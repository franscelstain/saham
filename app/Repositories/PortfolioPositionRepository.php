<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * SRP: posisi terbuka untuk policy POSITION_TRADE / NO_TRADE carry-only.
 */
class PortfolioPositionRepository
{
    /**
     * @return array<int,array{ticker_id:int,avg_price:float,position_lots:int,entry_date:string|null}>
     */
    public function openPositionsByTicker(int $accountId = 1): array
    {
        if (!$this->tableExists('portfolio_positions')) {
            return [];
        }

        $rows = DB::table('portfolio_positions')
            ->where('is_open', 1)
            ->where('account_id', $accountId)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $tid = (int)($r->ticker_id ?? 0);
            if ($tid <= 0) continue;
            $out[$tid] = [
                'ticker_id' => $tid,
                'avg_price' => (float)($r->avg_price ?? 0),
                'position_lots' => (int)($r->position_lots ?? 0),
                'entry_date' => $r->entry_date ? (string)$r->entry_date : null,
                'qty' => isset($r->qty) ? (int)($r->qty ?? 0) : ((int)($r->position_lots ?? 0) * 100),
                'strategy_code' => $r->strategy_code !== null ? (string)$r->strategy_code : (($r->policy_code ?? null) ? (string)$r->policy_code : null),
            ];
        }
        return $out;
    }

    public function listOpenPositions(int $accountId = 1): array
    {
        if (!$this->tableExists('portfolio_positions')) return [];

        $rows = DB::table('portfolio_positions')
            ->where('account_id', $accountId)
            ->where('is_open', 1)
            ->orderBy('ticker_id')
            ->get();

        return $rows ? $rows->all() : [];
    }

    public function findOpen(int $accountId, int $tickerId): ?object
    {
        if ($accountId <= 0 || $tickerId <= 0 || !$this->tableExists('portfolio_positions')) return null;
        return DB::table('portfolio_positions')
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->where('is_open', 1)
            ->orderByDesc('id')
            ->first();
    }

    public function upsertPosition(array $row): void
    {
        if (!$this->tableExists('portfolio_positions')) return;

        // Key: (account_id,ticker_id,is_open=1) - one open position per ticker per account
        $accountId = (int)($row['account_id'] ?? 1);
        $tickerId = (int)($row['ticker_id'] ?? 0);
        if ($tickerId <= 0) return;

        $isOpen = (int)($row['is_open'] ?? 0);

        $now = now();
        $row['updated_at'] = $row['updated_at'] ?? $now;
        $row['created_at'] = $row['created_at'] ?? $now;

        // Close: flip latest open row if present.
        if ($isOpen === 0) {
            $open = DB::table('portfolio_positions')
                ->where('account_id', $accountId)
                ->where('ticker_id', $tickerId)
                ->where('is_open', 1)
                ->orderByDesc('id')
                ->first();

            if ($open && isset($open->id)) {
                $id = (int)$open->id;
                $patch = $row;
                unset($patch['id']);
                DB::table('portfolio_positions')->where('id', $id)->update($patch);
                return;
            }
        }

        // Try update existing row with same is_open to avoid duplicates.
        $existing = DB::table('portfolio_positions')
            ->where('account_id', $accountId)
            ->where('ticker_id', $tickerId)
            ->where('is_open', $isOpen)
            ->orderByDesc('id')
            ->first();

        if ($existing && isset($existing->id)) {
            $id = (int)$existing->id;
            $patch = $row;
            unset($patch['id']);
            DB::table('portfolio_positions')->where('id', $id)->update($patch);
            return;
        }

        DB::table('portfolio_positions')->insert($row);
    }

    public function hasOpenPositions(): bool
    {
        if (!$this->tableExists('portfolio_positions')) return false;
        return DB::table('portfolio_positions')->where('is_open', 1)->limit(1)->exists();
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
