<?php

namespace App\Console\Commands;

use App\Services\Portfolio\PortfolioService;
use Illuminate\Console\Command;

class PortfolioValueEod extends Command
{
    protected $signature = 'portfolio:value-eod
        {--date= : Trade date (YYYY-MM-DD)}
        {--account=1 : Account ID}';

    protected $description = 'Update portfolio_positions valuations using canonical EOD close';

    private PortfolioService $svc;

    public function __construct(PortfolioService $svc)
    {
        parent::__construct();
        $this->svc = $svc;
    }

    public function handle()
    {
        $date = (string)$this->option('date');
        $accountId = (int)$this->option('account');
        $res = $this->svc->valueEod($date, $accountId);
        if (!$res['ok']) {
            $this->error('ERROR: ' . ($res['error'] ?? 'unknown'));
            return 1;
        }

        $this->info('OK valued=' . (string)($res['valued'] ?? 0));
        return 0;
    }
}
