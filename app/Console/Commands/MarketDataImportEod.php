<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarketData\ImportEodService;

final class MarketDataImportEod extends Command
{
    protected $signature = 'market-data:import-eod
        {--date= : Single trade date (YYYY-MM-DD)}
        {--from= : Start date (YYYY-MM-DD)}
        {--to= : End date (YYYY-MM-DD)}
        {--ticker= : Optional single ticker code (ex: BBCA)}
        {--chunk=200 : Ticker chunk size}
    ';

    protected $description = 'Import Market Data EOD into md_raw_eod and md_canonical_eod (with gating)';

    /** @var ImportEodService */
    private $svc;

    public function __construct(ImportEodService $svc)
    {
        parent::__construct();
        $this->svc = $svc;
    }

    public function handle(): int
    {
        $date = $this->option('date') ? (string) $this->option('date') : null;
        $from = $this->option('from') ? (string) $this->option('from') : null;
        $to = $this->option('to') ? (string) $this->option('to') : null;
        $ticker = $this->option('ticker') ? (string) $this->option('ticker') : null;
        $chunk = (int) ($this->option('chunk') ?: 200);

        $out = $this->svc->run($date, $from, $to, $ticker, $chunk);

        $this->line('--- Market Data EOD Import ---');
        $this->line('run_id: ' . $out['run_id']);
        $this->line('status: ' . $out['status']);
        $this->line('range: ' . $out['effective_start'] . ' .. ' . $out['effective_end']);
        $this->line('tickers: ' . $out['target_tickers'] . ', days: ' . $out['target_days']);
        $this->line('expected: ' . $out['expected_points'] . ', canonical: ' . $out['canonical_points']);
        $this->line('coverage_pct: ' . $out['coverage_pct'] . ', fallback_pct: ' . $out['fallback_pct']);
        $this->line('hard_rejects: ' . $out['hard_rejects'] . ', soft_flags: ' . $out['soft_flags']);

        if (!empty($out['notes'])) {
            foreach ($out['notes'] as $n) $this->warn((string) $n);
        }

        return $out['status'] === 'FAILED' ? 1 : 0;
    }
}
