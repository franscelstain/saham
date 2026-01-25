<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: audit trail event posisi.
 */
class PortfolioPositionEventRepository
{
    public function tableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('portfolio_position_events');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    public function insertOne(array $row): int
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('portfolio_position_events_table_missing');
        }

        $row['created_at'] = $row['created_at'] ?? now();
        return (int) DB::table('portfolio_position_events')->insertGetId($row);
    }

    /**
     * Get latest as_of_trade_date for a given event_type.
     */
    public function lastEventTradeDate(int $accountId, int $tickerId, string $eventType): ?string
    {
        if ($accountId <= 0 || $tickerId <= 0 || trim($eventType) === '' || !$this->tableExists()) return null;

        $row = DB::table('portfolio_position_events')
            ->select(['as_of_trade_date'])
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->where('event_type', $eventType)
            ->whereNotNull('as_of_trade_date')
            ->orderByDesc('as_of_trade_date')
            ->orderByDesc('id')
            ->first();

        return $row && isset($row->as_of_trade_date) ? (string)$row->as_of_trade_date : null;
    }

    /**
     * Check whether an event already exists for the same plan_version and as_of_trade_date.
     * This is used for idempotency of BE_ARMED / SL_MOVED events.
     */
    public function existsForPlan(
        int $accountId,
        int $tickerId,
        string $eventType,
        ?string $planVersion,
        ?string $asOfTradeDate
    ): bool {
        if ($accountId <= 0 || $tickerId <= 0 || trim($eventType) === '' || !$this->tableExists()) return false;

        $q = DB::table('portfolio_position_events')
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->where('event_type', $eventType);

        if ($planVersion !== null && trim($planVersion) !== '') {
            $q->where('plan_version', $planVersion);
        }
        if ($asOfTradeDate !== null && trim($asOfTradeDate) !== '') {
            $q->where('as_of_trade_date', $asOfTradeDate);
        }

        return $q->exists();
    }
}
