<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WatchlistPersistenceRepository
{
    /**
     * Save full preopen payload as daily snapshot.
     * Returns watchlist_daily_id.
     */
    public function saveDailySnapshot(string $tradeDate, array $payload, string $source = 'preopen'): int
    {
        $now = now();

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Idempotent per (trade_date, source). If a snapshot already exists,
        // update it instead of inserting a new row.
        $existing = DB::table('watchlist_daily')
            ->select(['watchlist_daily_id'])
            ->where('trade_date', $tradeDate)
            ->where('source', $source)
            ->orderByDesc('watchlist_daily_id')
            ->first();

        if ($existing && !empty($existing->watchlist_daily_id)) {
            DB::table('watchlist_daily')
                ->where('watchlist_daily_id', (int) $existing->watchlist_daily_id)
                ->update([
                    'generated_at' => $now,
                    'payload_json' => $payloadJson,
                    'updated_at' => $now,
                ]);

            return (int) $existing->watchlist_daily_id;
        }

        // Insert new snapshot. If two requests race, unique index will enforce
        // single row; in that case we re-read and update.
        try {
            $id = DB::table('watchlist_daily')->insertGetId([
                'trade_date' => $tradeDate,
                'source' => $source,
                'generated_at' => $now,
                'payload_json' => $payloadJson,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return (int) $id;
        } catch (\Throwable $e) {
            $existing = DB::table('watchlist_daily')
                ->select(['watchlist_daily_id'])
                ->where('trade_date', $tradeDate)
                ->where('source', $source)
                ->orderByDesc('watchlist_daily_id')
                ->first();

            if ($existing && !empty($existing->watchlist_daily_id)) {
                DB::table('watchlist_daily')
                    ->where('watchlist_daily_id', (int) $existing->watchlist_daily_id)
                    ->update([
                        'generated_at' => $now,
                        'payload_json' => $payloadJson,
                        'updated_at' => $now,
                    ]);
                return (int) $existing->watchlist_daily_id;
            }

            throw $e;
        }
    }

    /**
     * Persist flattened candidates per bucket.
     * $groups format: ['top_picks'=>[], 'watch'=>[], 'avoid'=>[]]
     */
    public function saveCandidates(int $dailyId, string $tradeDate, array $groups): void
    {
        $now = now();
        $rows = [];

        // Idempotent refresh: avoid duplicated candidate rows when endpoint is hit
        // multiple times for the same daily snapshot.
        DB::table('watchlist_candidates')->where('watchlist_daily_id', $dailyId)->delete();

        $mapBuckets = [
            // contract buckets
            'top_picks' => 'TOP_PICKS',
            'secondary' => 'SECONDARY',
            'watch_only' => 'WATCH_ONLY',
            // legacy buckets
            'watch' => 'WATCH',
            'avoid' => 'AVOID',
        ];

        foreach ($mapBuckets as $k => $bucket) {
            $list = $groups[$k] ?? [];
            if (!is_array($list)) continue;

            foreach ($list as $idx => $r) {
                if (!is_array($r)) continue;

                $rankScore = null;
                if (isset($r['watchlist_score']) && is_numeric($r['watchlist_score'])) $rankScore = (float) $r['watchlist_score'];
                elseif (isset($r['rankScore']) && is_numeric($r['rankScore'])) $rankScore = (float) $r['rankScore'];
                elseif (isset($r['rank_score']) && is_numeric($r['rank_score'])) $rankScore = (float) $r['rank_score'];

                $plan = $r['plan'] ?? ($r['trade_plan'] ?? null);
                if ($plan === null && (isset($r['levels']) || isset($r['timing']) || isset($r['sizing']))) {
                    $plan = [
                        'levels' => $r['levels'] ?? null,
                        'timing' => $r['timing'] ?? null,
                        'sizing' => $r['sizing'] ?? null,
                        'reason_codes' => $r['reason_codes'] ?? [],
                        'setup_type' => $r['setup_type'] ?? null,
                    ];
                }
                $debug = isset($r['debug']) && is_array($r['debug']) ? $r['debug'] : null;

                $rankReasonCodes = $r['rankReasonCodes'] ?? ($r['rank_reason_codes'] ?? ($debug['rank_reason_codes'] ?? null));
                $rankBreakdown = $r['rank_breakdown'] ?? ($r['score_breakdown'] ?? ($debug['score_breakdown'] ?? null));

                $rows[] = [
                    'watchlist_daily_id' => $dailyId,
                    'trade_date' => $tradeDate,
                    'ticker_id' => (int)($r['ticker_id'] ?? ($r['tickerId'] ?? 0)),
                    'ticker' => (string)($r['ticker'] ?? ($r['ticker_code'] ?? ($r['code'] ?? ''))),
                    'bucket' => $bucket,
                    'rank' => is_numeric($r['rank'] ?? null) ? (int)$r['rank'] : ($idx + 1),
                    'watchlist_score' => $rankScore ?? 0,
                    'confidence' => !empty($r['confidence']) ? (string) $r['confidence'] : null,

                    'decision_code' => (int)($r['decision_code'] ?? ($r['decisionCode'] ?? 0)),
                    'signal_code' => (int)($r['signal_code'] ?? ($r['signalCode'] ?? 0)),
                    'volume_label_code' => (int)($r['volume_label_code'] ?? ($r['volumeLabelCode'] ?? 0)),

                    'decision_label' => !empty($r['decision_label']) ? (string) $r['decision_label'] : (!empty($r['decisionLabel']) ? (string) $r['decisionLabel'] : null),
                    'signal_label' => !empty($r['signal_label']) ? (string) $r['signal_label'] : (!empty($r['signalLabel']) ? (string) $r['signalLabel'] : null),
                    'volume_label' => !empty($r['volume_label']) ? (string) $r['volume_label'] : (!empty($r['volumeLabel']) ? (string) $r['volumeLabel'] : null),

                    'open' => isset($r['open']) && is_numeric($r['open']) ? (float)$r['open'] : null,
                    'high' => isset($r['high']) && is_numeric($r['high']) ? (float)$r['high'] : null,
                    'low' => isset($r['low']) && is_numeric($r['low']) ? (float)$r['low'] : null,
                    'close' => isset($r['close']) && is_numeric($r['close']) ? (float)$r['close'] : null,
                    'volume' => isset($r['volume']) && is_numeric($r['volume']) ? (int)$r['volume'] : null,

                    'prev_open' => isset($r['prev_open']) && is_numeric($r['prev_open']) ? (float)$r['prev_open'] : (isset($r['prevOpen']) && is_numeric($r['prevOpen']) ? (float)$r['prevOpen'] : null),
                    'prev_high' => isset($r['prev_high']) && is_numeric($r['prev_high']) ? (float)$r['prev_high'] : (isset($r['prevHigh']) && is_numeric($r['prevHigh']) ? (float)$r['prevHigh'] : null),
                    'prev_low' => isset($r['prev_low']) && is_numeric($r['prev_low']) ? (float)$r['prev_low'] : (isset($r['prevLow']) && is_numeric($r['prevLow']) ? (float)$r['prevLow'] : null),
                    'prev_close' => isset($r['prev_close']) && is_numeric($r['prev_close']) ? (float)$r['prev_close'] : (isset($r['prevClose']) && is_numeric($r['prevClose']) ? (float)$r['prevClose'] : null),

                    'dv20' => isset($r['dv20']) && is_numeric($r['dv20']) ? (float)$r['dv20'] : null,
                    'liq_bucket' => (string)($r['liq_bucket'] ?? ''),

                    'candle_body_pct' => isset($r['candle_body_pct']) && is_numeric($r['candle_body_pct']) ? (float)$r['candle_body_pct'] : null,
                    'candle_upper_wick_pct' => isset($r['candle_upper_wick_pct']) && is_numeric($r['candle_upper_wick_pct']) ? (float)$r['candle_upper_wick_pct'] : null,
                    'candle_lower_wick_pct' => isset($r['candle_lower_wick_pct']) && is_numeric($r['candle_lower_wick_pct']) ? (float)$r['candle_lower_wick_pct'] : null,
                    'is_inside_day' => isset($r['is_inside_day']) ? (int)((bool)$r['is_inside_day']) : null,
                    'engulfing_type' => !empty($r['engulfing_type']) ? (string)$r['engulfing_type'] : null,
                    'is_long_upper_wick' => isset($r['is_long_upper_wick']) ? (int)((bool)$r['is_long_upper_wick']) : null,
                    'is_long_lower_wick' => isset($r['is_long_lower_wick']) ? (int)((bool)$r['is_long_lower_wick']) : null,

                    'plan' => is_array($plan) ? json_encode($plan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (is_string($plan) ? $plan : null),
                    'rank_reason_codes' => is_array($rankReasonCodes) ? json_encode($rankReasonCodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (is_string($rankReasonCodes) ? $rankReasonCodes : null),
                    'rank_breakdown' => is_array($rankBreakdown) ? json_encode($rankBreakdown, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (is_string($rankBreakdown) ? $rankBreakdown : null),

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
