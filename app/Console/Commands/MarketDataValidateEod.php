<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarketData\ValidateEodService;

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
    ';

    protected $description = 'Validate canonical EOD against validator provider (EODHD) for subset tickers';

    /** @var ValidateEodService */
    private $svc;

    public function __construct(ValidateEodService $svc)
    {
        parent::__construct();
        $this->svc = $svc;
    }

    public function handle(): int
    {
        $date = $this->option('date') ? (string) $this->option('date') : '';
        $tickersOpt = $this->option('tickers') ? (string) $this->option('tickers') : '';
        $max = $this->option('max') !== null ? (int) $this->option('max') : null;
        $runId = $this->option('run_id') !== null ? (int) $this->option('run_id') : null;

        $tickers = [];
        if ($tickersOpt !== '') {
            foreach (explode(',', $tickersOpt) as $t) {
                $t = strtoupper(trim($t));
                if ($t !== '') $tickers[] = $t;
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

        $counts = (array) ($summary['counts'] ?? []);
        $hasBad = ((int) ($counts['DISAGREE_MAJOR'] ?? 0) > 0) || ((int) ($counts['VALIDATOR_ERROR'] ?? 0) > 0);
        $failed = (string) ($summary['status'] ?? '') === 'FAILED';

        return ($failed || $hasBad) ? 1 : 0;
    }
}
