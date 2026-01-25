<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: akses DB untuk lots (cost basis) dan update remaining_qty.
 */
class PortfolioLotRepository
{
    public function tableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('portfolio_lots');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function createForBuyTrade(array $row): int
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('portfolio_lots_table_missing');
        }

        $now = now();
        $row['created_at'] = $row['created_at'] ?? $now;
        $row['updated_at'] = $row['updated_at'] ?? $now;

        // idempotent by unique(buy_trade_id)
        $existing = DB::table('portfolio_lots')->where('buy_trade_id', (int)($row['buy_trade_id'] ?? 0))->first();
        if ($existing && isset($existing->id)) {
            return (int) $existing->id;
        }

        return (int) DB::table('portfolio_lots')->insertGetId($row);
    }

    /**
     * FIFO open lots (remaining_qty > 0)
     *
     * @return array<int,object>
     */
    public function listOpenLotsFifo(int $accountId, int $tickerId): array
    {
        if (!$this->tableExists()) return [];

        $rows = DB::table('portfolio_lots')
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->where('remaining_qty', '>', 0)
            ->orderBy('buy_date')
            ->orderBy('id')
            ->get();

        return $rows ? $rows->all() : [];
    }

    public function decrementRemaining(int $lotId, int $qty): void
    {
        if ($lotId <= 0 || $qty <= 0 || !$this->tableExists()) return;

        DB::table('portfolio_lots')
            ->where('id', $lotId)
            ->update([
                'remaining_qty' => DB::raw('GREATEST(remaining_qty - ' . (int)$qty . ', 0)'),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array{qty:int, avg_cost:float|null, entry_date:string|null, lots:int}
     */
    public function summarizeOpenLots(int $accountId, int $tickerId): array
    {
        if (!$this->tableExists()) return ['qty' => 0, 'avg_cost' => null, 'entry_date' => null, 'lots' => 0];

        $rows = DB::table('portfolio_lots')
            ->selectRaw('SUM(remaining_qty) AS qty, SUM(remaining_qty * unit_cost) AS cost_sum, MIN(buy_date) AS entry_date')
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->where('remaining_qty', '>', 0)
            ->first();

        $qty = $rows && $rows->qty !== null ? (int) $rows->qty : 0;
        $costSum = $rows && $rows->cost_sum !== null ? (float) $rows->cost_sum : 0.0;
        $entryDate = $rows && $rows->entry_date !== null ? (string) $rows->entry_date : null;

        $avg = $qty > 0 ? ($costSum / $qty) : null;
        $lots = $qty > 0 ? (int) ceil($qty / 100) : 0;

        return ['qty' => $qty, 'avg_cost' => $avg, 'entry_date' => $entryDate, 'lots' => $lots];
    }
}
