<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarketData\Importers\OhlcEodImportService;

class ImportOhlcEod extends Command
{
    protected $signature = 'trade:import-ohlc-eod
        {--start= : Start date (YYYY-MM-DD)}
        {--end= : End date (YYYY-MM-DD)}
        {--date= : Single date (YYYY-MM-DD)}
        {--ticker= : Optional single ticker code (ex: BBCA)}
    ';

    protected $description = 'Import OHLC EOD from external provider (default: config trade.market_data.default_provider)';

    private OhlcEodImportService $svc;

    public function __construct(OhlcEodImportService $svc)
    {
        parent::__construct();
        $this->svc = $svc;
    }

    public function handle()
    {
        $date = $this->option('date');
        $start = $this->option('start');
        $end = $this->option('end');
        $ticker = $this->option('ticker') ?: null;

        if ($date) {
            $start = $date;
            $end = $date;
        }

        if (!$start || !$end) {
            $this->error('Use --date=YYYY-MM-DD or --start=YYYY-MM-DD --end=YYYY-MM-DD');
            return 1;
        }

        $res = $this->svc->run($start, $end, $ticker);

        $this->line(json_encode($res, JSON_PRETTY_PRINT));

        return ($res['status'] ?? '') === 'ok' ? 0 : 1;
    }
}
