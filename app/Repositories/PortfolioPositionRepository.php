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
    public function openPositionsByTicker(): array
    {
        if (!$this->tableExists('portfolio_positions')) {
            return [];
        }

        $rows = DB::table('portfolio_positions')
            ->where('is_open', 1)
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
            ];
        }
        return $out;
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
