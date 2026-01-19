<?php

namespace App\Services\MarketData;

use App\Repositories\TickerRepository;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\MarketData\CanonicalEodRepository;
use App\Repositories\MarketData\RunRepository;
use App\Repositories\MarketData\RawEodRepository;

use App\Trade\MarketData\Config\ImportPolicy;
use App\Trade\MarketData\Config\QualityRules;
use App\Trade\MarketData\Config\ProviderPriority;

use App\Trade\MarketData\Normalize\EodBarNormalizer;
use App\Trade\MarketData\Providers\Contracts\EodProvider;
use App\Trade\MarketData\Select\CanonicalSelector;
use App\Trade\MarketData\Validate\EodQualityGate;

use App\Trade\Support\TradeClock;
use Illuminate\Support\Facades\Log;

final class ImportEodService
{
    /** @var ImportPolicy */
    private $policy;

    /** @var QualityRules */
    private $rules;


    /** @var TickerRepository */
    private $tickers;

    /** @var MarketCalendarRepository */
    private $calendar;

    /** @var RunRepository */
    private $runs;

    /** @var RawEodRepository */
    private $rawRepo;

    /** @var CanonicalEodRepository */
    private $canRepo;

    /** @var array<string,EodProvider> */
    private $providersByName;

    /** @var EodBarNormalizer */
    private $normalizer;

    /** @var EodQualityGate */
    private $gate;

    /** @var CanonicalSelector */
    private $selector;

    /** @var DisagreementMajorService */
    private $disagreeSvc;

    /** @var MissingTradingDayService */
    private $missingSvc;

    /** @var SoftQualityRulesService */
    private $softQualitySvc;


    public function __construct(
        ImportPolicy $policy,
        QualityRules $rules,
        ProviderPriority $priority,
        TickerRepository $tickers,
        MarketCalendarRepository $calendar,
        RunRepository $runs,
        RawEodRepository $rawRepo,
        CanonicalEodRepository $canRepo,
        DisagreementMajorService $disagreeSvc,
        MissingTradingDayService $missingSvc,
        SoftQualityRulesService $softQualitySvc,
        array $providersByName // bind this in ServiceProvider: ['yahoo' => YahooEodProvider]
    ) {
        $this->policy = $policy;
        $this->rules = $rules;
        $this->tickers = $tickers;
        $this->calendar = $calendar;
        $this->runs = $runs;
        $this->rawRepo = $rawRepo;
        $this->canRepo = $canRepo;
        $this->disagreeSvc = $disagreeSvc;
        $this->missingSvc = $missingSvc;
        $this->softQualitySvc = $softQualitySvc;


        $this->providersByName = [];
        foreach ($providersByName as $k => $p) {
            if ($p instanceof EodProvider) $this->providersByName[strtolower((string)$k)] = $p;
        }

        $this->normalizer = new EodBarNormalizer(TradeClock::tz());
        $this->gate = new EodQualityGate($rules);
        $this->selector = new CanonicalSelector($priority);
    }

    /**
     * @return array summary
     */
    public function run(?string $date, ?string $from, ?string $to, ?string $tickerCode, int $chunkSize = 200): array
    {
        $today = TradeClock::today();
        $beforeCutoff = TradeClock::isBeforeEodCutoff();
        $tz = TradeClock::tz();

        // Resolve effective end date by cutoff + trading day
        $effectiveEnd = $this->resolveEffectiveEndDate($today, $beforeCutoff);

        if ($date) {
            $fromEff = $date;
            $toEff = $date;
        } else {
            $toEff = $to ?: $effectiveEnd;
            $fromEff = $from ?: $this->calendar->lookbackStartDate($toEff, $this->policy->lookbackTradingDays());
        }

        // If asked range extends beyond effective end, clamp to effective end.
        if ($toEff > $effectiveEnd) $toEff = $effectiveEnd;

        $tradeDates = $this->calendar->tradingDatesBetween($fromEff, $toEff);
        $tradeDatesSet = array_fill_keys($tradeDates, true);
        $targetDays = count($tradeDates);

        $tickerRows = $this->tickers->listActive($tickerCode);
        $targetTickers = count($tickerRows);

        $runId = $this->runs->createRun([
            'job' => 'import_eod',
            'timezone' => $tz,
            'cutoff' => TradeClock::eodCutoff(),
            'effective_start_date' => $fromEff,
            'effective_end_date' => $toEff,
            'target_tickers' => $targetTickers,
            'target_days' => $targetDays,
            'status' => 'RUNNING',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($targetDays === 0) {
            $notes = ['no_trading_days_in_range'];

            $this->runs->finishRun($runId, [
                'status' => 'FAILED',
                'coverage_pct' => 0,
                'fallback_pct' => 0,
                'hard_rejects' => 0,
                'soft_flags' => 0,
                'disagree_major' => 0,
                'missing_trading_day' => 0,
                'notes' => implode(' | ', $notes),
            ]);

            return [
                'run_id' => $runId,
                'status' => 'FAILED',
                'effective_start' => $fromEff,
                'effective_end' => $toEff,
                'target_tickers' => $targetTickers,
                'target_days' => 0,
                'expected_points' => 0,
                'canonical_points' => 0,
                'coverage_pct' => 0,
                'fallback_pct' => 0,
                'hard_rejects' => 0,
                'soft_flags' => 0,
                'notes' => $notes,
            ];
        }

        // Metrics
        $hardRejects = 0;
        $softFlags = 0;
        $softFlagsGate = 0;
        $fallbackPicks = 0;
        $totalPicks = 0;
        $provErr = []; // ex: ['yahoo' => 12]
        $provErrCode = []; // ex: ['yahoo' => ['NET_ERROR'=>5,'HTTP_502'=>7]]

        // Guardrail: avoid log spam. Keep only few samples per provider/run.
        $provErrSample = []; // ex: ['yahoo' => 3]
        $hardRejectSample = 0;

        // Buffer rows for batch insert/upsert
        $rawRowsBuf = [];
        $canRowsBuf = [];

        $batchInsert = 2000;
        $batchUpsert = 2000;

        $tickerChunks = array_chunk($tickerRows, max(1, $chunkSize));

        // perf: fetch per-provider in parallel per chunk (still 1 req per symbol, but concurrent)
        $poolConc = max(1, (int) config('trade.perf.http_pool', 15));

        foreach ($tickerChunks as $chunk) {
            // Prefetch results for this chunk to avoid serial HTTP per ticker.
            // prefetch[source] = ['symByTid' => [tid=>symbol], 'resBySym' => [symbol=>ProviderFetchResult]]
            $prefetch = [];

            foreach ($this->providersByName as $src => $provider) {
                $symByTid = [];
                $symbols = [];

                foreach ($chunk as $t) {
                    $tid = (int) $t['ticker_id'];
                    $tcode = (string) $t['ticker_code'];
                    $symbol = $provider->mapTickerCodeToSymbol($tcode);
                    $symByTid[$tid] = $symbol;
                    if ($symbol !== '') $symbols[] = $symbol;
                }

                $symbols = array_values(array_unique($symbols));

                // provider may implement fetchMany(...) for concurrency.
                if (method_exists($provider, 'fetchMany')) {
                    $resBySym = $provider->fetchMany($symbols, $fromEff, $toEff, $poolConc);
                } else {
                    $resBySym = [];
                    foreach ($symbols as $symbol) {
                        $resBySym[$symbol] = $provider->fetch($symbol, $fromEff, $toEff);
                    }
                }

                $prefetch[$src] = [
                    'symByTid' => $symByTid,
                    'resBySym' => $resBySym,
                ];
            }

            foreach ($chunk as $t) {
                $tid = (int) $t['ticker_id'];
                $tcode = (string) $t['ticker_code'];

                // Build candidates per date per source
                // candidates[tradeDate][source] = {bar, val}
                $candidates = [];

                foreach ($this->providersByName as $src => $provider) {
                    $symbol = $prefetch[$src]['symByTid'][$tid] ?? $provider->mapTickerCodeToSymbol($tcode);
                    $res = $prefetch[$src]['resBySym'][$symbol] ?? $provider->fetch($symbol, $fromEff, $toEff);

                    if ($res->errorCode) {
                        $provErr[$src] = ($provErr[$src] ?? 0) + 1;

                        $code = (string) $res->errorCode;
                        if ($code === '') $code = 'UNKNOWN';
                        if (!isset($provErrCode[$src])) $provErrCode[$src] = [];
                        $provErrCode[$src][$code] = ($provErrCode[$src][$code] ?? 0) + 1;

                        // Log few samples only
                        $provErrSample[$src] = ($provErrSample[$src] ?? 0) + 1;
                        if ($provErrSample[$src] <= 3) {
                            Log::channel('market_data')->warning('md.import.provider_error', [
                                'run_id' => $runId,
                                'source' => $src,
                                'ticker_code' => $tcode,
                                'symbol' => $symbol,
                                'from' => $fromEff,
                                'to' => $toEff,
                                'error_code' => $code,
                                'error_msg' => $res->errorMsg,
                            ]);
                        }

                        continue;
                    }
                
                    // Map normalized bars by tradeDate
                    $barsByDate = [];
                    foreach ($res->bars as $rawBar) {
                        $norm = $this->normalizer->normalize($rawBar);
                        if (!$norm) continue;
                        if (!isset($tradeDatesSet[$norm->tradeDate])) continue; // skip non-trading

                        $barsByDate[$norm->tradeDate] = $norm;
                    }

                    foreach ($barsByDate as $d => $norm) {
                        $val = $this->gate->validate($norm);

                        if (!$val->hardValid) {
                            $hardRejects++;

                            // Sample few hard rejects for audit
                            $hardRejectSample++;
                            if ($hardRejectSample <= 10) {
                                Log::channel('market_data')->info('md.import.hard_reject', [
                                    'run_id' => $runId,
                                    'source' => $src,
                                    'ticker_code' => $tcode,
                                    'trade_date' => $d,
                                    'close' => $norm->close,
                                    'volume' => $norm->volume,
                                    'error_code' => $val->errorCode,
                                    'flags' => $val->flags,
                                ]);
                            }
                        }
                        if ($val->flags) $softFlags += count($val->flags);

                        $candidates[$d][$src] = ['bar' => $norm, 'val' => $val];

                        $rawRowsBuf[] = [
                            'run_id' => $runId,
                            'ticker_id' => $tid,
                            'trade_date' => $d,
                            'source' => $src,
                            'source_symbol' => $symbol,
                            'source_ts' => null,
                            'open' => $norm->open,
                            'high' => $norm->high,
                            'low' => $norm->low,
                            'close' => $norm->close,
                            'adj_close' => $norm->adjClose,
                            'volume' => $norm->volume,
                            'hard_valid' => $val->hardValid ? 1 : 0,
                            'flags' => $val->flags ? implode(',', $val->flags) : null,
                            'error_code' => $val->errorCode,
                            'error_msg' => $val->errorMsg,
                            'imported_at' => now(),
                        ];

                        if (count($rawRowsBuf) >= $batchInsert) {
                            $this->rawRepo->insertMany($rawRowsBuf, $batchInsert);
                            $rawRowsBuf = [];
                        }
                    }
                }

                // Select canonical per trading date
                foreach ($tradeDates as $d) {
                    $bySource = $candidates[$d] ?? [];
                    $pick = $this->selector->select($d, $bySource);
                    if (!$pick) continue;

                    // Cutoff hard rule: never canonicalize today before cutoff
                    if ($beforeCutoff && $d === $today) {
                        // treat as reject canonical
                        continue;
                    }

                    $totalPicks++;
                    if ($pick->reason === 'FALLBACK_USED') $fallbackPicks++;

                    $canRowsBuf[] = [
                        'run_id' => $runId,
                        'ticker_id' => $tid,
                        'trade_date' => $d,
                        'chosen_source' => $pick->chosenSource,
                        'reason' => $pick->reason,
                        'flags' => $pick->flags ? implode(',', $pick->flags) : null,
                        'open' => $pick->bar->open,
                        'high' => $pick->bar->high,
                        'low' => $pick->bar->low,
                        'close' => $pick->bar->close,
                        'adj_close' => $pick->bar->adjClose,
                        'volume' => $pick->bar->volume,
                        'built_at' => now(),
                    ];

                    if (count($canRowsBuf) >= $batchUpsert) {
                        $this->canRepo->upsertMany($canRowsBuf, $batchUpsert);
                        $canRowsBuf = [];
                    }
                }
            }
        }

        // Flush remaining RAW
        if ($rawRowsBuf) {
            $this->rawRepo->insertMany($rawRowsBuf, $batchInsert);
            $rawRowsBuf = [];
        }

        // Gating: coverage
        $expected = $targetTickers * $targetDays;
        $coveragePct = $expected > 0 ? ($totalPicks * 100.0 / $expected) : 0.0;
        $fallbackPct = $totalPicks > 0 ? ($fallbackPicks * 100.0 / $totalPicks) : 0.0;

        $status = 'SUCCESS';
        $notes = [];

        if ($coveragePct < $this->policy->coverageMinPct()) {
            $status = 'CANONICAL_HELD';
            $notes[] = 'Coverage below threshold: ' . number_format($coveragePct, 2) .
                '% < ' . number_format($this->policy->coverageMinPct(), 2) . '%';
        }

        if ($provErr) {
            $parts = [];

            foreach ($provErr as $src => $cnt) {
                $detail = '';

                if (!empty($provErrCode[$src])) {
                    arsort($provErrCode[$src]); // terbesar dulu
                    $top = array_slice($provErrCode[$src], 0, 3, true); // top 3 aja biar nggak spam
                    $pairs = [];
                    foreach ($top as $code => $n) $pairs[] = $code . '=' . $n;
                    $detail = ' (' . implode(',', $pairs) . ')';
                }

                $parts[] = $src . '=' . $cnt . $detail;
            }

            $notes[] = 'provider_errors: ' . implode(',', $parts);
        }

        // Flush remaining CANONICAL (kalau ada sisa buffer)
        if ($status === 'SUCCESS' && $canRowsBuf) {
            $this->canRepo->upsertMany($canRowsBuf, $batchUpsert);
            $canRowsBuf = [];
        }
        
        // Phase 2: Disagreement Major (multi-source)
        $disagreeMajor = 0;
        if ($status === 'SUCCESS') {
            $canonicalPoints = $this->canRepo->countByRunId($runId);
            $dg = $this->disagreeSvc->compute($runId, $canonicalPoints, $this->rules->disagreeMajorRatio(), 10);

            $disagreeMajor = (int) ($dg['disagree_major'] ?? 0);
            $ratio = (float) ($dg['disagree_major_ratio'] ?? 0.0);

            if ($disagreeMajor > 0) {
                $notes[] = 'disagree_major=' . $disagreeMajor;
                $notes[] = 'disagree_thr=' . number_format($this->rules->disagreeMajorPct(), 2) . '%';
                $notes[] = 'disagree_ratio=' . number_format($ratio * 100.0, 2) . '%';

                // Add a few samples to help investigation (max 3 in notes to avoid spam)
                $samples = is_array($dg['samples'] ?? null) ? $dg['samples'] : [];
                $take = array_slice($samples, 0, 3);
                if ($take) {
                    $parts = [];
                    foreach ($take as $s) {
                        $parts[] =
                            ((int) ($s['ticker_id'] ?? 0)) . '@' . ((string) ($s['trade_date'] ?? '')) .
                            '=' . number_format(((float) ($s['pct'] ?? 0.0)) * 100.0, 2) . '%';
                    }
                    $notes[] = 'disagree_samples=' . implode(',', $parts);
                }

                $shouldHold = ($ratio >= 0.01) || ($disagreeMajor >= 20);
                if ($shouldHold) {
                    $status = 'CANONICAL_HELD';
                    $notes[] = 'held_reason=disagree_major';
                    $notes[] = 'held_rule=ratio>=1% OR count>=20';
                }
            }
        }

        $missingTradingDay = 0;

        if ($status === 'SUCCESS') {
            $mt = $this->missingSvc->compute(
                $runId,
                $fromEff,
                $toEff,
                $targetTickers,
                0.60, // 60% minimal per day
                5
            );

            $missingTradingDay = (int) ($mt['missing_days'] ?? 0);

            if ($missingTradingDay > 0) {
                $notes[] = 'missing_trading_day=' . $missingTradingDay;
                $notes[] = 'missing_dates=' . implode(',', array_slice((array)($mt['missing_dates'] ?? []), 0, 5));
                $status = 'CANONICAL_HELD';
                $notes[] = 'held_reason=missing_trading_day';
            } else {
                $lowDays = (int) ($mt['low_coverage_days'] ?? 0);
                if ($lowDays >= 2) {
                    $notes[] = 'low_coverage_days=' . $lowDays;
                    $status = 'CANONICAL_HELD';
                    $notes[] = 'held_reason=low_coverage_days';
                }
            }
        }

        if ($status === 'SUCCESS') {
            $sq = $this->softQualitySvc->evaluate($runId, $fromEff, $toEff, $targetTickers, $this->rules->gapExtremeRatio());

            $softFlagsGate = (int) ($sq['soft_flags'] ?? 0);
            if ($softFlagsGate > 0) {
                $notes[] = 'soft_flags_phase4=' . $softFlagsGate;
                foreach ($sq['rule_counts'] as $k => $v) {
                    if ($v > 0) $notes[] = "soft_{$k}={$v}";
                }
                foreach ($sq['samples'] as $k => $s) {
                    $notes[] = "soft_sample_{$k}={$s}";
                }
            }

            if (!empty($sq['hold'])) {
                $status = 'CANONICAL_HELD';
                $notes[] = 'held_reason=soft_quality';
                $notes[] = 'soft_hold_reason=' . ($sq['hold_reason'] ?? 'unknown');
            }
        }


        // Kalau HELD -> hapus semua canonical yg terlanjur ke-upsert di batch sebelumnya
        if ($status === 'CANONICAL_HELD') {
            $this->canRepo->deleteByRunId($runId);
            $notes[] = 'held_deleted_canonical: run_id=' . $runId;
        }

        $softFlagsTotal = (int) ($softFlags + $softFlagsGate);

        $this->runs->finishRun($runId, [
            'status' => $status,
            'coverage_pct' => round($coveragePct, 2),
            'fallback_pct' => round($fallbackPct, 2),
            'hard_rejects' => (int) $hardRejects,
            'soft_flags' => $softFlagsTotal,
            'disagree_major' => (int) $disagreeMajor,
            'missing_trading_day' => (int) $missingTradingDay,
            'notes' => $notes ? implode(' | ', $notes) : null,
        ]);

        return [
            'run_id' => $runId,
            'status' => $status,
            'effective_start' => $fromEff,
            'effective_end' => $toEff,
            'target_tickers' => $targetTickers,
            'target_days' => $targetDays,
            'expected_points' => $expected,
            'canonical_points' => $totalPicks,
            'coverage_pct' => round($coveragePct, 2),
            'fallback_pct' => round($fallbackPct, 2),
            'hard_rejects' => (int) $hardRejects,
            'soft_flags' => $softFlagsTotal,
            'notes' => $notes,
        ];
    }

    private function resolveEffectiveEndDate(string $today, bool $beforeCutoff): string
    {
        // MARKET_DATA.md: before cutoff => end_date = previous trading day
        if ($beforeCutoff) {
            return $this->calendar->previousTradingDate($today) ?: $today;
        }

        // after cutoff => if today trading day, allow today, else previous trading day
        if ($this->calendar->isTradingDay($today)) return $today;
        return $this->calendar->previousTradingDate($today) ?: $today;
    }
}
