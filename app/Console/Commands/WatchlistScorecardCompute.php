<?php

namespace App\Console\Commands;

use App\Services\Watchlist\WatchlistScorecardService;
use Illuminate\Console\Command;

class WatchlistScorecardCompute extends Command
{
    protected $signature = 'watchlist:scorecard:compute
        {--trade-date= : Watchlist trade_date (EOD signal date) YYYY-MM-DD}
        {--exec-date= : Execution date YYYY-MM-DD}
        {--policy= : Policy code (e.g. WEEKLY_SWING)}
        {--source= : Source key used when saving plan (default: preopen_contract_<policy>)}';

    protected $description = 'Compute and store watchlist scorecard metrics for a given plan + latest check.';

    public function handle(WatchlistScorecardService $svc): int
    {
        $tradeDate = (string)($this->option('trade-date') ?? '');
        $execDate = (string)($this->option('exec-date') ?? '');
        $policy = strtoupper((string)($this->option('policy') ?? ''));
        $source = (string)($this->option('source') ?? '');
        if ($source === '') {
            $source = 'preopen_contract' . ($policy !== '' ? '_' . strtolower($policy) : '');
        }

        if ($tradeDate === '' || $execDate === '' || $policy === '') {
            $this->error('Missing required options: --trade-date, --exec-date, --policy');
            return 2;
        }

        $metrics = $svc->computeScorecardDto($tradeDate, $execDate, $policy, $source);

        $out = [
            'trade_date' => $tradeDate,
            'exec_trade_date' => $execDate,
            'exec_date' => $execDate,
            'policy' => $policy,
            'source' => $source,
            'metrics' => $metrics->toArray(),
        ];

        $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}
