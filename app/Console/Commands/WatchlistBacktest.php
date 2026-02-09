<?php

namespace App\Console\Commands;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\WatchlistPersistenceRepository;
use App\Trade\Watchlist\WatchlistEngine;
use Illuminate\Console\Command;
use Throwable;

/**
 * watchlist:backtest
 *
 * Step 14: run watchlist preopen generation over a date range from CLI.
 *
 * Notes:
 * - We iterate ONLY trading days (based on market_calendars).
 * - Each loop uses now_ts at end-of-day (23:59:00) to make the engine deterministic.
 * - Optional --persist will store the generated preopen contract in watchlist persistence tables.
 */
class WatchlistBacktest extends Command
{
    /** @var string */
    protected $signature = 'watchlist:backtest
        {--policy= : Policy code (e.g. WEEKLY_SWING)}
        {--from= : Start date (YYYY-MM-DD)}
        {--to= : End date (YYYY-MM-DD)}
        {--source=backtest : Source label for persistence (when --persist=1)}
        {--persist=0 : Persist generated snapshot (0/1)}
        {--json=0 : Output JSON lines (0/1)}
        {--debug=0 : Print exception class + file:line (0/1)}';

    /** @var string */
    protected $description = 'Backtest watchlist preopen generation across a date range.';

    /** @var WatchlistEngine */
    private $engine;
    /** @var MarketCalendarRepository */
    private $calRepo;
    /** @var WatchlistPersistenceRepository */
    private $persistRepo;

    public function __construct(
        WatchlistEngine $engine,
        MarketCalendarRepository $calRepo,
        WatchlistPersistenceRepository $persistRepo
    ) {
        parent::__construct();
        $this->engine = $engine;
        $this->calRepo = $calRepo;
        $this->persistRepo = $persistRepo;
    }

    public function handle(): int
    {
        $policy = (string)($this->option('policy') ?: '');
        $from = (string)($this->option('from') ?: '');
        $to = (string)($this->option('to') ?: '');
        $source = (string)($this->option('source') ?: 'backtest');
        $persist = (string)($this->option('persist') ?: '0') === '1';
        $asJson = (string)($this->option('json') ?: '0') === '1';

        if ($policy === '' || $from === '' || $to === '') {
            $this->error('Missing required options: --policy, --from, --to');
            $this->line('Example: php artisan watchlist:backtest --policy=WEEKLY_SWING --from=2026-01-01 --to=2026-02-01');
            return 2;
        }

        $dates = $this->calRepo->tradingDatesBetween($from, $to);
        if (empty($dates)) {
            $this->error('No trading days found in the given range. Ensure market_calendars is populated.');
            return 3;
        }

        $ok = 0;
        $fail = 0;
        $startTs = microtime(true);

        foreach ($dates as $d) {
            // end-of-day timestamp (local timezone handled by app config)
            $nowTs = $d . ' 23:59:00';

            try {
                $contract = $this->engine->buildPreopen([
                    'policy' => $policy,
                    'eod_date' => $d,
                    'now_ts' => $nowTs,
                    'source' => $source,
                ]);

                // Extract minimal stable summary
                $meta = (array)($contract['meta'] ?? []);
                $groups = (array)($contract['groups'] ?? []);
                $counts = [];
                $topTickers = [];

                foreach ($groups as $g) {
                    $k = (string)($g['semantic'] ?? '');
                    if ($k === '') {
                        continue;
                    }
                    $items = (array)($g['items'] ?? []);
                    $counts[$k] = count($items);
                    if (empty($topTickers) && $k === 'TOP_PICKS') {
                        foreach ($items as $it) {
                            $t = (string)($it['ticker_code'] ?? ($it['ticker'] ?? ''));
                            if ($t !== '') {
                                $topTickers[] = $t;
                            }
                        }
                    }
                }

                if ($persist) {
                    // Persist against trade_date (execution date) so scorecard/check-live can bootstrap
                    $tradeDate = (string)($meta['trade_date'] ?? $d);

                    // Persist full contract first (idempotent by trade_date+policy+source)
                    $dailyId = $this->persistRepo->saveDailySnapshot($tradeDate, $contract, $policy, $source);

                    // Then persist flattened candidates (idempotent by daily_id+ticker)
                    $this->persistRepo->saveCandidatesFromContract(
                        (int)$dailyId,
                        (array)$meta,
                        (array)$groups
                    );
                }

                $ok++;

                if ($asJson) {
                    $this->line(json_encode([
                        'eod_date' => $d,
                        'policy' => $policy,
                        'trade_date' => (string)($meta['trade_date'] ?? ''),
                        'counts' => $counts,
                        'top_picks' => array_slice($topTickers, 0, 10),
                        'persisted' => $persist,
                    ]));
                } else {
                    $this->info(sprintf('%s OK  trade_date=%s  top=%s  secondary=%s  watch=%s  avoid=%s',
                        $d,
                        (string)($meta['trade_date'] ?? '-'),
                        (string)($counts['TOP_PICKS'] ?? 0),
                        (string)($counts['SECONDARY'] ?? 0),
                        (string)($counts['WATCH_ONLY'] ?? 0),
                        (string)($counts['AVOID'] ?? 0)
                    ));
                }
            } catch (Throwable $e) {
                $fail++;
                $debug = (bool)((int) $this->option('debug'));
                $err = sprintf('%s: %s @ %s:%d',
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    (int) $e->getLine()
                );
                if ($asJson) {
                    $this->line(json_encode([
                        'eod_date' => $d,
                        'policy' => $policy,
                        'error' => $err,
                    ]));
                } else {
                    $this->error(sprintf('%s FAIL %s', $d, $err));
                    if ($debug) {
                        $this->line($e->getTraceAsString());
                    }
                }
            }
        }

        $elapsed = microtime(true) - $startTs;
        if (!$asJson) {
            $this->line(sprintf('Done. ok=%d fail=%d elapsed=%.2fs%s', $ok, $fail, $elapsed, $persist ? ' (persisted)' : ''));
        }

        return $fail === 0 ? 0 : 1;
    }
}
