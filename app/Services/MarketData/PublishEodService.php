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

        $this->canRepo->chunkByRunId($runId, $batch, function ($rows) use (
                &$published,
                $batch,
                $now
            ) {
                $payload = [];

                foreach ($rows as $r) {
                    // Minimal required fields
                    $row = [
                        'ticker_id' => (int) $r->ticker_id,
                        'trade_date' => (string) $r->trade_date,
                        'open' => $r->open !== null ? (float) $r->open : null,
                        'high' => $r->high !== null ? (float) $r->high : null,
                        'low'  => $r->low  !== null ? (float) $r->low  : null,
                        'close'=> $r->close!== null ? (float) $r->close: null,
                        'volume' => $r->volume !== null ? (int) $r->volume : null,
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
            }, 'canonical_id'); // chunkById requires an integer key; assumes md_canonical_eod has canonical_id PK

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
        $add = 'published_ohlc_rows=' . $published;
        if (!$this->runRepo->appendNote($runId, $add)) {
            $notes[] = 'warn_notes_update_failed';
        }

        $notes[] = 'publish_ok';
        $notes[] = 'published=' . $published;

        return [
            'status' => 'SUCCESS',
            'run_id' => $runId,
            'published' => $published,
            'batch' => $batch,
            'notes' => $notes,
        ];
    }
}
