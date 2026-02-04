<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WatchlistPersistenceRepository
{
    /**
     * Save full preopen payload as daily snapshot.
     * Returns watchlist_daily_id.
     *
     * Idempotent per (policy, trade_date, source).
     */
    public function saveDailySnapshot(string $tradeDate, array $payload, string $source = 'preopen'): int
    {
        $now = now();

        $meta = (array)($payload['meta'] ?? []);
        $policy = (string)($meta['policy'] ?? '');
        $asofEodDate = (string)($meta['asof_eod_date'] ?? '');
        $canonicalReady = (bool)($meta['canonical_ready'] ?? false);

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $existing = DB::table('watchlist_daily')
            ->select(['watchlist_daily_id'])
            ->where('policy', $policy)
            ->where('trade_date', $tradeDate)
            ->where('source', $source)
            ->orderByDesc('watchlist_daily_id')
            ->first();

        if ($existing && !empty($existing->watchlist_daily_id)) {
            DB::table('watchlist_daily')
                ->where('watchlist_daily_id', (int) $existing->watchlist_daily_id)
                ->update([
                    'asof_eod_date' => $asofEodDate,
                    'canonical_ready' => $canonicalReady ? 1 : 0,
                    'generated_at' => $now,
                    'payload_json' => $payloadJson,
                    'updated_at' => $now,
                ]);

            return (int) $existing->watchlist_daily_id;
        }

        try {
            $id = DB::table('watchlist_daily')->insertGetId([
                'policy' => $policy,
                'trade_date' => $tradeDate,
                'asof_eod_date' => $asofEodDate,
                'canonical_ready' => $canonicalReady ? 1 : 0,
                'source' => $source,
                'generated_at' => $now,
                'payload_json' => $payloadJson,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return (int) $id;
        } catch (\Throwable $e) {
            // Race-safe retry
            $existing = DB::table('watchlist_daily')
                ->select(['watchlist_daily_id'])
                ->where('policy', $policy)
                ->where('trade_date', $tradeDate)
                ->where('source', $source)
                ->orderByDesc('watchlist_daily_id')
                ->first();

            if ($existing && !empty($existing->watchlist_daily_id)) {
                DB::table('watchlist_daily')
                    ->where('watchlist_daily_id', (int) $existing->watchlist_daily_id)
                    ->update([
                        'asof_eod_date' => $asofEodDate,
                        'canonical_ready' => $canonicalReady ? 1 : 0,
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
     * Persist candidates (groups) from the strict preopen contract.
     *
     * This stays SRP-clean: it stores what the contract already computed
     * (no re-derivation of plan/reasons inside persistence).
     */
    public function saveCandidatesFromContract(int $dailyId, array $meta, array $groups): void
    {
        $now = now();

        $policy = (string)($meta['policy'] ?? '');
        $tradeDate = (string)($meta['trade_date'] ?? '');
        $asofEodDate = (string)($meta['asof_eod_date'] ?? '');

        // Replace rows for this daily snapshot (idempotent).
        DB::table('watchlist_candidates')
            ->where('watchlist_daily_id', $dailyId)
            ->delete();

        $groupMap = [
            'top_picks'  => 'TOP_PICKS',
            'secondary'  => 'SECONDARY',
            'watch_only' => 'WATCH_ONLY',
            'avoid'      => 'AVOID',
            'no_trade'   => 'NO_TRADE',
        ];

        $rows = [];
        foreach ($groupMap as $k => $groupCode) {
            $items = (array)($groups[$k] ?? []);
            foreach ($items as $it) {
                if (!is_array($it)) continue;

                $ticker = (string)($it['ticker'] ?? '');
                if ($ticker === '') continue;

                $rank = isset($it['rank']) ? (int)$it['rank'] : null;
                $scoreTotal = isset($it['score_total']) && is_numeric($it['score_total']) ? (float)$it['score_total'] : null;

                $reasonsJson = json_encode((array)($it['reasons'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $eodBarJson = json_encode((array)($it['eod_bar'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $tickerPlan = (array)($it['ticker_plan'] ?? []);
                $tickerPlanJson = json_encode($tickerPlan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $rows[] = [
                    'watchlist_daily_id' => $dailyId,
                    'policy' => $policy,
                    'trade_date' => $tradeDate,
                    'asof_eod_date' => $asofEodDate,
                    'group_code' => $groupCode,
                    'ticker' => $ticker,
                    'rank' => $rank,
                    'score_total' => $scoreTotal,
                    'setup_type' => isset($tickerPlan['setup_type']) ? (string)$tickerPlan['setup_type'] : null,
                    'plan_entry' => isset($tickerPlan['plan_entry']) ? (int)$tickerPlan['plan_entry'] : null,
                    'plan_stop' => isset($tickerPlan['plan_stop']) ? (int)$tickerPlan['plan_stop'] : null,
                    'plan_tp1' => isset($tickerPlan['plan_tp1']) ? (int)$tickerPlan['plan_tp1'] : null,
                    'rr_est' => isset($tickerPlan['rr_est']) && is_numeric($tickerPlan['rr_est']) ? (float)$tickerPlan['rr_est'] : null,
                    'reasons_json' => $reasonsJson,
                    'eod_bar_json' => $eodBarJson,
                    'ticker_plan_json' => $tickerPlanJson,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rows)) {
            DB::table('watchlist_candidates')->insert($rows);
        }
    }

    /**
     * Find a previously persisted strict preopen contract.
     *
     * @param string $execDate execution date (trade_date in preopen contract)
     * @param string $policy policy code
     * @param string $source source key (default preopen_contract_<policy>)
     * @param string|null $asofEodDate optional filter for asof_eod_date
     * @return array<string,mixed>|null
     */
    public function findDailyContract(string $execDate, string $policy, string $source, ?string $asofEodDate = null): ?array
    {
        $q = DB::table('watchlist_daily')
            ->select(['payload_json'])
            ->where('policy', strtoupper(trim($policy)))
            ->where('trade_date', $execDate)
            ->where('source', (string)$source)
            ->orderByDesc('watchlist_daily_id');

        if ($asofEodDate !== null && $asofEodDate !== '') {
            $q->where('asof_eod_date', $asofEodDate);
        }

        $row = $q->first();
        if (!$row || !isset($row->payload_json) || $row->payload_json === null) return null;

        $decoded = json_decode((string)$row->payload_json, true);
        return is_array($decoded) ? $decoded : null;
    }
}
