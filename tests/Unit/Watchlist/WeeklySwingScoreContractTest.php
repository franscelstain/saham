<?php

namespace Tests\Unit\Watchlist;

use App\DTO\Watchlist\CandidateInput;
use App\DTO\Watchlist\PolicyDocCheckResult;
use App\Repositories\DividendEventRepository;
use App\Repositories\IntradaySnapshotRepository;
use App\Repositories\MarketBreadthRepository;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\TickerStatusRepository;
use App\Repositories\WatchlistRepository;
use App\Trade\Pricing\FeeConfig;
use App\Trade\Pricing\TickLadderConfig;
use App\Trade\Pricing\TickRule;
use App\Trade\Support\TradeClockConfig;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\Trade\Watchlist\Config\WatchlistPolicyConfig;
use App\Trade\Watchlist\Contracts\PolicyDocLocator;
use App\Trade\Watchlist\Contracts\PreopenContractValidator;
use App\Trade\Watchlist\Policies\WeeklySwingPolicy;
use App\Trade\Watchlist\WatchlistEngine;
use Tests\TestCase;

class WeeklySwingScoreContractTest extends TestCase
{
    public function testWeeklySwingPolicyScoringMatchesDocsWeightsAndClamps(): void
    {
        $candidate = $this->makeCandidateGood();
        $engine = $this->makeEngineWithCandidate($candidate, true);

        $policy = new WeeklySwingPolicy();
        // Calibrated weights (WS param_id=4) must be sourced from config/env, not hardcoded.
        $policyMeta = [
            'weights' => [
                'w_trend' => 0.25,
                'w_momentum' => 0.20,
                'w_volume' => 0.20,
                'w_breakout' => 0.25,
                'w_risk' => 0.10,
            ],
        ];

        $res = $policy->apply($this->candidateToPolicyInput($candidate), $policyMeta, $engine);

        $this->assertFalse((bool)($res['drop'] ?? true));
        $this->assertArrayHasKey('score_total', $res);

        // Expected score based on docs/watchlist/policy/weekly_swing.md (weights + clamp):
        // s_pattern=1.0 (signal_code=5)
        // s_trend from close_vs_ma20=(1020/990)-1
        // s_momentum from roc20=0.10
        // s_volume from vol_ratio=2.0
        // s_risk: invStop (inverse normalized stop_pct) and invAtr (atr_pct=atr14/close)
        $close = 1020.0;
        $ma20 = 990.0;
        $closeVsMa20 = ($close / $ma20) - 1.0;
        $sTrend = $this->clamp01(($closeVsMa20 + 0.02) / (0.05 - (-0.02)));
        $sMomentum = $this->clamp01((0.10 - (-0.03)) / (0.12 - (-0.03)));
        $sVolume = $this->clamp01((2.0 - 1.0) / (3.0 - 1.0));

        // stop_pct = (entry - sl) / entry. This fixture is built so TP1 RR passes rounding.
        // entry=1020, sl=970 => stop_pct=50/1020
        $stopPct = 50.0 / 1020.0;
        $invStop = 1.0 - $this->clamp01(($stopPct - 0.01) / (0.08 - 0.01));

        $atrPct = 31.0 / 1020.0;
        $invAtr = 1.0 - $this->clamp01(($atrPct - 0.01) / (0.12 - 0.01));
        $sRisk = $this->clamp01(0.5 * $invStop + 0.5 * $invAtr);

        // Policy scoring (calibrated): risk is a penalty, subtract (1 - sRisk)
        $wTrend = 0.25; $wMom = 0.20; $wVol = 0.20; $wBreak = 0.25; $wRisk = 0.10;
        $sumW = $wTrend + $wMom + $wVol + $wBreak + $wRisk;
        $wnTrend = $wTrend / $sumW;
        $wnMom   = $wMom / $sumW;
        $wnVol   = $wVol / $sumW;
        $wnBreak = $wBreak / $sumW;
        $wnRisk  = $wRisk / $sumW;

        $expected = $this->clamp01(
            ($wnBreak * 1.0) +
            ($wnTrend * $sTrend) +
            ($wnMom * $sMomentum) +
            ($wnVol * $sVolume) -
            ($wnRisk * (1.0 - $sRisk))
        );

        $this->assertEqualsWithDelta($expected, (float)$res['score_total'], 0.02);
        $this->assertEqualsWithDelta($expected * 100.0, (float)($res['score'] ?? 0.0), 2.0);
    }

    public function testCandidateToPolicyInputMustCarryClassifierFields(): void
    {
        $candidate = $this->makeCandidateGood();
        $in = $this->candidateToPolicyInput($candidate);

        // If these go missing, policy scoring will silently drift (e.g., s_pattern falls back to default).
        $this->assertArrayHasKey('decision_code', $in);
        $this->assertArrayHasKey('signal_code', $in);
        $this->assertArrayHasKey('volume_label_code', $in);

        $this->assertSame($candidate->decisionCode(), $in['decision_code']);
        $this->assertSame($candidate->signalCode(), $in['signal_code']);
        $this->assertSame($candidate->volumeLabelCode(), $in['volume_label_code']);
    }

    public function testHardRuleFailStaysInPreopenAsAvoidNotExcluded(): void
    {
        $candidate = $this->makeCandidateHardFailDv20();
        $engine = $this->makeEngineWithCandidate($candidate, true);

        $doc = $engine->buildPreopen([
            'policy' => 'WEEKLY_SWING',
            'eod_date' => '2026-01-30',
            'capital_idr' => null,
            'now_ts' => '2026-02-03T08:00:00+07:00',
        ]);

        (new PreopenContractValidator())->validate($doc);

        $groups = (array)($doc['groups'] ?? []);
        $foundGroup = null;
        $foundItem = null;

        foreach ($groups as $groupName => $items) {
            foreach ((array)$items as $it) {
                if (($it['ticker'] ?? null) === 'BBCA') {
                    $foundGroup = (string)$groupName;
                    $foundItem = (array)$it;
                    break 2;
                }
            }
        }

        $this->assertNotNull($foundGroup, 'BBCA should remain in preopen payload groups');
        $this->assertSame('avoid', $foundGroup, 'BBCA hard-rule fail must land in avoid group');

        $reasons = (array)($foundItem['reasons'] ?? []);
        $codes = array_map(fn($r) => (string)($r['code'] ?? ''), $reasons);
        $this->assertContains('WS_MIN_DV20_IDR', $codes);
    }

    public function testRrTooLowIsWatchOnlyNotAvoid(): void
    {
        $candidate = $this->makeCandidateHardFailRr();
        $engine = $this->makeEngineWithCandidate($candidate, true);

        $doc = $engine->buildPreopen([
            'policy' => 'WEEKLY_SWING',
            'eod_date' => '2026-01-30',
            'capital_idr' => null,
            'now_ts' => '2026-02-03T08:00:00+07:00',
        ]);

        (new PreopenContractValidator())->validate($doc);

        $foundGroup = null;
        foreach ((array)($doc['groups'] ?? []) as $groupName => $items) {
            foreach ((array)$items as $it) {
                if (($it['ticker'] ?? null) === 'BBCA') { $foundGroup = (string)$groupName; break 2; }
            }
        }

        $this->assertNotNull($foundGroup, 'BBCA should remain in preopen payload groups');
        $this->assertSame('watch_only', $foundGroup, 'RR too low must land in watch_only (monitoring), not avoid');
    }

    public function testTrendGateFailIsWatchOnlyNotAvoid(): void
    {
        $candidate = $this->makeCandidateTrendGateFail();
        $engine = $this->makeEngineWithCandidate($candidate, true);

        $doc = $engine->buildPreopen([
            'policy' => 'WEEKLY_SWING',
            'eod_date' => '2026-01-30',
            'capital_idr' => null,
            'now_ts' => '2026-02-03T08:00:00+07:00',
        ]);

        (new PreopenContractValidator())->validate($doc);

        $foundGroup = null;
        foreach ((array)($doc['groups'] ?? []) as $groupName => $items) {
            foreach ((array)$items as $it) {
                if (($it['ticker'] ?? null) === 'BBCA') { $foundGroup = (string)$groupName; break 2; }
            }
        }

        $this->assertNotNull($foundGroup, 'BBCA should remain in preopen payload groups');
        $this->assertSame('watch_only', $foundGroup, 'Trend gate fail must land in watch_only (monitoring), not avoid');
    }

    private function clamp01(float $v): float
    {
        if ($v < 0.0) return 0.0;
        if ($v > 1.0) return 1.0;
        return $v;
    }

    private function makeCandidateGood(array $overrides = []): CandidateInput
    {
        $data = [
            'signal_code' => 5,
            'decision_code' => 5,
            'volume_label_code' => 4,
            'ticker_id' => 1,
            'ticker_code' => 'BBCA',
            'open' => 1000,
            'high' => 1050,
            'low' => 980,
            'close' => 1020,
            'volume' => 1000000,
            'prev_close' => 1000,
            // Universe gate requires a numeric score_total from repo output.
            'score_total' => 0.60,
            'ma20' => 990,
            'ma50' => 980,
            'ma200' => 900,
            'rsi14' => 55,
            'atr14' => 31,
            'vol_ratio' => 2.0,
            'hh20' => 1050,
            // ll5/low set so policy stop rounds to 970 (R=50), TP1 RR meets WS_MIN_RR after rounding
            'll5' => 975,
            'roc20' => 0.10,
            // Canonical liquidity field is dv20_idr (average daily traded value 20d in IDR)
            'dv20_idr' => 50_000_000_000,
            // Keep dv20 for backward compatibility in some code paths.
            'dv20' => 50_000_000_000,
        ];

        foreach ($overrides as $k => $v) {
            $data[$k] = $v;
        }

        return new CandidateInput($data);
    }

    private function makeCandidateHardFailDv20(): CandidateInput
    {
        // Must pass Universe liquidity gate (default 2B) but fail WEEKLY_SWING policy gate (5B)
        return $this->makeCandidateGood([
            'dv20_idr' => 3_000_000_000,
            'dv20' => 3_000_000_000,
        ]);
    }



    private function makeCandidateHardFailRr(): CandidateInput
    {
        // Make RR too low: widen R by pushing ll5 lower so stop gets much lower.
        return $this->makeCandidateGood(['ll5' => 900]);
    }


    private function makeCandidateTrendGateFail(): CandidateInput
    {
        // Force close < ma20 and ma20 < ma50 so both condA/condB fail, and keep hh20 so resistance20 exists.
        return $this->makeCandidateGood([
            'ma20' => 1100,
            'ma50' => 1200,
            'hh20' => 1300,
        ]);
    }


    private function candidateToPolicyInput(CandidateInput $c): array
    {
        // Mimic the core policy input built by WatchlistEngine::buildCandidate()
        return [
            'ticker_id' => $c->tickerId(),
            'ticker_code' => $c->tickerCode(),
            // ensure policy gets the same pattern/signal classifiers that engine provides
            'decision_code' => $c->decisionCode(),
            'signal_code' => $c->signalCode(),
            'volume_label_code' => $c->volumeLabelCode(),
            'open' => $c->open(),
            'high' => $c->high(),
            'low' => $c->low(),
            'close' => $c->close(),
            'ma20' => $c->ma20(),
            'ma50' => $c->ma50(),
            'ma200' => $c->ma200(),
            'atr14' => $c->atr14(),
            'atr_pct' => ($c->atr14() !== null && (float)$c->close() > 0) ? ((float)$c->atr14() / (float)$c->close()) : null,
            'vol_ratio' => $c->volRatio(),
            'dv20' => $c->dv20(),

            'hh20' => $c->hh20(),
            'll5' => $c->ll5(),
            'roc20' => $c->roc20(),
            'setup_type' => 'Base',
        ];
    }

    private function makeEngineWithCandidate(CandidateInput $candidate, bool $canonicalReady): WatchlistEngine
    {
        $cfg = new WatchlistPolicyConfig(
            'WEEKLY_SWING',
            null,
            false,
            [],
            2,
            95.0,
            95.0,
            false,
            15.0,
            8.0,
            0.02,
            0.02,
            0.75,
            true,
            [
                'WEEKLY_SWING' => [
                    'max_gap_up_pct' => 0.03,
                    'max_chase_from_close_pct' => 0.02,
                    'max_spread_pct' => 0.015,
                ],
            ],
            5,
            [2,3,4,5,6,7,8,9,10],
            ['WS_MIN_DV20_IDR','WS_MAX_TICK_PCT','WS_MIN_ATR_PCT'],
            [
                'option' => 'FIXED',
                'toppick_max' => 10,
                'secondary_max' => 10,
                'watch_only_max' => 50,
                'top_pick_max' => 10,
                // Calibrated thresholds (WS param_id=4)
                'toppick_min_score' => 0.66,
                'toppick_score_gap' => 0.08,
                'secondary_min_score' => 0.50,
                'watch_only_min_score' => 0.50,
            ],
            [
                'min_price' => 50,
                // Calibrated universe guards (WS param_id=4)
                'min_dv20_idr' => 3000000000,
                'min_turnover20_idr' => 3000000000,
                'max_atr_pct_universe' => 0.09,
                'min_vol_ratio_universe' => 1.05,
            ]
        );

        $tickRule = new TickRule(new TickLadderConfig([
            ['lt' => 200, 'tick' => 1],
            ['lt' => 500, 'tick' => 2],
            ['lt' => 2000, 'tick' => 5],
            ['lt' => 5000, 'tick' => 10],
            ['lt' => 20000, 'tick' => 25],
            ['lt' => 50000, 'tick' => 50],
            ['tick' => 100],
        ]));

        $feeCfg = new FeeConfig(0.0015, 0.0025, 0.0, 0.0, 0.0005);
        $clockCfg = new TradeClockConfig('Asia/Jakarta', 16, 0);
        $scorecardCfg = new ScorecardConfig(false, 0.01, 0.015, 0.004, '09:00', '15:50');
        $watchRepo = new class($candidate, $canonicalReady) extends WatchlistRepository {
            private CandidateInput $c;
            private bool $ok;
            public function __construct(CandidateInput $c, bool $ok) { $this->c = $c; $this->ok = $ok; }
            public function getLatestCommonEodDate(): ?string { return '2026-01-30'; }
            public function getEodCandidates(string $tradeDate): array { return [$this->c->toArray()]; }
            public function coverageSnapshot(string $tradeDate): array
            {
                if ($this->ok) {
                    return ['canonical_coverage_pct' => 100.0, 'indicators_coverage_pct' => 100.0];
                }
                return ['canonical_coverage_pct' => 10.0, 'indicators_coverage_pct' => 10.0];
            }
        };

        $breadthRepo = new class extends MarketBreadthRepository {
            public function snapshot(string $tradeDate): array { return ['trade_date' => $tradeDate, 'sample_size' => 0]; }
        };

        $calRepo = new class extends MarketCalendarRepository {
            public function prevTradingDay(string $date): ?string { return $date; }
            public function nextTradingDay(string $date): ?string { return $date; }
            public function isTradingDay(string $date): bool { return true; }
            public function tradingDatesBetween(string $from, string $to): array { return [$from, $to]; }
            public function getCalendarRow(string $date): ?array
            {
                return [
                    'trade_date' => $date,
                    'session_open_time' => '09:00:00',
                    'session_close_time' => '16:00:00',
                    'breaks_json' => json_encode([]),
                ];
            }
        };

        $divRepo = new class extends DividendEventRepository {
            public function eventsByTickerInWindow(string $from, string $to): array { return []; }
        };

        $intraRepo = new class extends IntradaySnapshotRepository {
            public function snapshotsByTicker(string $tradeDate): array { return []; }
        };

        $statusRepo = new class extends TickerStatusRepository {
            public function statusByTickerAsOf(string $tradeDate): array
            {
                return [
                    1 => [
                        'special_notations' => [],
                        'is_suspended' => false,
                        'status_quality' => 'OK',
                        'status_asof_trade_date' => $tradeDate,
                        'trading_mechanism' => 'REGULAR',
                    ],
                ];
            }
        };

        $posRepo = new class extends PortfolioPositionRepository {
            public function openPositionsByTicker(int $accountId = 1): array { return []; }
        };

        $policyDocs = new class implements PolicyDocLocator {
            public function check(string $policyCode): PolicyDocCheckResult
            {
                return PolicyDocCheckResult::ok($policyCode, null, null);
            }
        };

        $rc = new \ReflectionClass(WatchlistEngine::class);
        $ctor = $rc->getConstructor();
        $args = [];
        foreach (($ctor ? $ctor->getParameters() : []) as $p) {
            $t = (string)($p->getType() ? $p->getType()->getName() : '');
            switch ($t) {
                case WatchlistRepository::class: $args[] = $watchRepo; break;
                case MarketCalendarRepository::class: $args[] = $calRepo; break;
                case DividendEventRepository::class: $args[] = $divRepo; break;
                case IntradaySnapshotRepository::class: $args[] = $intraRepo; break;
                case TickerStatusRepository::class: $args[] = $statusRepo; break;
                case PortfolioPositionRepository::class: $args[] = $posRepo; break;
                case MarketBreadthRepository::class: $args[] = $breadthRepo; break;
                case TickRule::class: $args[] = $tickRule; break;
                case FeeConfig::class: $args[] = $feeCfg; break;
                case TradeClockConfig::class: $args[] = $clockCfg; break;
                case ScorecardConfig::class: $args[] = $scorecardCfg; break;
                case WatchlistPolicyConfig::class: $args[] = $cfg; break;
                case PolicyDocLocator::class: $args[] = $policyDocs; break;
                default:
                    $args[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
                    break;
            }
        }

        /** @var WatchlistEngine $engine */
        $engine = $rc->newInstanceArgs($args);
        return $engine;
}
}