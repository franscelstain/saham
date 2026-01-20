<?php

namespace App\Trade\Ranking;

use App\Trade\Explain\ReasonCatalog;

/**
 * WatchlistRanker v3
 *
 * Target output: score 0..100 yang deterministik dan bisa dijelaskan.
 * Komponen (maks):
 *  - Trend (30)
 *  - Momentum (20)
 *  - Volume & Liquidity (20)
 *  - Risk Quality (20)
 *  - Market Alignment (10)
 */
class WatchlistRanker
{
    private bool $enabled;
    private float $rrMin;
    private array $opts;

    public function __construct(bool $enabled, float $rrMin, array $opts = [])
    {
        $this->enabled = $enabled;
        $this->rrMin = $rrMin;
        $this->opts = $opts;
    }

    /**
     * @return array{score:float,codes:array<int,string>,reasons:array<int,array>,breakdown:array<string,float>}
     */
    public function rank(array $row, string $marketRegime = 'neutral'): array
    {
        if (!$this->enabled) {
            return [
                'score' => 0.0,
                'breakdown' => [
                    'trend' => 0.0,
                    'momentum' => 0.0,
                    'volume' => 0.0,
                    'risk' => 0.0,
                    'market' => 0.0,
                ],
                'reasons' => [
                    [
                        'code' => 'RANKING_DISABLED',
                        'message' => ReasonCatalog::rankReasonMessage('RANKING_DISABLED', []),
                        'severity' => 'warn',
                        'points' => 0.0,
                    ]
                ],
                'codes' => ['RANKING_DISABLED'],
            ];
        }

        $debug = (bool)($this->opts['debug_reasons'] ?? false);

        $codes = [];
        $reasons = [];

        $close = $this->f($row['close'] ?? null);
        $ma20  = $this->f($row['ma20'] ?? null);
        $ma50  = $this->f($row['ma50'] ?? null);
        $ma200 = $this->f($row['ma200'] ?? null);
        $rsi   = $this->f($row['rsi'] ?? ($row['rsi14'] ?? null));
        $volRatio = $this->f($row['vol_ratio'] ?? null);
        $valueEst = $this->f($row['valueEst'] ?? ($row['value_est'] ?? null));
        $atrPct   = $this->f($row['atr_pct'] ?? null);
        $gapPct   = $this->f($row['gap_pct'] ?? null);
        $rangePct = $this->f($row['range_pct'] ?? null);

        $decisionCode = (int)($row['decisionCode'] ?? ($row['decision_code'] ?? 0));
        $signalCode   = (int)($row['signalCode'] ?? ($row['signal_code'] ?? 0));
        $volLabelCode = (int)($row['volumeLabelCode'] ?? ($row['volume_label_code'] ?? 0));

        $expiryStatus = (string)($row['expiryStatus'] ?? ($row['expiry_status'] ?? 'N/A'));
        $plan = (array)($row['plan'] ?? []);
        $rrTp2 = $this->f($plan['rrTp2'] ?? null);
        $planErrors = (array)($plan['errors'] ?? []);

        // ---------------- Trend (30)
        $trend = 0.0;
        if ($close !== null && $ma20 !== null && $close > $ma20) {
            $trend += 10;
            $this->push($codes, $reasons, 'TREND_C_GT_MA20', 'pos', 10, $debug);
        }
        if ($ma20 !== null && $ma50 !== null && $ma20 > $ma50) {
            $trend += 10;
            $this->push($codes, $reasons, 'TREND_MA20_GT_MA50', 'pos', 10, $debug);
        }
        if ($ma50 !== null && $ma200 !== null && $ma50 > $ma200) {
            $trend += 10;
            $this->push($codes, $reasons, 'TREND_MA50_GT_MA200', 'pos', 10, $debug);
        }
        if ($trend > 30) $trend = 30;

        // ---------------- Momentum (20)
        $momentum = 0.0;
        if ($rsi !== null) {
            if ($rsi >= 60) {
                $momentum += 12;
                $this->push($codes, $reasons, 'MOM_RSI_STRONG', 'pos', 12, $debug, ['avg' => $rsi]);
            } elseif ($rsi >= 55) {
                $momentum += 10;
                $this->push($codes, $reasons, 'MOM_RSI_OK', 'pos', 10, $debug, ['avg' => $rsi]);
            } elseif ($rsi >= 50) {
                $momentum += 6;
                $this->push($codes, $reasons, 'MOM_RSI_NEUTRAL', 'warn', 6, $debug, ['avg' => $rsi]);
            } else {
                $momentum += 2;
                $this->push($codes, $reasons, 'MOM_RSI_WEAK', 'warn', 2, $debug, ['avg' => $rsi]);
            }
        }

        // decision bonus kecil (tetap cap 20)
        if ($decisionCode === 5) {
            $momentum += 5;
            $this->push($codes, $reasons, 'DECISION_5', 'pos', 5, $debug);
        } elseif ($decisionCode === 4) {
            $momentum += 2;
            $this->push($codes, $reasons, 'DECISION_4', 'pos', 2, $debug);
        }

        // range sehat (bukan syarat, hanya bonus kecil)
        if ($rangePct !== null) {
            if ($rangePct >= 3.0) {
                $momentum += 3;
                $this->push($codes, $reasons, 'MOM_RANGE_ACTIVE', 'pos', 3, $debug);
            } elseif ($rangePct >= 2.0) {
                $momentum += 2;
                $this->push($codes, $reasons, 'MOM_RANGE_OK', 'pos', 2, $debug);
            }
        }
        if ($momentum > 20) $momentum = 20;

        // ---------------- Volume & Liquidity (20)
        $volume = 0.0;
        if ($volRatio !== null) {
            if ($volRatio >= 2.0) {
                $volume += 10;
                $this->push($codes, $reasons, 'VOL_RATIO_GE_20', 'pos', 10, $debug, ['vol_ratio' => $volRatio]);
            } elseif ($volRatio >= 1.5) {
                $volume += 7;
                $this->push($codes, $reasons, 'VOL_RATIO_GE_15', 'pos', 7, $debug, ['vol_ratio' => $volRatio]);
            } elseif ($volRatio >= 1.0) {
                $volume += 4;
                $this->push($codes, $reasons, 'VOL_RATIO_GE_10', 'pos', 4, $debug, ['vol_ratio' => $volRatio]);
            }
        }

        // legacy volume label bonus (cap total 20)
        if ($volLabelCode === 7) {
            $volume += 6;
            $this->push($codes, $reasons, 'VOL_STRONG_BURST', 'pos', 6, $debug);
        } elseif ($volLabelCode === 6) {
            $volume += 4;
            $this->push($codes, $reasons, 'VOL_BURST', 'pos', 4, $debug);
        } elseif ($volLabelCode === 5) {
            $volume += 2;
            $this->push($codes, $reasons, 'VOL_EARLY', 'pos', 2, $debug);
        }

        if ($valueEst !== null) {
            if ($valueEst >= 5000000000) {
                $volume += 10;
                $this->push($codes, $reasons, 'LIQ_GE_5B', 'pos', 10, $debug);
            } elseif ($valueEst >= 2000000000) {
                $volume += 7;
                $this->push($codes, $reasons, 'LIQ_GE_2B', 'pos', 7, $debug);
            } elseif ($valueEst >= 1000000000) {
                $volume += 4;
                $this->push($codes, $reasons, 'LIQ_GE_1B', 'pos', 4, $debug);
            }
        }
        if ($volume > 20) $volume = 20;

        // ---------------- Risk Quality (20)
        $risk = 0.0;

        // RR TP2
        if ($rrTp2 !== null) {
            if ($rrTp2 >= 2.0) {
                $risk += 8;
                $this->push($codes, $reasons, 'RR_GE_20', 'pos', 8, $debug, ['rrTp2' => $rrTp2]);
            } elseif ($rrTp2 >= 1.5) {
                $risk += 6;
                $this->push($codes, $reasons, 'RR_GE_15', 'pos', 6, $debug, ['rrTp2' => $rrTp2]);
            } elseif ($rrTp2 >= 1.2) {
                $risk += 3;
                $this->push($codes, $reasons, 'RR_GE_12', 'pos', 3, $debug, ['rrTp2' => $rrTp2]);
            } elseif ($rrTp2 > 0 && $rrTp2 < $this->rrMin) {
                // penalty kecil tapi tetap mencerminkan kualitas risk buruk
                $risk -= 4;
                $this->push($codes, $reasons, 'RR_BELOW_MIN', 'neg', -4, $debug, ['rrTp2' => $rrTp2, 'min' => $this->rrMin]);
            }
        } else {
            $this->push($codes, $reasons, 'RR_UNKNOWN', 'warn', 0, $debug);
        }

        // ATR% lebih kecil -> lebih "tenang" (bonus kecil)
        if ($atrPct !== null) {
            if ($atrPct <= 3.0) {
                $risk += 5;
                $this->push($codes, $reasons, 'ATR_LOW', 'pos', 5, $debug);
            } elseif ($atrPct <= 5.0) {
                $risk += 3;
                $this->push($codes, $reasons, 'ATR_OK', 'pos', 3, $debug);
            } else {
                $this->push($codes, $reasons, 'ATR_HIGH', 'warn', 0, $debug);
            }
        }

        // Gap% kecil -> lebih aman untuk entry pre-open
        if ($gapPct !== null) {
            if (abs($gapPct) <= 1.0) {
                $risk += 4;
                $this->push($codes, $reasons, 'GAP_SMALL', 'pos', 4, $debug);
            } elseif (abs($gapPct) <= 2.0) {
                $risk += 2;
                $this->push($codes, $reasons, 'GAP_OK', 'pos', 2, $debug);
            } else {
                $this->push($codes, $reasons, 'GAP_RISK', 'warn', 0, $debug);
            }
        }

        // Expiry penalty
        if ($expiryStatus === 'EXPIRED') {
            $risk -= 10;
            $this->push($codes, $reasons, 'EXPIRED', 'neg', -10, $debug);
        } elseif ($expiryStatus === 'AGING') {
            $risk -= 4;
            $this->push($codes, $reasons, 'AGING', 'warn', -4, $debug);
        }

        // Plan invalid penalty
        if (!empty($planErrors)) {
            $risk -= 10;
            $this->push($codes, $reasons, 'PLAN_INVALID', 'neg', -10, $debug, ['errors' => $planErrors]);
        }

        if ($risk < 0) $risk = 0;
        if ($risk > 20) $risk = 20;

        // ---------------- Market Alignment (10)
        $market = 0.0;
        if ($marketRegime === 'risk_on') {
            $market = 10.0;
            $this->push($codes, $reasons, 'MARKET_RISK_ON', 'pos', 10, $debug);
        } elseif ($marketRegime === 'risk_off') {
            $market = 0.0;
            $this->push($codes, $reasons, 'MARKET_RISK_OFF', 'neg', 0, $debug);
        } else {
            $market = 5.0;
            $this->push($codes, $reasons, 'MARKET_NEUTRAL', 'warn', 5, $debug);
        }

        // ---------------- Signal small bonus (opsional) - keep deterministic
        // signalCode tidak masuk komponen utama, tapi kita expose sebagai code (tanpa points) untuk UI.
        if ($signalCode !== 0) {
            $this->push($codes, $reasons, 'SIGNAL_' . $signalCode, 'info', 0, $debug, ['signalCode' => $signalCode]);
        }

        $total = $trend + $momentum + $volume + $risk + $market;
        if ($total < 0) $total = 0;
        if ($total > 100) $total = 100;

        return [
            'score' => (float) $total,
            'breakdown' => [
                'trend' => (float) $trend,
                'momentum' => (float) $momentum,
                'volume' => (float) $volume,
                'risk' => (float) $risk,
                'market' => (float) $market,
            ],
            'codes' => array_values(array_unique($codes)),
            'reasons' => $debug ? $reasons : [],
        ];
    }

    private function f($v): ?float
    {
        if ($v === null) return null;
        if ($v === '') return null;
        return (float) $v;
    }

    private function push(array &$codes, array &$reasons, string $code, string $severity, float $points, bool $debug, array $ctx = []): void
    {
        $codes[] = $code;
        if (!$debug) return;
        $item = [
            'code' => $code,
            'message' => ReasonCatalog::rankReasonMessage($code, $ctx),
            'severity' => $severity,
            'points' => (float) $points,
        ];
        if (!empty($ctx)) $item['context'] = $ctx;
        $reasons[] = $item;
    }
}
