<?php

namespace App\Trade\Watchlist\Scorecard;

use Illuminate\Support\Facades\DB;

class ScorecardRepository
{
    public function upsertScorecard(int $runId, ?float $feasibleRate, ?float $fillRate, ?float $outcomeRate, array $payload = []): void
    {
        $json = null;
        if (!empty($payload)) {
            $enc = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($enc === false) throw new \RuntimeException('failed to json_encode scorecard payload');
            $json = $enc;
        }

        DB::table('watchlist_scorecards')->upsert([
            [
                'run_id' => $runId,
                'feasible_rate' => $feasibleRate,
                'fill_rate' => $fillRate,
                'outcome_rate' => $outcomeRate,
                'payload_json' => $json,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        ], ['run_id'], ['feasible_rate', 'fill_rate', 'outcome_rate', 'payload_json', 'updated_at']);
    }
}
