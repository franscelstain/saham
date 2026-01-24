<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarketData\ValidateEodService;
use App\Services\Watchlist\WatchlistService;
use App\Repositories\MarketData\CandidateValidationRepository;
use Illuminate\Support\Facades\Schema;

/**
 * market-data:validate-eod
 *
 * Validasi subset ticker (recommended/candidates) yang sudah diproses dari Yahoo canonical
 * menggunakan provider validator (default: EODHD).
 *
 * Catatan:
 * - Ini bukan import 900 ticker.
 * - Default cap mengikuti config trade.market_data.validator.* dan daily_call_limit provider.
 */
final class MarketDataValidateEod extends Command
{
    protected $signature = 'market-data:validate-eod
        {--date= : Trade date (YYYY-MM-DD)}
        {--tickers= : Comma-separated ticker codes (ex: BBCA,BBRI)}
        {--max= : Max tickers to validate (override cap, still limited by config/provider)}
        {--run_id= : Optional canonical run_id override}
        {--save=1 : Persist results into md_candidate_validations if table exists}
    ';

    protected $description = 'Validate canonical EOD against validator provider (EODHD) for subset tickers';

    /** @var ValidateEodService */
    private $svc;

    /** @var WatchlistService */
    private $watchSvc;

    /** @var CandidateValidationRepository */
    private $valRepo;

    public function __construct(ValidateEodService $svc, WatchlistService $watchSvc, CandidateValidationRepository $valRepo)
    {
        parent::__construct();
        $this->svc = $svc;
        $this->watchSvc = $watchSvc;
        $this->valRepo = $valRepo;
    }

    public function handle(): int
    {
        $date = $this->option('date') ? (string) $this->option('date') : '';
        $tickersOpt = $this->option('tickers') ? (string) $this->option('tickers') : '';
        $max = $this->option('max') !== null ? (int) $this->option('max') : null;
        $runId = $this->option('run_id') !== null ? (int) $this->option('run_id') : null;
        $save = $this->option('save') !== null ? (int) $this->option('save') : 1;

        $tickers = [];
        if ($tickersOpt !== '') {
            foreach (explode(',', $tickersOpt) as $t) {
                $t = strtoupper(trim($t));
                if ($t !== '') $tickers[] = $t;
            }
        }

        // Phase 7: automatic tickers source
        // If user doesn't pass --tickers, take from watchlist top picks (recommended picks) to avoid manual input.
        if (!$tickers) {
            $wl = $this->watchSvc->preopenContract();
            // Contract: trade_date is the EOD basis date
            $wlDate = (string) ($wl['trade_date'] ?? '');

            // If user didn't pass --date, default to watchlist latest eod_date.
            if (trim($date) === '' && $wlDate !== '') {
                $date = $wlDate;
            }

            $groups = (array) ($wl['groups'] ?? []);
            $top = (array) ($groups['top_picks'] ?? []);
            foreach ($top as $row) {
                if (!is_array($row)) continue;
                $code = strtoupper(trim((string) ($row['ticker_code'] ?? '')));
                if ($code !== '') $tickers[] = $code;
            }
        }

        $out = $this->svc->run($date, $tickers, $max, $runId);

        $summary = (array) ($out['summary'] ?? []);
        $rows = (array) ($out['rows'] ?? []);
        $notes = (array) ($out['notes'] ?? []);

        $this->line('--- Market Data EOD Validate (Subset) ---');
        $this->line('trade_date: ' . ($summary['trade_date'] ?? $date));
        $this->line('status: ' . ($summary['status'] ?? 'UNKNOWN'));

        if (!empty($summary['canonical_run_id'])) {
            $this->line('canonical_run_id: ' . $summary['canonical_run_id']);
        }

        if (!empty($summary['caps'])) {
            $c = (array) $summary['caps'];
            $this->line('cap: ' . ($c['cap'] ?? '-') . ' (cfg_max=' . ($c['cfg_max'] ?? '-') . ', daily_call_limit=' . ($c['daily_call_limit'] ?? '-') . ', opt_max=' . ($c['opt_max'] ?? '-') . ')');
        }

        if (!empty($summary['thresholds'])) {
            $t = (array) $summary['thresholds'];
            $this->line('thresholds: warn_pct=' . ($t['warn_pct'] ?? '-') . '%, disagree_major_pct=' . ($t['disagree_major_pct'] ?? '-') . '%');
        }

        if (!empty($summary['counts'])) {
            $c = (array) $summary['counts'];
            $this->line('counts: OK=' . ($c['OK'] ?? 0)
                . ', WARN=' . ($c['WARN'] ?? 0)
                . ', DISAGREE_MAJOR=' . ($c['DISAGREE_MAJOR'] ?? 0)
                . ', PRIMARY_NO_DATA=' . ($c['PRIMARY_NO_DATA'] ?? 0)
                . ', VALIDATOR_NO_DATA=' . ($c['VALIDATOR_NO_DATA'] ?? 0)
                . ', VALIDATOR_ERROR=' . ($c['VALIDATOR_ERROR'] ?? 0)
                . ', INVALID_TICKER=' . ($c['INVALID_TICKER'] ?? 0)
            );
        }

        if ($notes) {
            foreach ($notes as $n) {
                $this->warn((string) $n);
            }
        }

        // Render per-ticker table (compact)
        if ($rows) {
            $table = [];
            foreach ($rows as $r) {
                $table[] = [
                    (string) ($r['ticker_code'] ?? '-'),
                    (string) ($r['status'] ?? '-'),
                    $r['primary_close'] !== null ? (string) $r['primary_close'] : '-',
                    $r['validator_close'] !== null ? (string) $r['validator_close'] : '-',
                    $r['diff_pct'] !== null ? (string) $r['diff_pct'] : '-',
                    (string) ($r['error_code'] ?? ''),
                ];
            }

            $this->table(['Ticker', 'Status', 'Close(primary)', 'Close(validator)', 'Diff %', 'Err'], $table);
        }

        // Persist results (optional) to avoid re-calling validator when rendering UI.
        if ($save === 1 && $rows && Schema::hasTable('md_candidate_validations')) {
            $saved = $this->valRepo->upsertFromValidateRows((string) ($summary['trade_date'] ?? $date), $rows);
            $this->line('saved_rows: ' . $saved);
        }

        // Exit code: fail if hard mismatch or validator error exists
        $counts = (array) ($summary['counts'] ?? []);
        $hasBad = ((int) ($counts['DISAGREE_MAJOR'] ?? 0) > 0) || ((int) ($counts['VALIDATOR_ERROR'] ?? 0) > 0);
        $failed = (string) ($summary['status'] ?? '') === 'FAILED';

        return ($failed || $hasBad) ? 1 : 0;
    }
}
