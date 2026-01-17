<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarketData\PublishEodService;

final class MarketDataPublishEod extends Command
{
    protected $signature = 'market-data:publish-eod
        {--run_id= : Canonical run_id to publish (required)}
        {--batch=2000 : Batch size for chunking canonical rows}
    ';

    protected $description = 'Publish md_canonical_eod (SUCCESS run) into ticker_ohlc_daily';

    /** @var PublishEodService */
    private $svc;

    public function __construct(PublishEodService $svc)
    {
        parent::__construct();
        $this->svc = $svc;
    }

    public function handle(): int
    {
        $runId = $this->option('run_id') !== null ? (int) $this->option('run_id') : 0;
        $batch = $this->option('batch') !== null ? (int) $this->option('batch') : 2000;

        if ($runId <= 0) {
            $this->error('Missing/invalid --run_id');
            return 1;
        }

        $out = $this->svc->publish($runId, $batch);

        $this->line('--- Market Data Publish EOD ---');
        $this->line('run_id: ' . ($out['run_id'] ?? $runId));
        $this->line('status: ' . ($out['status'] ?? 'UNKNOWN'));
        $this->line('published: ' . ($out['published'] ?? 0));
        $this->line('batch: ' . ($out['batch'] ?? $batch));

        $notes = (array) ($out['notes'] ?? []);
        foreach ($notes as $n) {
            if (strpos((string) $n, 'warn_') === 0) {
                $this->warn((string) $n);
            } else {
                $this->line((string) $n);
            }
        }

        return ((string) ($out['status'] ?? '') === 'SUCCESS') ? 0 : 1;
    }
}
