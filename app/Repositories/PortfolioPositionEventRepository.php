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
}
