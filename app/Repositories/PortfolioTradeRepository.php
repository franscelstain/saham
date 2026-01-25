<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: akses DB untuk trades (fills).
 */
class PortfolioTradeRepository
{
    public function tableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('portfolio_trades');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Upsert trade (idempotent) using external_ref (preferred) or trade_hash.
     *
     * @param array{
     *   account_id:int,
     *   ticker_id:int,
     *   symbol?:string|null,
     *   trade_date:string,
     *   side:string,
     *   qty:int,
     *   price:float,
     *   gross_amount?:float|null,
     *   fee_amount?:float|null,
     *   tax_amount?:float|null,
     *   net_amount?:float|null,
     *   external_ref?:string|null,
     *   trade_hash?:string|null,
     *   broker_ref?:string|null,
     *   source?:string|null,
     *   currency?:string|null,
     *   meta_json?:mixed
     * } $row
     * @return array{id:int, created:bool}
     */
    public function upsertOne(array $row): array
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('portfolio_trades_table_missing');
        }

        $now = now();
        $row['updated_at'] = $now;
        $row['created_at'] = $row['created_at'] ?? $now;

        $accountId = (int)($row['account_id'] ?? 0);
        $tickerId = (int)($row['ticker_id'] ?? 0);
        if ($accountId <= 0 || $tickerId <= 0) {
            throw new \InvalidArgumentException('invalid_account_or_ticker');
        }

        $existing = $this->findExistingByKey($row);

        if ($existing && isset($existing->id)) {
            $id = (int) $existing->id;
            $patch = $row;
            unset($patch['id']);
            DB::table('portfolio_trades')->where('id', $id)->update($patch);
            return ['id' => $id, 'created' => false];
        }

        $id = (int) DB::table('portfolio_trades')->insertGetId($row);
        return ['id' => $id, 'created' => true];
    }

    /**
     * Find existing trade by idempotency key (external_ref preferred, else trade_hash).
     * If neither provided, will compute deterministic trade_hash using signature.
     */
    public function findExistingByKey(array $row): ?object
    {
        if (!$this->tableExists()) return null;

        $accountId = (int)($row['account_id'] ?? 0);
        $tickerId = (int)($row['ticker_id'] ?? 0);
        if ($accountId <= 0 || $tickerId <= 0) return null;

        $keyExternal = isset($row['external_ref']) ? (string)($row['external_ref'] ?? '') : '';
        $keyHash = isset($row['trade_hash']) ? (string)($row['trade_hash'] ?? '') : '';

        $query = DB::table('portfolio_trades')->where('account_id', $accountId);
        if ($keyExternal !== '') {
            $query->where('external_ref', $keyExternal);
        } elseif ($keyHash !== '') {
            $query->where('trade_hash', $keyHash);
        } else {
            $sig = implode('|', [
                $accountId,
                $tickerId,
                (string)($row['trade_date'] ?? ''),
                strtoupper((string)($row['side'] ?? '')),
                (int)($row['qty'] ?? 0),
                (float)($row['price'] ?? 0),
            ]);
            $keyHash = hash('sha256', $sig);
            $query->where('trade_hash', $keyHash);
        }

        return $query->orderByDesc('id')->first();
    }

    public function find(int $id): ?object
    {
        if ($id <= 0 || !$this->tableExists()) return null;
        return DB::table('portfolio_trades')->where('id', $id)->first();
    }

    public function existsMatchForSellTrade(int $sellTradeId): bool
    {
        try {
            return DB::table('portfolio_lot_matches')->where('sell_trade_id', $sellTradeId)->limit(1)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function existsLotForBuyTrade(int $buyTradeId): bool
    {
        try {
            return DB::table('portfolio_lots')->where('buy_trade_id', $buyTradeId)->limit(1)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
