<?php

namespace App\Services\MarketData;

use App\Repositories\TickerOhlcDailyRepository;
use App\Repositories\MarketData\CanonicalEodRepository;
use App\Repositories\MarketData\RunRepository;
use App\Trade\Support\TradeClock;

final class PublishEodService
{
    /** @var TickerOhlcDailyRepository */
    private $ohlcRepo;

    /** @var CanonicalEodRepository */
    private $canRepo;

    /** @var RunRepository */
    private $runRepo;

    public function __construct(TickerOhlcDailyRepository $ohlcRepo, RunRepository $runRepo, CanonicalEodRepository $canRepo)
    {
        $this->ohlcRepo = $ohlcRepo;
        $this->runRepo = $runRepo;
        $this->canRepo = $canRepo;
    }

    /**
     * Publish canonical EOD (md_canonical_eod) into main OHLC table (ticker_ohlc_daily).
     *
     * Rules:
     * - Only publish if md_runs.status = SUCCESS.
     * - If run is HELD/FAILED/etc -> do nothing (return FAILED).
     *
     * @return array{status:string, run_id:int, published:int, batch:int, notes:string[]}
     */
    public function publish(int $runId, int $batch = 2000): array
    {
        $notes = [];

        if ($runId <= 0) {
            return [
                'status' => 'FAILED',
                'run_id' => $runId,
                'published' => 0,
                'batch' => $batch,
                'notes' => ['invalid_run_id'],
            ];
        }

        if ($batch <= 0) $batch = 2000;

        $run = $this->runRepo->find($runId);
        if (!$run) {
            return [
                'status' => 'FAILED',
                'run_id' => $runId,
                'published' => 0,
                'batch' => $batch,
                'notes' => ['run_not_found'],
            ];
        }

        $ok = $this->runRepo->assertSuccess($runId);
        if (empty($ok['ok'])) {
            $st = isset($ok['status']) ? (string) $ok['status'] : 'UNKNOWN';
            return [
                'status' => 'FAILED',
                'run_id' => $runId,
                'published' => 0,
                'batch' => $batch,
                'notes' => ['run_status_not_success:' . ($st ?: 'UNKNOWN')],
            ];
        }

        $now = TradeClock::now();

        // Stream canonical rows in chunks to avoid memory blowups.
        $published = 0;
        $rejectedNull = 0;

        $this->canRepo->chunkByRunId($runId, function ($rows) use (
                &$published,
                &$rejectedNull,
                $now
            ) {
                $payload = [];

                foreach ($rows as $r) {
                    // STRICT: avoid DB errors on NOT NULL columns in ticker_ohlc_daily.
                    // If any required value is null, skip and count reject.
                    if ($r->ticker_id === null || $r->trade_date === null) {
                        $rejectedNull++;
                        continue;
                    }
                    if ($r->open === null || $r->high === null || $r->low === null || $r->close === null || $r->volume === null) {
                        $rejectedNull++;
                        continue;
                    }

                    $row = [
                        'ticker_id' => (int) $r->ticker_id,
                        'trade_date' => (string) $r->trade_date,
                        'open' => (float) $r->open,
                        'high' => (float) $r->high,
                        'low'  => (float) $r->low,
                        'close'=> (float) $r->close,
                        'volume' => (int) $r->volume,
                        'source' => $r->chosen_source !== null ? (string) $r->chosen_source : null,
                        'run_id' => (int) $r->run_id,
                        'adj_close' => $r->adj_close !== null ? (float) $r->adj_close : null,
                        'ca_hint' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $payload[] = $row;
                }

                if ($payload) {
                    $this->ohlcRepo->upsertMany($payload);
                    $published += count($payload);
                }
            });

        if ($published === 0) {
            $notes[] = 'no_canonical_rows';
            return [
                'status' => 'FAILED',
                'run_id' => $runId,
                'published' => 0,
                'batch' => $batch,
                'notes' => $notes,
            ];
        }

        // Optional: append notes to md_runs.notes (do not fail publish if notes update fails)
        $add1 = 'published_ohlc_rows=' . $published;
        $add2 = 'rejected_null_fields=' . $rejectedNull;

        $ok1 = $this->runRepo->appendNote($runId, $add1);
        $ok2 = $this->runRepo->appendNote($runId, $add2);

        if (!$ok1 || !$ok2) {
            $notes[] = 'warn_notes_update_failed';
        }

        $notes[] = 'publish_ok';
        $notes[] = 'published=' . $published;

        if ($rejectedNull > 0) {
            $notes[] = 'warn_rejected_null_fields=' . $rejectedNull;
        }

        return [
            'status' => 'SUCCESS',
            'run_id' => $runId,
            'published' => $published,
            'batch' => $batch,
            'notes' => $notes,
        ];
    }
}
