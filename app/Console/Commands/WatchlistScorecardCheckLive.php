<?php

namespace App\Console\Commands;

use App\Services\Watchlist\WatchlistScorecardService;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\DTO\Watchlist\Scorecard\LiveSnapshotDto;
use Illuminate\Console\Command;

class WatchlistScorecardCheckLive extends Command
{
    protected $signature = 'watchlist:scorecard:check-live
        {--trade-date= : Watchlist trade_date (EOD signal date) YYYY-MM-DD}
        {--exec-date= : Execution date YYYY-MM-DD}
        {--policy= : Policy code (e.g. WEEKLY_SWING)}
        {--source= : Source label used by preopenContract persistence}
        {--input= : Path to snapshot JSON file. If omitted, read from STDIN}
        {--checkpoint= : Optional checkpoint label (e.g. 09:20)}';

    protected $description = 'Run a live execution eligibility check for a watchlist strategy run and persist the check.';

    public function handle(WatchlistScorecardService $svc, ScorecardConfig $cfg): int
    {
        $tradeDate = (string)($this->option('trade-date') ?? '');
        $execDate = (string)($this->option('exec-date') ?? '');
        $policy = (string)($this->option('policy') ?? '');
        $source = (string)($this->option('source') ?? '');
        if ($tradeDate === '' || $execDate === '' || $policy === '') {
            $this->error('Missing required options: --trade-date, --exec-date, --policy');
            return 2;
        }
        if ($source === '') {
            // Default follows WatchlistService persistence.
            $source = 'preopen_contract_' . strtolower($policy);
        }

        $raw = '';
        $input = (string)($this->option('input') ?? '');
        if ($input !== '') {
            if (!is_file($input)) {
                $this->error("Input file not found: $input");
                return 2;
            }
            $raw = (string) file_get_contents($input);
        } else {
            $raw = (string) stream_get_contents(STDIN);
        }

        $snapshot = json_decode($raw, true);
        if (!is_array($snapshot)) {
            $this->error('Invalid snapshot JSON');
            return 2;
        }
        $checkpoint = (string)($this->option('checkpoint') ?? '');
        if ($checkpoint !== '') {
            $snapshot['checkpoint'] = $checkpoint;
        }
        if (empty($snapshot['checked_at'])) {
            $snapshot['checked_at'] = now()->toRfc3339String();
        }

        try {
            $snapshotDto = LiveSnapshotDto::fromArray($snapshot, $cfg, (string)($snapshot['checked_at'] ?? ''));
            $resultDto = $svc->checkLiveDto($tradeDate, $execDate, $policy, $snapshotDto, $source);
        } catch (\Throwable $e) {
            $this->error('FAIL: ' . $e->getMessage());
            return 1;
        }

        $this->line(json_encode($resultDto->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}
