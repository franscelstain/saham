<?php

namespace App\Repositories\MarketData;

use Illuminate\Support\Facades\DB;

class RunRepository
{
    public function createRun(array $row): int
    {
        $id = DB::table('md_runs')->insertGetId($row);
        return (int) $id;
    }

    public function finishRun(int $runId, array $patch): void
    {
        $patch['finished_at'] = $patch['finished_at'] ?? now();
        $patch['updated_at'] = $patch['updated_at'] ?? now();

        DB::table('md_runs')
            ->where('run_id', $runId)
            ->update($patch);
    }
}
