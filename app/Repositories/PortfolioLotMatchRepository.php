<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: akses DB untuk match records (FIFO).
 */
class PortfolioLotMatchRepository
{
    public function tableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('portfolio_lot_matches');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function upsertOne(array $row): int
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('portfolio_lot_matches_table_missing');
        }

        $sellTradeId = (int)($row['sell_trade_id'] ?? 0);
        $buyLotId = (int)($row['buy_lot_id'] ?? 0);
        if ($sellTradeId <= 0 || $buyLotId <= 0) {
            throw new \InvalidArgumentException('invalid_match_keys');
        }

        $now = now();
        $row['updated_at'] = $row['updated_at'] ?? $now;
        $row['created_at'] = $row['created_at'] ?? $now;

        $existing = DB::table('portfolio_lot_matches')
            ->where('sell_trade_id', $sellTradeId)
            ->where('buy_lot_id', $buyLotId)
            ->first();

        if ($existing && isset($existing->id)) {
            $id = (int) $existing->id;
            $patch = $row;
            unset($patch['id']);
            DB::table('portfolio_lot_matches')->where('id', $id)->update($patch);
            return $id;
        }

        return (int) DB::table('portfolio_lot_matches')->insertGetId($row);
    }

    public function sumRealizedPnl(int $accountId, int $tickerId): float
    {
        if (!$this->tableExists()) return 0.0;

        $row = DB::table('portfolio_lot_matches')
            ->selectRaw('SUM(realized_pnl) AS pnl')
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->first();

        return $row && $row->pnl !== null ? (float) $row->pnl : 0.0;
    }
}
