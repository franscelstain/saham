<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: plan snapshots (execution intent) dari watchlist.
 */
class PortfolioPlanRepository
{
    public function tableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('portfolio_plans');
        } catch (\Throwable $e) {
            return false;
        }
    }



    public function findById(int $planId): ?object
    {
        if ($planId <= 0 || !$this->tableExists()) return null;
        return DB::table('portfolio_plans')->where('id', $planId)->first();
    }
    /**
     * @param array<string,mixed> $row
     * @return array{id:int, created:bool, status?:string}
     */
    public function upsertOne(array $row): array
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('portfolio_plans_table_missing');
        }

        $accountId = (int)($row['account_id'] ?? 0);
        $tickerId = (int)($row['ticker_id'] ?? 0);
        $strategy = (string)($row['strategy_code'] ?? '');
        $asOf = (string)($row['as_of_trade_date'] ?? '');
        $ver = (string)($row['plan_version'] ?? '');
        if ($accountId <= 0 || $tickerId <= 0 || $strategy === '' || $asOf === '' || $ver === '') {
            throw new \InvalidArgumentException('invalid_plan_keys');
        }

        $now = now();
        $row['updated_at'] = $now;
        $row['created_at'] = $row['created_at'] ?? $now;

        $existing = DB::table('portfolio_plans')
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->where('strategy_code', $strategy)
            ->where('as_of_trade_date', $asOf)
            ->where('plan_version', $ver)
            ->first();

        if ($existing && isset($existing->id)) {
            $id = (int) $existing->id;
            $patch = $row;
            unset($patch['id']);
            // If OPENED: keep snapshot immutable.
            if ((string)($existing->status ?? '') === 'OPENED') {
                unset($patch['plan_snapshot_json'], $patch['entry_json'], $patch['risk_json'], $patch['take_profit_json'], $patch['timebox_json'], $patch['reason_codes_json']);
            }
            DB::table('portfolio_plans')->where('id', $id)->update($patch);
            return ['id' => $id, 'created' => false, 'status' => (string)($existing->status ?? '')];
        }

        $id = (int) DB::table('portfolio_plans')->insertGetId($row);
        return ['id' => $id, 'created' => true, 'status' => (string)($row['status'] ?? 'PLANNED')];
    }

    public function find(int $id): ?object
    {
        if ($id <= 0 || !$this->tableExists()) return null;
        return DB::table('portfolio_plans')->where('id', $id)->first();
    }

    /**
     * @return array<int,object>
     */
    public function listExpirable(string $today, ?int $accountId = null): array
    {
        if (!$this->tableExists()) return [];

        $q = DB::table('portfolio_plans')
            ->where('status', 'PLANNED')
            ->whereNotNull('entry_expiry_date')
            ->where('entry_expiry_date', '<=', $today)
            ->orderBy('id');
        if ($accountId !== null) $q->where('account_id', (int)$accountId);

        $rows = $q->get();
        return $rows ? $rows->all() : [];
    }

    public function markExpired(int $planId): void
    {
        if ($planId <= 0 || !$this->tableExists()) return;
        DB::table('portfolio_plans')->where('id', $planId)->update([
            'status' => 'EXPIRED',
            'updated_at' => now(),
        ]);
    }

    /**
     * Mark a plan as CANCELLED.
     *
     * @param string|null $reason Optional cancel reason (kept for API compatibility; currently not persisted)
     */
    public function markCancelled(int $planId, ?string $reason = null): void
    {
        if ($planId <= 0 || !$this->tableExists()) return;
        DB::table('portfolio_plans')->where('id', $planId)->update([
            'status' => 'CANCELLED',
            'updated_at' => now(),
        ]);
    }

    public function findLatestPlanned(int $accountId, int $tickerId, ?string $strategy = null): ?object
    {
        if (!$this->tableExists()) return null;

        $q = DB::table('portfolio_plans')
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->where('status', 'PLANNED')
            ->orderByDesc('id');

        if ($strategy !== null && $strategy !== '') {
            $q->where('strategy_code', $strategy);
        }

        return $q->first();
    }

    public function findLatestForTicker(int $accountId, int $tickerId, ?string $strategy = null): ?object
    {
        if (!$this->tableExists()) return null;

        $q = DB::table('portfolio_plans')
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->whereIn('status', ['OPENED', 'PLANNED'])
            ->orderByDesc('id');

        if ($strategy !== null && $strategy !== '') {
            $q->where('strategy_code', $strategy);
        }
        return $q->first();
    }

    public function markOpened(int $planId): void
    {
        if ($planId <= 0 || !$this->tableExists()) return;
        DB::table('portfolio_plans')->where('id', $planId)->update([
            'status' => 'OPENED',
            'updated_at' => now(),
        ]);
    }
}
