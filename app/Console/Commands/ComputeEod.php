<?php

namespace App\Console\Commands;

use App\Services\Compute\ComputeEodService;
use App\Trade\Support\TradeClock;
use App\Trade\Support\TradePerf;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ComputeEod extends Command
{
    protected $signature = 'trade:compute-eod
        {--date= : Single trade date (YYYY-MM-DD)}
        {--from= : Start date (YYYY-MM-DD)}
        {--to= : End date (YYYY-MM-DD)}
        {--ticker= : Optional single ticker code (ex: BBCA)}
        {--chunk=200 : Ticker chunk size}
    ';

    protected $description = 'Compute indikator EOD (holiday-aware) + decision/volume + signal age';

    private ComputeEodService $service;

    public function __construct(ComputeEodService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $ticker = $this->option('ticker') ?: null;
        $date   = $this->option('date');
        $from   = $this->option('from');
        $to     = $this->option('to');
        $tz     = TradeClock::tz();
        $chunk  = (int) ($this->option('chunk') ?: TradePerf::tickerChunk());


        // normalize from/to
        if ($from && !$to) $to = $from;
        if ($to && !$from) $from = $to;

        // --- Single
        if ($date) {
            $res = $this->service->runDate($date, $ticker, $chunk);

            $this->printResult($res);
            return ($res['status'] ?? '') === 'error' ? 1 : 0;
        }

        // --- Range
        if ($from && $to) {
            $start = Carbon::parse($from, $tz);
            $end   = Carbon::parse($to, $tz);

            if ($start->gt($end)) {
                $this->error('Invalid range: --to must be >= --from');
                return 1;
            }

            $summary = [
                'days' => 0,
                'ok' => 0,
                'skipped' => 0,
                'error' => 0,
                'processed' => 0,
                'skipped_no_row' => 0,
            ];

            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $ds = $d->toDateString();
                $res = $this->service->runDate($ds, $ticker, $chunk);

                $summary['days']++;
                $st = $res['status'] ?? 'unknown';
                if (!isset($summary[$st])) $summary[$st] = 0;
                $summary[$st]++;

                $summary['processed'] += (int)($res['processed'] ?? 0);
                $summary['skipped_no_row'] += (int)($res['skipped_no_row'] ?? 0);

                // 1-line progress yang jelas
                $requested = $res['requested_date'] ?? $ds;
                $resolved  = $res['resolved_trade_date'] ?? null;

                $line = "{$requested} -> " . ($resolved ?: 'null') . " | {$st}";
                if (!empty($res['reason'])) $line .= " | reason={$res['reason']}";
                if (isset($res['processed'])) $line .= " | processed={$res['processed']}";
                if (isset($res['skipped_no_row'])) $line .= " | skipped_no_row={$res['skipped_no_row']}";
                $this->line($line);
            }

            $this->newLine();
            $this->info('SUMMARY');
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));

            return $summary['error'] > 0 ? 1 : 0;
        }

        // --- Default (no date): service resolve sendiri (today vs prev day by cutoff)
        $res = $this->service->runDate(null, $ticker, $chunk);
        $this->printResult($res);
        return ($res['status'] ?? '') === 'error' ? 1 : 0;
    }

    private function printResult(array $res): void
    {
        $st = $res['status'] ?? 'unknown';
        $requested = $res['requested_date'] ?? null;
        $resolved  = $res['resolved_trade_date'] ?? null;

        $this->info("STATUS: {$st}");
        $this->line("requested_date     : " . ($requested ?: '(default)'));
        $this->line("resolved_trade_date : " . ($resolved ?: 'null'));

        if (!empty($res['reason'])) {
            $this->warn("reason             : {$res['reason']}");
        }

        if (isset($res['processed'])) {
            $this->line("processed          : " . (int)$res['processed']);
        }
        if (isset($res['skipped_no_row'])) {
            $this->line("skipped_no_row     : " . (int)$res['skipped_no_row']);
        }
        if (isset($res['ticker_filter'])) {
            $this->line("ticker_filter      : " . ($res['ticker_filter'] ?: '(all)'));
        }
        if (isset($res['chunk_size'])) {
            $this->line("chunk_size         : " . (int)$res['chunk_size']);
        }
        if (isset($res['tickers_in_scope'])) {
            $this->line("tickers_in_scope   : " . (int)$res['tickers_in_scope']);
        }
        if (isset($res['chunks'])) {
            $this->line("chunks             : " . (int)$res['chunks']);
        }

        $this->newLine();
    }
}
