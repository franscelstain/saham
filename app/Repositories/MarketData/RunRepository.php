<?php

namespace App\Repositories\MarketData;

use Illuminate\Support\Facades\DB;

class RunRepository
{
    public function findLatestImportRun(): ?object
    {
        return DB::table('md_runs')
            ->where('job', 'import_eod')
            ->orderByDesc('run_id')
            ->first();
    }

    public function getLatestSuccessEffectiveEndDate(): ?string
    {
        $row = DB::table('md_runs')
            ->select('effective_end_date')
            ->where('job', 'import_eod')
            ->where('status', 'SUCCESS')
            ->orderByDesc('run_id')
            ->first();

        return $row && isset($row->effective_end_date) ? (string) $row->effective_end_date : null;
    }

    public function createRun(array $row): int
    {
        $id = DB::table('md_runs')->insertGetId($row);
        return (int) $id;
    }

    public function finishRun(int $runId, array $patch): void
    {
        $patch['finished_at'] = $patch['finished_at'] ?? now();
        $patch['updated_at'] = $patch['updated_at'] ?? now();

        // Keep last_good_trade_date consistent with MARKET_DATA.md contract.
        // - If this run SUCCESS: last_good_trade_date = effective_end_date
        // - Else: last_good_trade_date = latest SUCCESS effective_end_date (nullable)
        try {
            $row = $this->find($runId);
            if ($row) {
                $newStatus = isset($patch['status']) ? (string) $patch['status'] : (string) ($row->status ?? '');
                if ($newStatus === 'SUCCESS') {
                    $patch['last_good_trade_date'] = (string) ($row->effective_end_date ?? $patch['effective_end_date'] ?? null);
                } else {
                    $patch['last_good_trade_date'] = $this->getLatestSuccessEffectiveEndDate();
                }
            }
        } catch (\Throwable $e) {
            // ignore (column may not exist yet during migrations in some environments)
        }

        DB::table('md_runs')
            ->where('run_id', $runId)
            ->update($patch);
    }

    public function find(int $runId): ?object
    {
        if ($runId <= 0) return null;

        return DB::table('md_runs')
            ->where('run_id', $runId)
            ->first();
    }

    public function findLatestSuccessImportRunCoveringDate(string $tradeDate): ?int
    {
        $row = DB::table('md_runs')
            ->select('run_id')
            ->where('job', 'import_eod')
            ->where('status', 'SUCCESS')
            ->where('effective_start_date', '<=', $tradeDate)
            ->where('effective_end_date', '>=', $tradeDate)
            ->orderByDesc('run_id')
            ->first();

        return $row && isset($row->run_id) ? (int) $row->run_id : null;
    }

    public function getStatus(int $runId): ?string
    {
        $row = $this->find($runId);
        if (!$row) return null;

        $status = $row->status ?? null;
        return $status !== null ? (string) $status : null;
    }

    /**
     * @return array{ok:bool, status?:string, reason?:string}
     */
    public function assertSuccess(int $runId): array
    {
        $row = $this->find($runId);
        if (!$row) {
            return ['ok' => false, 'reason' => 'run_not_found'];
        }

        $status = (string) ($row->status ?? '');
        if ($status !== 'SUCCESS') {
            return [
                'ok' => false,
                'status' => $status ?: 'UNKNOWN',
                'reason' => 'run_status_not_success',
            ];
        }

        return ['ok' => true, 'status' => $status];
    }

    public function appendNote(int $runId, string $note, string $sep = ' | '): bool
    {
        $note = trim($note);
        if ($runId <= 0 || $note === '') return false;

        $row = $this->find($runId);
        if (!$row) return false;

        $old = (string) ($row->notes ?? '');
        $new = $old !== '' ? ($old . $sep . $note) : $note;

        $affected = DB::table('md_runs')
            ->where('run_id', $runId)
            ->update([
                'notes' => $new,
                'updated_at' => now(),
            ]);

        return $affected > 0;
    }
}
