<?php

namespace App\Trade\Watchlist\Scorecard;

use App\DTO\Watchlist\Scorecard\EligibilityCheckDto;
use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use App\DTO\Watchlist\Scorecard\StrategyCheckDto;
use Illuminate\Support\Facades\DB;

class StrategyCheckRepository
{
    public function insertCheckFromDto(int $runId, LiveSnapshotDto $snapshot, EligibilityCheckDto $result): int
    {
        return $this->insertCheck($runId, $snapshot->checkedAt, $snapshot->toArray(), $result->toArray());
    }

    private function insertCheck(int $runId, string $checkedAt, array $snapshot, array $result): int
    {
        $snapJson = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
        $resJson = json_encode($result, JSON_UNESCAPED_SLASHES);
        if ($snapJson === false || $resJson === false) {
            throw new \RuntimeException('failed to json_encode snapshot/result');
        }

        $id = DB::table('watchlist_strategy_checks')->insertGetId([
            'run_id' => $runId,
            'checked_at' => $checkedAt,
            'snapshot_json' => $snapJson,
            'result_json' => $resJson,
            'created_at' => DB::raw('CURRENT_TIMESTAMP'),
            'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
        ]);

        return (int) $id;
    }

    /**
     * @return array{check_id:int,checked_at:string,snapshot:array,result:array}|null
     */
    private function getLatestCheck(int $runId): ?array
    {
        $row = DB::table('watchlist_strategy_checks')
            ->where('run_id', $runId)
            ->orderByDesc('checked_at')
            ->select(['check_id', 'checked_at', 'snapshot_json', 'result_json'])
            ->first();

        if (!$row) return null;

        $snapshot = json_decode((string) $row->snapshot_json, true);
        $result = json_decode((string) $row->result_json, true);
        if (!is_array($snapshot)) $snapshot = [];
        if (!is_array($result)) $result = [];

        return [
            'check_id' => (int) $row->check_id,
            'checked_at' => (string) $row->checked_at,
            'snapshot' => $snapshot,
            'result' => $result,
        ];
    }

    public function getLatestCheckDto(int $runId): ?StrategyCheckDto
    {
        $r = $this->getLatestCheck($runId);
        if (!$r) return null;
        return new StrategyCheckDto((int)$r['check_id'], (string)$r['checked_at'], (array)($r['snapshot'] ?? []), (array)($r['result'] ?? []));
    }
}
