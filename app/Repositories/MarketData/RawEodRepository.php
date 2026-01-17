<?php

namespace App\Repositories\MarketData;

use Illuminate\Support\Facades\DB;

class RawEodRepository
{
    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function insertMany(array $rows, int $batch = 2000): void
    {
        if (!$rows) return;

        $chunks = array_chunk($rows, max(1, $batch));
        foreach ($chunks as $c) {
            DB::table('md_raw_eod')->insert($c);
        }
    }
}
