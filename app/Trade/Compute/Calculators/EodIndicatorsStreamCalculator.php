<?php

namespace App\Trade\Compute\Calculators;

use App\Trade\Compute\Classifiers\DecisionClassifier;
use App\Trade\Compute\Classifiers\PatternClassifier;
use App\Trade\Compute\Classifiers\VolumeLabelClassifier;
use App\Trade\Compute\Rolling\RollingAtrWilder;
use App\Trade\Compute\Rolling\RollingRsiWilder;
use App\Trade\Compute\Rolling\RollingSma;
use App\Trade\Compute\SignalAgeTracker;
use App\Trade\Support\PriceBasisPolicy;
use DateTimeInterface;

/**
 * Pure(ish) EOD indicator streaming calculator.
 *
 * SRP_Performa: heavy compute logic lives here (domain layer),
 * orchestration (date resolution, chunking, DB upsert) stays in the service.
 *
 * Notes:
 * - Input cursor is expected ordered by ticker_id then trade_date.
 * - All formulas and rounding semantics must stay identical to the previous implementation.
 */
final class EodIndicatorsStreamCalculator
{
    private DecisionClassifier $decision;
    private VolumeLabelClassifier $volume;
    private PatternClassifier $pattern;
    private SignalAgeTracker $age;
    private PriceBasisPolicy $basisPolicy;

    /** @var callable|null */
    private $onInvalidBarOnTradeDate;

    /** @var callable|null */
    private $onInsufficientWindowOnTradeDate;

    /** @var callable|null */
    private $onCorporateActionOnTradeDate;

    private int $processed = 0;
    private int $skippedInvalidOnTradeDate = 0;
    private array $seenOnTradeDate = [];
    private array $invalidOnTradeDate = [];

    public function __construct(
        DecisionClassifier $decision,
        VolumeLabelClassifier $volume,
        PatternClassifier $pattern,
        SignalAgeTracker $age,
        PriceBasisPolicy $basisPolicy
    ) {
        $this->decision = $decision;
        $this->volume = $volume;
        $this->pattern = $pattern;
        $this->age = $age;
        $this->basisPolicy = $basisPolicy;
    }

    /**
     * Provide callback executed when an invalid canonical bar is encountered on the computed trade date.
     *
     * SRP_Performa: logging should be handled by orchestration layer, so we emit an event-like callback.
     *
     * Signature: fn(array $context): void
     */
    public function onInvalidBarOnTradeDate(?callable $cb): self
    {
        $this->onInvalidBarOnTradeDate = $cb;
        return $this;
    }

    /**
     * Provide callback executed when indicator rolling windows are insufficient on the computed trade date.
     *
     * compute_eod.md contract: if window not complete, indicator may be NULL but MUST be logged,
     * and decision must not appear as Strong/Layak.
     *
     * Signature: fn(array $context): void
     */
    public function onInsufficientWindowOnTradeDate(?callable $cb): self
    {
        $this->onInsufficientWindowOnTradeDate = $cb;
        return $this;
    }

    /**
     * Provide callback executed when corporate action hints are present on the computed trade date.
     *
     * compute_eod.md contract: do NOT silently smooth splits; flag + stop recommendations.
     *
     * Signature: fn(array $context): void
     */
    public function onCorporateActionOnTradeDate(?callable $cb): self
    {
        $this->onCorporateActionOnTradeDate = $cb;
        return $this;
    }

    /**
     * Stream computed rows for a given trade date.
     *
     * @param iterable $cursor  Rows from ticker_ohlc_daily in [startDate..tradeDate] for a chunk of tickers.
     * @param string   $tradeDate
     * @param array    $prevSnaps Map[ticker_id] => prev indicator snapshot row (array)
     * @param mixed    $now       Timestamp (string/Carbon) to put into created_at/updated_at
     *
     * @return \Generator<int, array>
     */
    public function streamRows(iterable $cursor, string $tradeDate, array $prevSnaps, $now): \Generator
    {
        // reset per-run state
        $this->processed = 0;
        $this->skippedInvalidOnTradeDate = 0;
        $this->seenOnTradeDate = [];
        $this->invalidOnTradeDate = [];

        $curTicker = null;

        $sma20 = $sma50 = $sma200 = null;
        $rsi14 = null;
        $atr14 = null;

        $lows20 = [];
        $highs20 = [];

        $lastVols20 = [];
        $sumVol20 = 0.0;

        foreach ($cursor as $r) {
            $tid = (int) $r->ticker_id;

            if ($curTicker !== $tid) {
                $curTicker = $tid;

                $sma20 = new RollingSma(20);
                $sma50 = new RollingSma(50);
                $sma200 = new RollingSma(200);
                $rsi14 = new RollingRsiWilder(14);
                $atr14 = new RollingAtrWilder(14);

                $lows20 = [];
                $highs20 = [];

                $lastVols20 = [];
                $sumVol20 = 0.0;
            }

            // support/resistance exclude today: hitung dari buffer sebelum push bar hari ini
            $support20 = null;
            $resist20 = null;
            if (count($lows20) >= 20)  $support20 = min($lows20);
            if (count($highs20) >= 20) $resist20  = max($highs20);

            // volSma20Prev (exclude today)
            $volSma20Prev = null;
            if (count($lastVols20) >= 20) $volSma20Prev = ($sumVol20 / 20.0);

            // Canonical data quality guard
            if (!$this->isValidBar($r)) {
                $rowDate = $this->toDateString($r->trade_date);

                if ($rowDate === $tradeDate) {
                    $this->skippedInvalidOnTradeDate++;
                    $this->invalidOnTradeDate[$tid] = true;

                    if (is_callable($this->onInvalidBarOnTradeDate)) {
                        ($this->onInvalidBarOnTradeDate)([
                            'trade_date' => $tradeDate,
                            'ticker_id' => (int) $r->ticker_id,
                            'open' => $r->open,
                            'high' => $r->high,
                            'low' => $r->low,
                            'close' => $r->close,
                            'adj_close' => $r->adj_close ?? null,
                            'price_basis' => property_exists($r, 'price_basis') ? ($r->price_basis ?? null) : null,
                            'volume' => $r->volume,
                        ]);
                    }

                    // Contract: skip/flag invalid bar. For target_date we still emit a neutral row
                    // so downstream knows this ticker is unusable on this date.
                    $signalCode = 0;
                    $volumeLabelCode = 1;
                    $decisionCode = 2;

                    $prevSnap = $prevSnaps[$tid] ?? null;
                    $age = $this->age->computeFromPrev($tid, $tradeDate, $signalCode, $prevSnap);

                    $adj = isset($r->adj_close) && $r->adj_close !== null ? (float) $r->adj_close : null;
                    $caEvent = property_exists($r, 'ca_event') ? ($r->ca_event ?? null) : null;
                    $caHint  = property_exists($r, 'ca_hint')  ? ($r->ca_hint  ?? null) : null;

                    $row = [
                        'ticker_id' => $tid,
                        'trade_date' => $tradeDate,

                        'open' => $r->open !== null ? (float) $r->open : null,
                        'high' => $r->high !== null ? (float) $r->high : null,
                        'low'  => $r->low !== null ? (float) $r->low  : null,
                        'close' => $r->close !== null ? (float) $r->close : null,
                        'adj_close' => $adj,
                        'ca_hint' => $caHint,
                        'ca_event' => $caEvent,

                        'basis_used' => PriceBasisPolicy::BASIS_CLOSE,
                        'price_used' => $r->close !== null ? (float) $r->close : null,
                        'volume' => $r->volume !== null ? (int) $r->volume : null,

                        'ma20' => null,
                        'ma50' => null,
                        'ma200' => null,
                        'rsi14' => null,
                        'atr14' => null,
                        'support_20d' => null,
                        'resistance_20d' => null,
                        'vol_sma20' => null,
                        'vol_ratio' => null,

                        'decision_code' => $decisionCode,
                        'signal_code' => $signalCode,
                        'volume_label_code' => $volumeLabelCode,

                        'signal_first_seen_date' => $age['signal_first_seen_date'],
                        'signal_age_days' => $age['signal_age_days'],

                        // scoring (neutral)
                        'score_total' => 0,
                        'score_trend' => 0,
                        'score_momentum' => 0,
                        'score_volume' => 0,
                        'score_breakout' => 0,
                        'score_risk' => 0,

                        'is_valid' => 0,
                        'invalid_reason' => 'INVALID_BAR',

                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $this->seenOnTradeDate[$tid] = true;
                    $this->processed++;
                    yield $row;
                }

                // Skip this bar entirely (tidak ikut warmup rolling metrics).
                continue;
            }

            // bar today (real market close)
            $closeReal = (float) $r->close;
            $high  = (float) $r->high;
            $low   = (float) $r->low;
            $vol   = (float) $r->volume;

            // Phase 5: price basis for indicators (contract: honor price_basis)
            $adj = isset($r->adj_close) && $r->adj_close !== null ? (float) $r->adj_close : null;
            $priceBasis = property_exists($r, 'price_basis') ? ($r->price_basis ?? null) : null;
            $pick = $this->basisPolicy->pickForIndicators($priceBasis, $closeReal, $adj);

            $priceUsed = (float) $pick['price'];
            $basisUsed = (string) $pick['basis'];

            $sma20->push($priceUsed);
            $sma50->push($priceUsed);
            $sma200->push($priceUsed);
            $rsi14->push($priceUsed);

            // ATR tetap pakai data real
            $atr14->push($high, $low, $closeReal);

            // update buffers
            $lows20[] = $low;   if (count($lows20) > 20) array_shift($lows20);
            $highs20[] = $high; if (count($highs20) > 20) array_shift($highs20);

            $lastVols20[] = $vol;
            $sumVol20 += $vol;
            if (count($lastVols20) > 20) {
                $out = array_shift($lastVols20);
                $sumVol20 -= $out;
            }

            // hanya emit row yang trade_date == $tradeDate
            $rowDate = $this->toDateString($r->trade_date);
            if ($rowDate !== $tradeDate) {
                continue;
            }

            // ---- Corporate action guard (split/discontinuity hints) ----
            $caEvent = property_exists($r, 'ca_event') ? ($r->ca_event ?? null) : null;
            $caHint  = property_exists($r, 'ca_hint')  ? ($r->ca_hint  ?? null) : null;
            $hasCa = ($caEvent !== null && $caEvent !== '') || ($caHint !== null && $caHint !== '');

            if ($hasCa) {
                if (is_callable($this->onCorporateActionOnTradeDate)) {
                    ($this->onCorporateActionOnTradeDate)([
                        'trade_date' => $tradeDate,
                        'ticker_id' => $tid,
                        'ca_event' => $caEvent,
                        'ca_hint' => $caHint,
                        'basis_used_candidate' => $basisUsed,
                        'price_used_candidate' => $priceUsed,
                        'close' => $closeReal,
                        'adj_close' => $adj,
                    ]);
                }

                // Stop recommendation: output neutral row (indicators NULL) and force decision=Hindari.
                // Rebuild canonical should be performed first, then recompute this range.
                $signalCode = 0;
                $volumeLabelCode = 1;
                $decisionCode = 2;

                $prevSnap = $prevSnaps[$tid] ?? null;
                $age = $this->age->computeFromPrev($tid, $tradeDate, $signalCode, $prevSnap);

                $row = [
                    'ticker_id' => $tid,
                    'trade_date' => $tradeDate,

                    'open' => (float) $r->open,
                    'high' => $high,
                    'low' => $low,
                    'close' => $closeReal,
                    'adj_close' => $adj,
                    'basis_used' => PriceBasisPolicy::BASIS_CLOSE,
                    'price_used' => $closeReal,
                    'volume' => (int) $r->volume,

                    'ca_hint' => $caHint,
                    'ca_event' => $caEvent,

                    'ma20' => null,
                    'ma50' => null,
                    'ma200' => null,
                    'rsi14' => null,
                    'atr14' => null,
                    'support_20d' => null,
                    'resistance_20d' => null,
                    'vol_sma20' => null,
                    'vol_ratio' => null,

                    // scoring (neutral)
                    'score_total' => 0,
                    'score_trend' => 0,
                    'score_momentum' => 0,
                    'score_volume' => 0,
                    'score_breakout' => 0,
                    'score_risk' => 0,

                    // CA guard = data valid, but indicators are intentionally neutralized.
                    // Keep semantics consistent:
                    // invalid_reason != null => is_valid = 0.
                    'is_valid' => 0,
                    'invalid_reason' => 'CA_GUARD',

                    'decision_code' => $decisionCode,
                    'signal_code' => $signalCode,
                    'volume_label_code' => $volumeLabelCode,

                    'signal_first_seen_date' => $age['signal_first_seen_date'],
                    'signal_age_days' => $age['signal_age_days'],

                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $this->seenOnTradeDate[$tid] = true;
                $this->processed++;
                yield $row;
                continue;
            }

            $volRatio = null;
            if ($volSma20Prev !== null && $volSma20Prev > 0) {
                $volRatio = round($vol / $volSma20Prev, 4);
            }

            $metrics = [
                'open' => (float) $r->open,
                'high' => $high,
                'low'  => $low,
                'close'=> $closeReal,
                'volume' => $vol,

                'ma20' => $sma20->value(),
                'ma50' => $sma50->value(),
                'ma200'=> $sma200->value(),
                'rsi14'=> $rsi14->value(),
                'atr14'=> $atr14->value(),

                'support_20d' => $support20,
                'resistance_20d' => $resist20,

                'vol_sma20' => $volSma20Prev,
                'vol_ratio' => $volRatio,
            ];

            $signalCode = $this->pattern->classify($metrics);
            $volumeLabelCode = $this->volume->classify($volRatio);

            // inject context untuk decision
            $metrics['signal_code'] = $signalCode;
            $metrics['volume_label'] = $volumeLabelCode;

            $decisionCode = $this->decision->classify($metrics);

            // ---- Rolling window contract (trading days) ----
            // If indicators are still NULL due to insufficient canonical history, we must:
            // 1) emit warning (handled by service via callback)
            // 2) never output a decision that looks Strong/Layak
            $missing = [];
            if ($metrics['ma20'] === null) $missing[] = 'ma20';
            if ($metrics['ma50'] === null) $missing[] = 'ma50';
            if ($metrics['ma200'] === null) $missing[] = 'ma200';
            if ($metrics['rsi14'] === null) $missing[] = 'rsi14';
            if ($metrics['atr14'] === null) $missing[] = 'atr14';
            if ($support20 === null) $missing[] = 'support_20d';
            if ($resist20 === null) $missing[] = 'resistance_20d';
            if ($volSma20Prev === null) $missing[] = 'vol_sma20';
            if ($volRatio === null) $missing[] = 'vol_ratio';

            if (!empty($missing)) {
                if (is_callable($this->onInsufficientWindowOnTradeDate)) {
                    ($this->onInsufficientWindowOnTradeDate)([
                        'trade_date' => $tradeDate,
                        'ticker_id' => $tid,
                        'missing' => $missing,
                        'sma20_count' => $sma20->count(),
                        'sma50_count' => $sma50->count(),
                        'sma200_count' => $sma200->count(),
                        'buf_lows20' => count($lows20),
                        'buf_highs20' => count($highs20),
                        'buf_vols20' => count($lastVols20),
                    ]);
                }

                // Prevent strong-looking decisions when history is incomplete.
                if ($decisionCode >= 4) {
                    $decisionCode = 2; // Hindari
                }
            }

            $prevSnap = $prevSnaps[$tid] ?? null;
            $age = $this->age->computeFromPrev($tid, $tradeDate, $signalCode, $prevSnap);

            $row = [
                'ticker_id' => $tid,
                'trade_date' => $tradeDate,

                'open' => (float) $r->open,
                'high' => $high,
                'low' => $low,
                'close' => $closeReal,
                'adj_close' => $adj,
                'basis_used' => $basisUsed,
                'price_used' => $priceUsed,
                'volume' => (int) $r->volume,

                'ca_hint' => $caHint,
                'ca_event' => $caEvent,

                'ma20' => $metrics['ma20'] !== null ? round($metrics['ma20'], 4) : null,
                'ma50' => $metrics['ma50'] !== null ? round($metrics['ma50'], 4) : null,
                'ma200'=> $metrics['ma200'] !== null ? round($metrics['ma200'], 4) : null,

                // kolom rsi14 di DB = decimal(6,2) -> round 2 supaya output stabil dan tidak bergantung rounding DB.
                'rsi14' => $metrics['rsi14'] !== null ? round($metrics['rsi14'], 2) : null,
                'atr14' => $metrics['atr14'] !== null ? round($metrics['atr14'], 4) : null,

                'support_20d' => $support20 !== null ? round($support20, 4) : null,
                'resistance_20d' => $resist20 !== null ? round($resist20, 4) : null,

                'vol_sma20' => $volSma20Prev !== null ? round($volSma20Prev, 4) : null,
                'vol_ratio' => $volRatio,

                'decision_code' => $decisionCode,
                'signal_code' => $signalCode,
                'volume_label_code' => $volumeLabelCode,

                'signal_first_seen_date' => $age['signal_first_seen_date'],
                'signal_age_days' => $age['signal_age_days'],

                // scoring
                'score_total' => 0,
                'score_trend' => 0,
                'score_momentum' => 0,
                'score_volume' => 0,
                'score_breakout' => 0,
                'score_risk' => 0,

                'is_valid' => 1,
                'invalid_reason' => null,

                'created_at' => $now,
                'updated_at' => $now,
            ];

            // compute score only if the row is not neutralized
            $scores = $this->computeScores($metrics, $signalCode, $volumeLabelCode, $decisionCode);
            $row['score_total'] = $scores['score_total'];
            $row['score_trend'] = $scores['score_trend'];
            $row['score_momentum'] = $scores['score_momentum'];
            $row['score_volume'] = $scores['score_volume'];
            $row['score_breakout'] = $scores['score_breakout'];
            $row['score_risk'] = $scores['score_risk'];

            $this->seenOnTradeDate[$tid] = true;
            $this->processed++;

            yield $row;
        }
    }

    public function processedCount(): int
    {
        return $this->processed;
    }

    public function skippedInvalidOnTradeDateCount(): int
    {
        return $this->skippedInvalidOnTradeDate;
    }

    public function seenOnTradeDateMap(): array
    {
        return $this->seenOnTradeDate;
    }

    public function invalidOnTradeDateMap(): array
    {
        return $this->invalidOnTradeDate;
    }

    /**
     * Simple scoring model (kept deterministic + lightweight).
     *
     * Tujuan:
     * - Menghindari kolom score_* jadi "kosong" (over/unused).
     * - Memberi ranking sederhana untuk Watchlist tanpa mengubah decision_code.
     *
     * Skala kecil (approx): -5 .. +10.
     * Downstream boleh pakai score_total untuk sorting saja.
     *
     * @return array{score_total:int,score_trend:int,score_momentum:int,score_volume:int,score_breakout:int,score_risk:int}
     */
    private function computeScores(array $m, int $signalCode, int $volumeLabelCode, int $decisionCode): array
    {
        // For neutralized rows (invalid/CA guard), caller should keep scores at 0.
        if ($decisionCode <= 0) {
            return [
                'score_total' => 0,
                'score_trend' => 0,
                'score_momentum' => 0,
                'score_volume' => 0,
                'score_breakout' => 0,
                'score_risk' => 0,
            ];
        }

        $close = isset($m['close']) ? (float) $m['close'] : 0.0;
        if ($close <= 0) {
            return [
                'score_total' => 0,
                'score_trend' => 0,
                'score_momentum' => 0,
                'score_volume' => 0,
                'score_breakout' => 0,
                'score_risk' => 0,
            ];
        }

        $ma20  = $m['ma20']  !== null ? (float) $m['ma20']  : null;
        $ma50  = $m['ma50']  !== null ? (float) $m['ma50']  : null;
        $ma200 = $m['ma200'] !== null ? (float) $m['ma200'] : null;
        $rsi   = $m['rsi14'] !== null ? (float) $m['rsi14'] : null;
        $atr   = $m['atr14'] !== null ? (float) $m['atr14'] : null;
        $volRatio = $m['vol_ratio'] !== null ? (float) $m['vol_ratio'] : null;
        $res  = $m['resistance_20d'] !== null ? (float) $m['resistance_20d'] : null;

        // Trend: close>ma20, ma20>=ma50, ma50>=ma200
        $trend = 0;
        if ($ma20 !== null && $close > $ma20) $trend += 1;
        if ($ma20 !== null && $ma50 !== null && $ma20 >= $ma50) $trend += 1;
        if ($ma50 !== null && $ma200 !== null && $ma50 >= $ma200) $trend += 1;

        // Momentum: prefer RSI 55-68, penalize extremes
        $mom = 0;
        if ($rsi !== null) {
            if ($rsi >= 55 && $rsi <= 68) $mom += 2;
            elseif (($rsi >= 50 && $rsi < 55) || ($rsi > 68 && $rsi <= 72)) $mom += 1;
            elseif ($rsi < 40 || $rsi > 75) $mom -= 1;
        }

        // Volume: use ratio if available, otherwise approximate by label
        $vol = 0;
        if ($volRatio !== null) {
            if ($volRatio >= 2.0) $vol += 2;
            elseif ($volRatio >= 1.3) $vol += 1;
            elseif ($volRatio < 0.9) $vol -= 1;
        } else {
            if ($volumeLabelCode >= 7) $vol += 2;
            elseif ($volumeLabelCode >= 6) $vol += 1;
        }

        // Breakout: above resistance or strong breakout signal
        $br = 0;
        if ($res !== null) {
            if ($close > $res) $br += 2;
            elseif ($res > 0 && ($close / $res) >= 0.99) $br += 1;
        }
        if (in_array($signalCode, [4, 5, 6, 7], true)) $br += 1;

        // Risk: penalize ATR% if too high
        $risk = 0;
        if ($atr !== null && $atr > 0) {
            $atrPct = ($atr / $close) * 100.0;
            if ($atrPct > 6.0) $risk -= 2;
            elseif ($atrPct > 4.0) $risk -= 1;
        }

        // Decision bias (small) so Layak/Confirm tends to float to top, but does not dominate.
        $bias = 0;
        if ($decisionCode === 5) $bias = 1;
        elseif ($decisionCode === 4) $bias = 0;
        elseif ($decisionCode <= 2) $bias = -1;

        $total = $trend + $mom + $vol + $br + $risk + $bias;

        return [
            'score_total' => (int) $this->clampInt($total, -20, 20),
            'score_trend' => (int) $this->clampInt($trend, -10, 10),
            'score_momentum' => (int) $this->clampInt($mom, -10, 10),
            'score_volume' => (int) $this->clampInt($vol, -10, 10),
            'score_breakout' => (int) $this->clampInt($br, -10, 10),
            'score_risk' => (int) $this->clampInt($risk, -10, 10),
        ];
    }

    private function clampInt($v, int $min, int $max): int
    {
        $i = (int) round((float) $v);
        if ($i < $min) return $min;
        if ($i > $max) return $max;
        return $i;
    }

    /**
     * Guard minimal untuk canonical OHLC.
     * Null/0 akan merusak rolling MA/RSI/ATR dan membuat output mismatch besar.
     */
    private function isValidBar($r): bool
    {
        // required prices
        if ($r->open === null || $r->high === null || $r->low === null || $r->close === null) return false;

        $open = (float) $r->open;
        $high = (float) $r->high;
        $low  = (float) $r->low;
        $close= (float) $r->close;

        if ($open <= 0 || $high <= 0 || $low <= 0 || $close <= 0) return false;
        if ($high < $low) return false;

        // volume boleh 0 (jarang), tapi tidak boleh null/negatif
        if ($r->volume === null) return false;
        $vol = (float) $r->volume;
        if ($vol < 0) return false;

        return true;
    }

    private function toDateString($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // kalau string datetime, ambil 10 char pertama
        $s = (string) $value;
        if (strlen($s) >= 10) {
            return substr($s, 0, 10);
        }

        // fallback
        return $s;
    }
}
