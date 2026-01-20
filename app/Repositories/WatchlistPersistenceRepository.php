<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WatchlistPersistenceRepository
{
    /**
     * Save full preopen payload as daily snapshot.
     * Returns watchlist_daily_id.
     */
    public function saveDailySnapshot(string $tradeDate, array $payload, string $policyCode = 'pre_open'): int
    {
        $now = now();

        $id = DB::table('watchlist_daily')->insertGetId([
            'trade_date' => $tradeDate,
            'policy_code' => $policyCode,
            'generated_at' => $now,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $id;
    }

    /**
     * Persist flattened candidates per bucket.
     * $groups format: ['top_picks'=>[], 'watch'=>[], 'avoid'=>[]]
     */
    public function saveCandidates(int $dailyId, string $tradeDate, array $groups): void
    {
        $now = now();
        $rows = [];

        $mapBuckets = [
            'top_picks' => 'TOP_PICKS',
            'watch' => 'WATCH',
            'avoid' => 'AVOID',
        ];

        foreach ($mapBuckets as $k => $bucket) {
            $list = $groups[$k] ?? [];
            if (!is_array($list)) continue;

            foreach ($list as $idx => $r) {
                if (!is_array($r)) continue;

                $rows[] = [
                    'watchlist_daily_id' => $dailyId,
                    'trade_date' => $tradeDate,
                    'ticker_id' => (int)($r['ticker_id'] ?? ($r['tickerId'] ?? 0)),
                    'ticker' => (string)($r['ticker'] ?? ($r['code'] ?? '')),
                    'bucket' => $bucket,
                    'rank' => is_numeric($r['rank'] ?? null) ? (int)$r['rank'] : ($idx + 1),
                    'score' => is_numeric($r['rankScore'] ?? ($r['rank_score'] ?? null)) ? (float)($r['rankScore'] ?? ($r['rank_score'] ?? 0)) : null,

                    'decision_code' => (int)($r['decision_code'] ?? ($r['decisionCode'] ?? 0)),
                    'signal_code' => (int)($r['signal_code'] ?? ($r['signalCode'] ?? 0)),
                    'volume_label_code' => (int)($r['volume_label_code'] ?? ($r['volumeLabelCode'] ?? 0)),

                    'open' => isset($r['open']) && is_numeric($r['open']) ? (float)$r['open'] : null,
                    'high' => isset($r['high']) && is_numeric($r['high']) ? (float)$r['high'] : null,
                    'low' => isset($r['low']) && is_numeric($r['low']) ? (float)$r['low'] : null,
                    'close' => isset($r['close']) && is_numeric($r['close']) ? (float)$r['close'] : null,
                    'volume' => isset($r['volume']) && is_numeric($r['volume']) ? (int)$r['volume'] : null,

                    'value_est' => isset($r['value_est']) && is_numeric($r['value_est']) ? (float)$r['value_est'] : (isset($r['valueEst']) && is_numeric($r['valueEst']) ? (float)$r['valueEst'] : null),
                    'dv20' => isset($r['dv20']) && is_numeric($r['dv20']) ? (float)$r['dv20'] : null,
                    'liq_bucket' => (string)($r['liq_bucket'] ?? ''),

                    'candle_body_pct' => isset($r['candle_body_pct']) && is_numeric($r['candle_body_pct']) ? (float)$r['candle_body_pct'] : null,
                    'candle_upper_wick_pct' => isset($r['candle_upper_wick_pct']) && is_numeric($r['candle_upper_wick_pct']) ? (float)$r['candle_upper_wick_pct'] : null,
                    'candle_lower_wick_pct' => isset($r['candle_lower_wick_pct']) && is_numeric($r['candle_lower_wick_pct']) ? (float)$r['candle_lower_wick_pct'] : null,
                    'is_inside_day' => isset($r['is_inside_day']) ? (int)((bool)$r['is_inside_day']) : null,
                    'engulfing_type' => !empty($r['engulfing_type']) ? (string)$r['engulfing_type'] : null,
                    'is_long_upper_wick' => isset($r['is_long_upper_wick']) ? (int)((bool)$r['is_long_upper_wick']) : null,
                    'is_long_lower_wick' => isset($r['is_long_lower_wick']) ? (int)((bool)$r['is_long_lower_wick']) : null,

                    'plan_json' => isset($r['plan']) ? json_encode($r['plan'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    'reason_codes_json' => isset($r['rankReasonCodes']) ? json_encode($r['rankReasonCodes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (isset($r['rank_reason_codes']) ? json_encode($r['rank_reason_codes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null),

                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rows)) {
            DB::table('watchlist_candidates')->insert($rows);
        }
    }
}
