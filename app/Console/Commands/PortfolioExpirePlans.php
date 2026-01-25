<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Portfolio\PortfolioService;

class PortfolioExpirePlans extends Command
{
    /**
     * Usage:
     *  php artisan portfolio:expire-plans --date=YYYY-MM-DD [--account_id=1]
     */
    protected $signature = 'portfolio:expire-plans {--date=} {--account_id=}';
    protected $description = 'Expire PLANNED portfolio plans whose entry expiry has passed';

    public function handle(PortfolioService $svc)
    {
        $date = (string)($this->option('date') ?? '');
        $accOpt = $this->option('account_id');
        $accountId = ($accOpt === null || trim((string)$accOpt) === '') ? null : (int)$accOpt;

        $res = $svc->expirePlans($date, $accountId);
        $this->line(json_encode($res, JSON_UNESCAPED_SLASHES));
        return $res['ok'] ? 0 : 1;
    }
}
