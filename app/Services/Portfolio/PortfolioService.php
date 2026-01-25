<?php

namespace App\Services\Portfolio;

use App\DTO\Portfolio\PlanInput;
use App\DTO\Portfolio\TradeInput;
use App\Repositories\MarketData\CanonicalEodRepository;
use App\Repositories\MarketData\RunRepository;
use App\Repositories\PortfolioLotMatchRepository;
use App\Repositories\PortfolioLotRepository;
use App\Repositories\PortfolioPlanRepository;
use App\Repositories\PortfolioPositionEventRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\PortfolioTradeRepository;
use App\Repositories\TickerRepository;
use App\Trade\Portfolio\PortfolioPolicyCodes;
use App\Trade\Pricing\FeeConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PortfolioService
 *
 * Orchestrator portfolio sesuai docs/PORTFOLIO.md:
 * - source of truth = fills (portfolio_trades)
 * - FIFO lots + realized PnL (portfolio_lots + portfolio_lot_matches)
 * - derived cache (portfolio_positions)
 * - audit trail (portfolio_position_events)
 */
class PortfolioService
{
    private PortfolioTradeRepository $tradeRepo;
    private PortfolioLotRepository $lotRepo;
    private PortfolioLotMatchRepository $matchRepo;
    private PortfolioPositionRepository $posRepo;
    private PortfolioPositionEventRepository $eventRepo;
    private PortfolioPlanRepository $planRepo;
    private TickerRepository $tickerRepo;
    private RunRepository $runRepo;
    private CanonicalEodRepository $canonRepo;

    private float $buyFee;
    private float $sellFee;

    public function __construct(
        PortfolioTradeRepository $tradeRepo,
        PortfolioLotRepository $lotRepo,
        PortfolioLotMatchRepository $matchRepo,
        PortfolioPositionRepository $posRepo,
        PortfolioPositionEventRepository $eventRepo,
        PortfolioPlanRepository $planRepo,
        TickerRepository $tickerRepo,
        RunRepository $runRepo,
        CanonicalEodRepository $canonRepo,
        FeeConfig $feeCfg
    ) {
        $this->tradeRepo = $tradeRepo;
        $this->lotRepo = $lotRepo;
        $this->matchRepo = $matchRepo;
        $this->posRepo = $posRepo;
        $this->eventRepo = $eventRepo;
        $this->planRepo = $planRepo;
        $this->tickerRepo = $tickerRepo;
        $this->runRepo = $runRepo;
        $this->canonRepo = $canonRepo;
        $this->buyFee = $feeCfg->buyRate() + $feeCfg->extraBuyRate();
        $this->sellFee = $feeCfg->sellRate() + $feeCfg->extraSellRate();
    }

    /**
     * Create/Upsert plan from watchlist execution intent.
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool, plan_id?:int, error?:string}
     */
    public function upsertPlan(array $payload): array
    {
        $dto = PlanInput::fromArray($payload);
        if ($dto->tickerId <= 0) return ['ok' => false, 'error' => 'invalid_ticker_id'];
        $dto->strategyCode = strtoupper($dto->strategyCode);
        if ($dto->strategyCode === '' || $dto->asOfTradeDate === '' || $dto->planVersion === '') {
            return ['ok' => false, 'error' => 'invalid_plan_keys'];
        }
        if (!PortfolioPolicyCodes::isValid($dto->strategyCode)) {
            return ['ok' => false, 'error' => 'invalid_strategy_code'];
        }

        $row = [
            'account_id' => $dto->accountId,
            'ticker_id' => $dto->tickerId,
            'strategy_code' => $dto->strategyCode,
            'as_of_trade_date' => $dto->asOfTradeDate,
            'intent' => $dto->intent,
            'alloc_pct' => $dto->allocPct,
            'plan_version' => $dto->planVersion,
            'status' => (string)($payload['status'] ?? 'PLANNED'),
            'plan_snapshot_json' => json_encode($dto->planSnapshot, JSON_UNESCAPED_SLASHES),
            'entry_json' => isset($payload['entry']) ? json_encode($payload['entry'], JSON_UNESCAPED_SLASHES) : null,
            'risk_json' => isset($payload['risk']) ? json_encode($payload['risk'], JSON_UNESCAPED_SLASHES) : null,
            'take_profit_json' => isset($payload['take_profit']) ? json_encode($payload['take_profit'], JSON_UNESCAPED_SLASHES) : null,
            'timebox_json' => isset($payload['timebox']) ? json_encode($payload['timebox'], JSON_UNESCAPED_SLASHES) : null,
            'reason_codes_json' => isset($payload['reason_codes']) ? json_encode($payload['reason_codes'], JSON_UNESCAPED_SLASHES) : null,
            'entry_expiry_date' => $payload['entry_expiry_date'] ?? null,
            'max_holding_days' => $payload['max_holding_days'] ?? null,
        ];

        try {
            $up = $this->planRepo->upsertOne($row);
            $id = (int)($up['id'] ?? 0);

            // Audit: PLAN_CREATED only when new.
            if (($up['created'] ?? false) && $id > 0) {
                $plan = $this->planRepo->find($id);
                $this->insertEvent($dto->accountId, $dto->tickerId, $plan, [
                    'event_type' => 'PLAN_CREATED',
                    'qty_before' => null,
                    'qty_after' => null,
                    'price' => null,
                    'reason_code' => 'PLAN',
                    'payload_json' => json_encode(['plan_id' => $id, 'intent' => $dto->intent], JSON_UNESCAPED_SLASHES),
                ]);
            }

            return ['ok' => true, 'plan_id' => $id];
        } catch (\Throwable $e) {
            Log::channel('portfolio')->error('portfolio.upsert_plan_failed', [
                'error' => $e->getMessage(),
                'account_id' => $dto->accountId,
                'ticker_id' => $dto->tickerId,
                'strategy_code' => $dto->strategyCode,
            ]);
            return ['ok' => false, 'error' => 'upsert_plan_failed'];
        }
    }

    /**
     * Ingest 1 trade (fill) and update lots/matches/positions.
     *
     * Input accepts either ticker_id or symbol.
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool, trade_id?:int, created?:bool, error?:string}
     */
    public function ingestTrade(array $payload): array
    {
        // Resolve ticker_id by symbol if needed
        if (!isset($payload['ticker_id']) || (int)$payload['ticker_id'] <= 0) {
            $sym = (string)($payload['symbol'] ?? $payload['ticker'] ?? '');
            $id = $this->tickerRepo->resolveIdByCode($sym);
            if ($id !== null) {
                $payload['ticker_id'] = $id;
                $payload['symbol'] = strtoupper($sym);
            }
        }

        $dto = TradeInput::fromArray($payload);
        if ($dto->tickerId <= 0) return ['ok' => false, 'error' => 'invalid_ticker'];
        if ($dto->tradeDate === '') return ['ok' => false, 'error' => 'invalid_trade_date'];
        if ($dto->qty <= 0 || $dto->price <= 0) return ['ok' => false, 'error' => 'invalid_qty_or_price'];
        if ($dto->side !== 'BUY' && $dto->side !== 'SELL') return ['ok' => false, 'error' => 'invalid_side'];

        $logCtx = [
            'account_id' => $dto->accountId,
            'ticker_id' => $dto->tickerId,
            'trade_date' => $dto->tradeDate,
            'side' => $dto->side,
            'qty' => $dto->qty,
            'price' => $dto->price,
            'external_ref' => $dto->externalRef,
        ];

        try {
            return DB::transaction(function () use ($dto, $payload, $logCtx) {
                // Deterministic amounts: prefer broker-provided numbers (Ajaib etc.)
                $gross = isset($payload['gross_amount']) ? (float)$payload['gross_amount'] : ($dto->price * $dto->qty);
                $fee = isset($payload['fee_amount']) ? (float)$payload['fee_amount'] : ($gross * ($dto->side === 'BUY' ? $this->buyFee : $this->sellFee));
                $tax = isset($payload['tax_amount']) ? (float)$payload['tax_amount'] : 0.0;
                $net = isset($payload['net_amount'])
                    ? (float)$payload['net_amount']
                    : ($dto->side === 'BUY' ? ($gross + $fee + $tax) : ($gross - $fee - $tax));

                $tradeRow = [
                    'account_id' => $dto->accountId,
                    'ticker_id' => $dto->tickerId,
                    'symbol' => $dto->symbol !== '' ? $dto->symbol : null,
                    'trade_date' => $dto->tradeDate,
                    'side' => $dto->side,
                    'qty' => $dto->qty,
                    'price' => $dto->price,
                    'gross_amount' => $gross,
                    'fee_amount' => $fee,
                    'tax_amount' => $tax,
                    'net_amount' => $net,
                    'external_ref' => $dto->externalRef,
                    'trade_hash' => $payload['trade_hash'] ?? null,
                    'broker_ref' => $dto->brokerRef,
                    'source' => $dto->source ?? 'manual',
                    'currency' => $dto->currency,
                    'meta_json' => $dto->meta ? json_encode($dto->meta, JSON_UNESCAPED_SLASHES) : null,
                ];

                // Strict idempotency: if trade exists AND already applied downstream, do not mutate lots/matches again.
                $existing = $this->tradeRepo->findExistingByKey($tradeRow);
                if ($existing && isset($existing->id)) {
                    $tradeId = (int)$existing->id;
                    if ($dto->side === 'SELL' && $this->tradeRepo->existsMatchForSellTrade($tradeId)) {
                        return ['ok' => true, 'trade_id' => $tradeId, 'created' => false];
                    }
                    if ($dto->side === 'BUY' && $this->tradeRepo->existsLotForBuyTrade($tradeId)) {
                        return ['ok' => true, 'trade_id' => $tradeId, 'created' => false];
                    }
                }

                $up = $this->tradeRepo->upsertOne($tradeRow);
                $tradeId = (int)$up['id'];

                // Pre-state for events
                $pre = $this->lotRepo->summarizeOpenLots($dto->accountId, $dto->tickerId);
                $preQty = (int)$pre['qty'];

                $plan = null;
                if ($this->planRepo->tableExists()) {
                    $plan = $this->planRepo->findLatestForTicker($dto->accountId, $dto->tickerId);
                }

                if ($dto->side === 'BUY') {
                    // Validate entry fill against plan (expiry/intent). Never blocks ingest, but logs breaches.
                    if ($plan && $preQty <= 0) {
                        $v = $this->validateEntryFill($plan, $dto);
                        if (!$v['ok']) {
                            $this->insertEvent($dto->accountId, $dto->tickerId, $plan, [
                                'event_type' => 'POLICY_BREACH',
                                'qty_before' => $preQty,
                                'qty_after' => null,
                                'price' => $dto->price,
                                'reason_code' => 'ENTRY',
                                'notes' => (string)($v['reason'] ?? 'policy_breach'),
                                'payload_json' => json_encode(['trade_date' => $dto->tradeDate, 'entry_expiry_date' => $plan->entry_expiry_date ?? null, 'intent' => $plan->intent ?? null], JSON_UNESCAPED_SLASHES),
                            ]);
                        }
                    }

                    $unitCost = $dto->qty > 0 ? ($net / $dto->qty) : 0.0;
                    $this->lotRepo->createForBuyTrade([
                        'account_id' => $dto->accountId,
                        'ticker_id' => $dto->tickerId,
                        'buy_trade_id' => $tradeId,
                        'buy_date' => $dto->tradeDate,
                        'qty' => $dto->qty,
                        'remaining_qty' => $dto->qty,
                        'unit_cost' => $unitCost,
                        'total_cost' => $unitCost * $dto->qty,
                    ]);

                    $post = $this->lotRepo->summarizeOpenLots($dto->accountId, $dto->tickerId);
                    $this->upsertDerivedPosition($dto->accountId, $dto->tickerId, $plan, $post, 'OPEN');

                    // Strict consistency (fail-fast)
                    $this->assertConsistentState($dto->accountId, $dto->tickerId, $plan, $post);

                    // Events
                    $eventType = $preQty <= 0 ? 'ENTRY_FILLED' : 'ADD_FILLED';
                    $this->insertEvent($dto->accountId, $dto->tickerId, $plan, [
                        'event_type' => $eventType,
                        'qty_before' => $preQty,
                        'qty_after' => (int)$post['qty'],
                        'price' => $dto->price,
                        'reason_code' => 'FILL',
                        'payload_json' => json_encode(['trade_id' => $tradeId], JSON_UNESCAPED_SLASHES),
                    ]);

                    if ($plan && isset($plan->id)) {
                        // State transition: PLANNED -> OPENED
                        $this->planRepo->markOpened((int)$plan->id);
                        if ($eventType === 'ENTRY_FILLED') {
                            $this->insertEvent($dto->accountId, $dto->tickerId, $plan, [
                                'event_type' => 'PLAN_OPENED',
                                'qty_before' => null,
                                'qty_after' => (int)$post['qty'],
                                'price' => $dto->price,
                                'reason_code' => 'PLAN',
                                'payload_json' => json_encode(['plan_id' => (int)$plan->id, 'trade_id' => $tradeId], JSON_UNESCAPED_SLASHES),
                            ]);
                        }
                    }
                } else {
                    // SELL - FIFO match
                    $remaining = $dto->qty;
                    $openLots = $this->lotRepo->listOpenLotsFifo($dto->accountId, $dto->tickerId);
                    if (!$openLots) {
                        throw new \RuntimeException('no_open_lots_to_sell');
                    }

                    $sellNetPerShare = $dto->qty > 0 ? ($net / $dto->qty) : 0.0;
                    foreach ($openLots as $lot) {
                        if ($remaining <= 0) break;
                        $lotId = (int)($lot->id ?? 0);
                        $lotRem = (int)($lot->remaining_qty ?? 0);
                        if ($lotId <= 0 || $lotRem <= 0) continue;

                        $m = min($remaining, $lotRem);
                        $buyUnitCost = (float)($lot->unit_cost ?? 0);

                        $pnl = ($sellNetPerShare - $buyUnitCost) * $m;

                        $this->matchRepo->upsertOne([
                            'account_id' => $dto->accountId,
                            'ticker_id' => $dto->tickerId,
                            'sell_trade_id' => $tradeId,
                            'buy_lot_id' => $lotId,
                            'matched_qty' => $m,
                            'buy_unit_cost' => $buyUnitCost,
                            'sell_unit_price' => $sellNetPerShare,
                            'buy_fee_alloc' => null,
                            'sell_fee_alloc' => null,
                            'realized_pnl' => $pnl,
                        ]);

                        $this->lotRepo->decrementRemaining($lotId, $m);
                        $remaining -= $m;
                    }

                    if ($remaining > 0) {
                        throw new \RuntimeException('sell_qty_exceeds_open_qty');
                    }

                    $post = $this->lotRepo->summarizeOpenLots($dto->accountId, $dto->tickerId);
                    $this->upsertDerivedPosition(
                        $dto->accountId,
                        $dto->tickerId,
                        $plan,
                        $post,
                        ((int)$post['qty'] <= 0) ? 'CLOSED' : 'CLOSING'
                    );

                    // Strict consistency (fail-fast)
                    $this->assertConsistentState($dto->accountId, $dto->tickerId, $plan, $post);

                    $eventType = $this->inferSellEventType($plan, $sellNetPerShare, $dto->tradeDate);
                    $this->insertEvent($dto->accountId, $dto->tickerId, $plan, [
                        'event_type' => $eventType,
                        'qty_before' => $preQty,
                        'qty_after' => (int)$post['qty'],
                        'price' => $dto->price,
                        'reason_code' => 'FILL',
                        'payload_json' => json_encode(['trade_id' => $tradeId], JSON_UNESCAPED_SLASHES),
                    ]);
                }

                Log::channel('portfolio')->info('portfolio.ingest_trade_ok', $logCtx + [
                    'trade_id' => $tradeId,
                    'strategy_code' => $plan && isset($plan->strategy_code) ? (string)$plan->strategy_code : null,
                    'position_state' => ($dto->side === 'BUY') ? 'OPEN' : (((int)($post['qty'] ?? 0) <= 0) ? 'CLOSED' : 'CLOSING'),
                ]);
                return ['ok' => true, 'trade_id' => $tradeId, 'created' => (bool)$up['created']];
            }, 3);
        } catch (\Throwable $e) {
            Log::channel('portfolio')->error('portfolio.ingest_trade_failed', $logCtx + [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return ['ok' => false, 'error' => $e->getMessage() ?: 'ingest_failed'];
        }
    }

    /**
     * List positions (derived cache) per account.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listPositions(int $accountId = 1): array
    {
        $rows = $this->posRepo->listOpenPositions($accountId);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'position_id' => (int)($r->id ?? 0),
                'account_id' => (int)($r->account_id ?? $accountId),
                'ticker_id' => (int)($r->ticker_id ?? 0),
                'strategy_code' => $r->strategy_code !== null ? (string)$r->strategy_code : (($r->policy_code ?? null) ? (string)$r->policy_code : null),
                'state' => $r->state !== null ? (string)$r->state : null,
                'qty' => isset($r->qty) ? (int)($r->qty ?? 0) : ((int)($r->position_lots ?? 0) * 100),
                'avg_price' => (float)($r->avg_price ?? 0),
                'entry_date' => $r->entry_date !== null ? (string)$r->entry_date : null,
                'realized_pnl' => isset($r->realized_pnl) ? (float)($r->realized_pnl ?? 0) : 0.0,
                'unrealized_pnl' => $r->unrealized_pnl !== null ? (float)$r->unrealized_pnl : null,
                'market_value' => $r->market_value !== null ? (float)$r->market_value : null,
                'last_valued_date' => $r->last_valued_date !== null ? (string)$r->last_valued_date : null,
                'plan_id' => isset($r->plan_id) ? (int)($r->plan_id ?? 0) : null,
                'plan_version' => $r->plan_version !== null ? (string)$r->plan_version : null,
                'as_of_trade_date' => $r->as_of_trade_date !== null ? (string)$r->as_of_trade_date : null,
            ];
        }
        return $out;
    }

    /**
     * Expire plans whose entry_expiry_date <= today and still PLANNED.
     * Emits PLAN_EXPIRED events. Idempotent.
     */
    public function expirePlans(string $today, ?int $accountId = null): array
    {
        $today = trim($today);
        if ($today === '') return ['ok' => false, 'expired' => 0, 'error' => 'invalid_date'];
        try {
            $plans = $this->planRepo->listExpirable($today, $accountId);
            if (!$plans) return ['ok' => true, 'expired' => 0];
            $cnt = 0;
            foreach ($plans as $p) {
                $pid = (int)($p->id ?? 0);
                if ($pid <= 0) continue;
                $this->planRepo->markExpired($pid);
                $this->insertEvent((int)($p->account_id ?? 0), (int)($p->ticker_id ?? 0), $p, [
                    'event_type' => 'PLAN_EXPIRED',
                    'qty_before' => null,
                    'qty_after' => null,
                    'price' => null,
                    'reason_code' => 'PLAN',
                    'notes' => 'entry_expiry_reached',
                    'payload_json' => json_encode(['plan_id' => $pid, 'entry_expiry_date' => $p->entry_expiry_date ?? null], JSON_UNESCAPED_SLASHES),
                ]);
                $cnt++;
            }
            Log::channel('portfolio')->info('portfolio.expire_plans_ok', ['today' => $today, 'expired' => $cnt, 'account_id' => $accountId]);
            return ['ok' => true, 'expired' => $cnt];
        } catch (\Throwable $e) {
            Log::channel('portfolio')->error('portfolio.expire_plans_failed', ['today' => $today, 'account_id' => $accountId, 'error' => $e->getMessage()]);
            return ['ok' => false, 'expired' => 0, 'error' => 'expire_plans_failed'];
        }
    }

    /**
     * Update valuations using canonical close for given trade_date.
     *
     * @return array{ok:bool, valued:int, error?:string}
     */
    public function valueEod(string $tradeDate, int $accountId = 1): array
    {
        $tradeDate = trim($tradeDate);
        if ($tradeDate === '') return ['ok' => false, 'valued' => 0, 'error' => 'invalid_trade_date'];

        try {
            $open = $this->posRepo->openPositionsByTicker($accountId);
            if (!$open) return ['ok' => true, 'valued' => 0];

            $tickerIds = array_keys($open);

            // Valuation source selection (handle CANONICAL_HELD / gaps)
            $priceDate = $tradeDate;
            $runId = $this->runRepo->findLatestSuccessImportRunCoveringDate($tradeDate);
            $isStale = false;
            $staleReason = null;
            if (!$runId) {
                // If current run is held/gapped, fall back to last_good_trade_date
                $cover = $this->runRepo->findLatestImportRunCoveringDate($tradeDate);
                if ($cover && isset($cover->last_good_trade_date) && $cover->last_good_trade_date) {
                    $priceDate = (string)$cover->last_good_trade_date;
                    $runId = $this->runRepo->findLatestSuccessImportRunCoveringDate($priceDate);
                    $isStale = true;
                    $staleReason = 'last_good_trade_date';
                }

                // If still none: pick latest SUCCESS at/before tradeDate
                if (!$runId) {
                    $before = $this->runRepo->findLatestSuccessImportRunAtOrBeforeDate($tradeDate);
                    if ($before && isset($before->run_id) && isset($before->effective_end_date)) {
                        $runId = (int)$before->run_id;
                        $priceDate = (string)$before->effective_end_date;
                        $isStale = true;
                        $staleReason = 'latest_success_before_date';
                    }
                }
            }

            if (!$runId) {
                return ['ok' => false, 'valued' => 0, 'error' => 'no_market_data_for_valuation'];
            }

            if ($isStale) {
                Log::channel('portfolio')->warning('portfolio.value_eod_stale', [
                    'trade_date' => $tradeDate,
                    'price_date' => $priceDate,
                    'reason' => $staleReason,
                    'run_id' => $runId,
                    'account_id' => $accountId,
                ]);
            }

            $closes = $this->canonRepo->loadByRunAndDate($runId, $priceDate, $tickerIds);

            $valued = 0;
            foreach ($open as $tid => $pos) {
                $close = $closes[$tid]['close'] ?? null;
                if ($close === null) continue;

                $qty = (int)($pos['qty'] ?? 0);
                $avg = (float)($pos['avg_price'] ?? 0);
                if ($qty <= 0 || $avg <= 0) continue;

                $mv = $qty * (float)$close;
                $upl = ($close - $avg) * $qty;

                $this->posRepo->upsertPosition([
                    'account_id' => $accountId,
                    'ticker_id' => (int)$tid,
                    'is_open' => 1,
                    'market_value' => $mv,
                    'unrealized_pnl' => $upl,
                    'last_valued_date' => $tradeDate,
                ]);
                $valued++;
            }

            return ['ok' => true, 'valued' => $valued];
        } catch (\Throwable $e) {
            Log::channel('portfolio')->error('portfolio.value_eod_failed', [
                'trade_date' => $tradeDate,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'valued' => 0, 'error' => 'value_eod_failed'];
        }
    }

    /**
     * Strict consistency: portfolio_positions.qty MUST equal SUM(portfolio_lots.remaining_qty).
     * If mismatch: write INCONSISTENT_STATE and throw to force rollback.
     *
     * @param array{qty:int, avg_cost:float|null, entry_date:string|null, lots:int} $lots
     */
    private function assertConsistentState(int $accountId, int $tickerId, ?object $plan, array $lots): void
    {
        $expectedQty = (int)($lots['qty'] ?? 0);
        $pos = $this->posRepo->findOpen($accountId, $tickerId);
        $posQty = 0;
        if ($pos && isset($pos->qty)) {
            $posQty = (int)($pos->qty ?? 0);
        } elseif ($pos && isset($pos->position_lots)) {
            $posQty = ((int)($pos->position_lots ?? 0)) * 100;
        }

        if ($pos && $posQty !== $expectedQty) {
            $this->insertEvent($accountId, $tickerId, $plan, [
                'event_type' => 'INCONSISTENT_STATE',
                'qty_before' => $posQty,
                'qty_after' => $expectedQty,
                'price' => null,
                'reason_code' => 'CONSISTENCY',
                'notes' => 'positions.qty != lots.remaining_qty',
                'payload_json' => json_encode(['expected_qty' => $expectedQty, 'pos_qty' => $posQty], JSON_UNESCAPED_SLASHES),
            ]);
            throw new \RuntimeException('inconsistent_state_positions_qty');
        }
    }

    /**
     * Infer sell event type from plan snapshot (TP/SL/Timebox). Fallback EXIT_MANUAL.
     */
    private function inferSellEventType(?object $plan, float $sellNetPerShare, string $sellTradeDate): string
    {
        $sellDate = trim($sellTradeDate);
        $sl = null;
        $tp1 = null;
        $tp2 = null;
        $maxHold = null;
        $entryDate = null;

        if ($plan) {
            // risk_json / take_profit_json / timebox_json are stored as JSON strings
            $risk = $this->decodeJsonObj($plan->risk_json ?? null);
            if (is_array($risk)) {
                $sl = isset($risk['sl_price']) ? (float)$risk['sl_price'] : (isset($risk['stop_loss_price']) ? (float)$risk['stop_loss_price'] : null);
            }
            $tp = $this->decodeJsonObj($plan->take_profit_json ?? null);
            if (is_array($tp)) {
                $tp1 = isset($tp['tp1_price']) ? (float)$tp['tp1_price'] : (isset($tp['take_profit_1_price']) ? (float)$tp['take_profit_1_price'] : null);
                $tp2 = isset($tp['tp2_price']) ? (float)$tp['tp2_price'] : (isset($tp['take_profit_2_price']) ? (float)$tp['take_profit_2_price'] : null);
            }
            $timebox = $this->decodeJsonObj($plan->timebox_json ?? null);
            if (is_array($timebox)) {
                $maxHold = isset($timebox['max_holding_days']) ? (int)$timebox['max_holding_days'] : null;
            }
            $entryDate = isset($plan->as_of_trade_date) ? (string)$plan->as_of_trade_date : null;
        }

        if ($sl !== null && $sellNetPerShare <= $sl) {
            return 'EXIT_SL';
        }
        if ($tp2 !== null && $sellNetPerShare >= $tp2) {
            return 'TP2_TAKEN';
        }
        if ($tp1 !== null && $sellNetPerShare >= $tp1) {
            return 'TP1_TAKEN';
        }
        if ($maxHold !== null && $maxHold > 0 && $entryDate) {
            $holdDays = $this->diffDays($entryDate, $sellDate);
            if ($holdDays !== null && $holdDays >= $maxHold) {
                return 'EXIT_TIME';
            }
        }
        return 'EXIT_MANUAL';
    }

    /**
     * Validate entry fill against plan expiry and intent.
     * @return array{ok:bool, reason?:string}
     */
    private function validateEntryFill(object $plan, TradeInput $dto): array
    {
        $intent = isset($plan->intent) ? strtoupper((string)$plan->intent) : '';
        if ($intent !== '' && !in_array($intent, ['ENTRY', 'ADD', 'BUY'], true)) {
            return ['ok' => false, 'reason' => 'intent_not_entry'];
        }

        if (isset($plan->entry_expiry_date) && $plan->entry_expiry_date) {
            $expiry = (string)$plan->entry_expiry_date;
            if (trim($expiry) !== '' && $dto->tradeDate > $expiry) {
                return ['ok' => false, 'reason' => 'entry_after_expiry'];
            }
        }
        return ['ok' => true];
    }

    /** @return array<string,mixed>|null */
    private function decodeJsonObj($json): ?array
    {
        if ($json === null) return null;
        if (is_array($json)) return $json;
        if (!is_string($json)) return null;
        $json = trim($json);
        if ($json === '') return null;
        $d = json_decode($json, true);
        return is_array($d) ? $d : null;
    }

    private function diffDays(string $from, string $to): ?int
    {
        try {
            $a = new \DateTime($from);
            $b = new \DateTime($to);
            return (int)$a->diff($b)->format('%r%a');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed>|null $plan
     * @param array{qty:int, avg_cost:float|null, entry_date:string|null, lots:int} $lots
     */
    private function upsertDerivedPosition(int $accountId, int $tickerId, ?object $plan, array $lots, string $state): void
    {
        $qty = (int)($lots['qty'] ?? 0);
        $avgCost = $lots['avg_cost'] !== null ? (float)$lots['avg_cost'] : 0.0;
        $entryDate = $lots['entry_date'] ?? null;
        $lotsCount = (int)($lots['lots'] ?? 0);

        $realized = 0.0;
        if ($this->matchRepo->tableExists()) {
            $realized = $this->matchRepo->sumRealizedPnl($accountId, $tickerId);
        }

        $strategy = null;
        $planId = null;
        $planVer = null;
        $asOf = null;
        $snapJson = null;
        if ($plan) {
            $planId = isset($plan->id) ? (int)$plan->id : null;
            $strategy = isset($plan->strategy_code) ? (string)$plan->strategy_code : null;
            $planVer = isset($plan->plan_version) ? (string)$plan->plan_version : null;
            $asOf = isset($plan->as_of_trade_date) ? (string)$plan->as_of_trade_date : null;
            $snapJson = isset($plan->plan_snapshot_json) ? (string)$plan->plan_snapshot_json : null;
        }

        $state = strtoupper(trim($state));
        if ($state === '') {
            $state = $qty > 0 ? 'OPEN' : 'CLOSED';
        }

        $this->posRepo->upsertPosition([
            'account_id' => $accountId,
            'ticker_id' => $tickerId,
            'is_open' => $qty > 0 ? 1 : 0,
            'qty' => $qty,
            'avg_price' => $avgCost,
            'position_lots' => $lotsCount,
            'entry_date' => $entryDate,
            'strategy_code' => $strategy,
            'policy_code' => $strategy, // backward compat
            'state' => $state,
            'realized_pnl' => $realized,
            'plan_id' => $planId,
            'plan_version' => $planVer,
            'as_of_trade_date' => $asOf,
            'plan_snapshot_json' => $snapJson,
        ]);
    }

    /**
     * @param array<string,mixed>|null $plan
     * @param array<string,mixed> $event
     */
    private function insertEvent(int $accountId, int $tickerId, ?object $plan, array $event): void
    {
        if (!$this->eventRepo->tableExists()) return;

        $row = [
            'account_id' => $accountId,
            'ticker_id' => $tickerId,
            'strategy_code' => $plan && isset($plan->strategy_code) ? (string)$plan->strategy_code : (isset($event['strategy_code']) ? (string)$event['strategy_code'] : null),
            'plan_version' => $plan && isset($plan->plan_version) ? (string)$plan->plan_version : (isset($event['plan_version']) ? (string)$event['plan_version'] : null),
            'as_of_trade_date' => $plan && isset($plan->as_of_trade_date) ? (string)$plan->as_of_trade_date : (isset($event['as_of_trade_date']) ? (string)$event['as_of_trade_date'] : null),
            'event_type' => (string)($event['event_type'] ?? 'UNKNOWN'),
            'qty_before' => $event['qty_before'] ?? null,
            'qty_after' => $event['qty_after'] ?? null,
            'price' => $event['price'] ?? null,
            'reason_code' => $event['reason_code'] ?? null,
            'notes' => $event['notes'] ?? null,
            'payload_json' => $event['payload_json'] ?? null,
            'created_at' => now(),
        ];

        $this->eventRepo->insertOne($row);
    }
}
