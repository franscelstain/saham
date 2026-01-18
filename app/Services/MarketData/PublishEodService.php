<?php

# build_id: v2.2.36
# tujuan: Periksa perubahan dan cari potensi bug/performance issue.

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

        $this->canRepo->chunkByRunId($runId, $batch, function ($rows) use (
                &$published,
                &$rejectedNull,
                $now
            ) {
                $payload = [];

                foreach ($rows as $r) {
                    // Minimal strict: key fields only.
                    if ($r->ticker_id === null || $r->trade_date === null) {
                        $rejectedNull++;
                        continue;
                    }

                    $close = $r->close !== null ? (float) $r->close : null;
                    $adj   = $r->adj_close !== null ? (float) $r->adj_close : null;

                    $priceBasis = null;
                    if ($adj !== null && $adj > 0) {
                        $priceBasis = 'adj_close';
                    } elseif ($close !== null && $close > 0) {
                        $priceBasis = 'close';
                    }

                    $row = [
                        'ticker_id' => (int) $r->ticker_id,
                        'trade_date' => (string) $r->trade_date,
                        'open' => $r->open  !== null ? (float) $r->open : null,
                        'high' => $r->high !== null ? (float) $r->high : null,
                        'low'  => $r->low !== null ? (float) $r->low : null,
                        'close'=> $close,
                        'volume' => $r->volume !== null ? (int) $r->volume : null,
                        'source' => $r->chosen_source !== null ? (string) $r->chosen_source : null,
                        'run_id' => (int) $runId,
                        'adj_close' => $adj,
                        'price_basis' => $priceBasis,
                        'ca_hint' => null,
                        'ca_event' => null,
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
