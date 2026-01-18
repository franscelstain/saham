<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarketData\RebuildCanonicalService;

final class MarketDataRebuildCanonical extends Command
{
    protected $signature = 'market-data:rebuild-canonical
        {--source_run= : RAW run_id sumber (default: latest SUCCESS import run covering end date)}
        {--date= : Single trade date (YYYY-MM-DD)}
        {--from= : Start date (YYYY-MM-DD)}
        {--to= : End date (YYYY-MM-DD)}
        {--ticker= : Optional single ticker code (ex: BBCA)}
    ';

    protected $description = 'Phase 6: Rebuild md_canonical_eod from md_raw_eod without refetch (new run_id audit trail)';

    /** @var RebuildCanonicalService */
    private $svc;

    public function __construct(RebuildCanonicalService $svc)
    {
        parent::__construct();
        $this->svc = $svc;
    }

    public function handle(): int
    {
        $sourceRun = $this->option('source_run') ? (int) $this->option('source_run') : null;
        $date = $this->option('date') ? (string) $this->option('date') : null;
        $from = $this->option('from') ? (string) $this->option('from') : null;
        $to = $this->option('to') ? (string) $this->option('to') : null;
        $ticker = $this->option('ticker') ? (string) $this->option('ticker') : null;

        $out = $this->svc->run($sourceRun, $date, $from, $to, $ticker);

        $this->line('--- Market Data Rebuild Canonical (Phase 6) ---');
        $this->line('run_id: ' . ($out['run_id'] ?? 0));
        $this->line('status: ' . ($out['status'] ?? 'UNKNOWN'));

        if (!empty($out['source_run_id'])) {
            $this->line('source_run_id: ' . $out['source_run_id']);
        }

        if (!empty($out['effective_start']) && !empty($out['effective_end'])) {
            $this->line('range: ' . $out['effective_start'] . ' .. ' . $out['effective_end']);
        }

        if (!empty($out['expected_points'])) {
            $this->line('expected: ' . $out['expected_points'] . ', canonical: ' . ($out['canonical_points'] ?? 0));
            $this->line('coverage_pct: ' . ($out['coverage_pct'] ?? 0) . ', fallback_pct: ' . ($out['fallback_pct'] ?? 0));
            $this->line('hard_rejects: ' . ($out['hard_rejects'] ?? 0) . ', soft_flags: ' . ($out['soft_flags'] ?? 0));
        }

        $notes = $out['notes'] ?? null;
        if (is_array($notes) && $notes) {
            foreach ($notes as $n) $this->warn((string) $n);
        }

        if (($out['status'] ?? '') === 'FAILED') return 1;
        return 0;
    }
}
