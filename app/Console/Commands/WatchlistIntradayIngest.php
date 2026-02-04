<?php

namespace App\Console\Commands;

use App\Repositories\IntradaySnapshotRepository;
use App\Repositories\TickerRepository;
use Illuminate\Console\Command;

/**
 * Ingest manual intraday snapshots into watchlist_intraday_snapshots.
 *
 * This is intentionally simple: it supports manual JSON snapshots and upserts by (trade_date, ticker_id).
 *
 * JSON input (allowed variants):
 *  - {"checked_at":"2026-02-02T09:20:00+07:00","tickers":[{"ticker":"BBCA","bid1":10000,"ask1":10005,"last":10000,"open":9950}]}
 *  - {"checked_at":"09:20:00","tickers":{"BBCA":{"bid1":10000,"ask1":10005,"last":10000,"open":9950}}}
 */
class WatchlistIntradayIngest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --trade-date is required because it is part of the unique key.
     */
    protected $signature = 'watchlist:intraday:ingest
        {--trade-date= : Execution date (YYYY-MM-DD)}
        {--input= : JSON file path (default: STDIN)}';

    protected $description = 'Ingest intraday snapshots (manual JSON) into watchlist_intraday_snapshots.';

    /** @var TickerRepository */
    private $tickerRepo;
    /** @var IntradaySnapshotRepository */
    private $snapRepo;

    public function __construct(TickerRepository $tickerRepo, IntradaySnapshotRepository $snapRepo)
    {
        parent::__construct();
        $this->tickerRepo = $tickerRepo;
        $this->snapRepo = $snapRepo;
    }

    public function handle(): int
    {
        $tradeDate = (string) $this->option('trade-date');
        $tradeDate = trim($tradeDate);
        if ($tradeDate === '') {
            $this->error('--trade-date is required (YYYY-MM-DD)');
            return 2;
        }

        $inputPath = (string) $this->option('input');
        $raw = '';
        if ($inputPath !== '') {
            if (!is_file($inputPath)) {
                $this->error("input file not found: {$inputPath}");
                return 2;
            }
            $raw = (string) file_get_contents($inputPath);
        } else {
            $raw = (string) stream_get_contents(STDIN);
        }

        $raw = trim($raw);
        if ($raw === '') {
            $this->error('empty input');
            return 2;
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $this->error('invalid JSON input');
            return 2;
        }

        $globalCheckedAt = isset($json['checked_at']) ? (string) $json['checked_at'] : '';
        $tickers = $json['tickers'] ?? null;

        // Normalize tickers into list of rows: [ticker_code, fields]
        $rows = [];
        if (is_array($tickers)) {
            $isList = array_keys($tickers) === range(0, count($tickers) - 1);
            if ($isList) {
                foreach ($tickers as $t) {
                    if (!is_array($t)) continue;
                    $code = isset($t['ticker']) ? (string)$t['ticker'] : (isset($t['ticker_code']) ? (string)$t['ticker_code'] : '');
                    $code = strtoupper(trim($code));
                    if ($code === '') continue;
                    $rows[] = ['code' => $code, 'fields' => $t];
                }
            } else {
                foreach ($tickers as $code => $t) {
                    $code = strtoupper(trim((string)$code));
                    if ($code === '' || !is_array($t)) continue;
                    $rows[] = ['code' => $code, 'fields' => $t];
                }
            }
        }

        if (empty($rows)) {
            $this->error('no tickers found in input');
            return 2;
        }

        $codes = array_values(array_unique(array_map(function ($r) { return $r['code']; }, $rows)));
        $idMap = $this->tickerRepo->resolveIdsByCodes($codes);

        $inserted = 0;
        $skippedMissingTicker = 0;
        foreach ($rows as $row) {
            $code = $row['code'];
            if (!isset($idMap[$code])) {
                $skippedMissingTicker++;
                continue;
            }

            $tid = (int) $idMap[$code];
            $f = is_array($row['fields']) ? $row['fields'] : [];

            $checkedAt = '';
            if (isset($f['checked_at'])) $checkedAt = (string)$f['checked_at'];
            if ($checkedAt === '') $checkedAt = $globalCheckedAt;
            $checkedAtNorm = $this->normalizeCheckedAt($tradeDate, $checkedAt);

            $bid1 = $this->numOrNull($f['bid1'] ?? ($f['bid_best'] ?? null));
            $ask1 = $this->numOrNull($f['ask1'] ?? ($f['ask_best'] ?? null));
            $last = $this->numOrNull($f['last'] ?? null);
            $open = $this->numOrNull($f['open'] ?? null);

            $openOrLast = $open !== null ? $open : $last;
            $spreadPct = null;
            if ($bid1 !== null && $ask1 !== null && $bid1 > 0) {
                $spreadPct = ($ask1 - $bid1) / $bid1;
            }

            $fields = [
                'checked_at' => $checkedAtNorm,
                'bid1' => $bid1,
                'ask1' => $ask1,
                'last' => $last,
                'open' => $open,
                'open_or_last_exec' => $openOrLast,
                'spread_pct' => $spreadPct,

                // Optional depth (Top-3)
                'bid2' => $this->numOrNull($f['bid2'] ?? null),
                'bid3' => $this->numOrNull($f['bid3'] ?? null),
                'ask2' => $this->numOrNull($f['ask2'] ?? null),
                'ask3' => $this->numOrNull($f['ask3'] ?? null),
                'bid_lots1' => $this->intOrNull($f['bid_lots1'] ?? null),
                'bid_lots2' => $this->intOrNull($f['bid_lots2'] ?? null),
                'bid_lots3' => $this->intOrNull($f['bid_lots3'] ?? null),
                'ask_lots1' => $this->intOrNull($f['ask_lots1'] ?? null),
                'ask_lots2' => $this->intOrNull($f['ask_lots2'] ?? null),
                'ask_lots3' => $this->intOrNull($f['ask_lots3'] ?? null),
            ];

            try {
                $this->snapRepo->upsertSnapshot($tradeDate, $tid, $code, $fields);
                $inserted++;
            } catch (\Throwable $e) {
                // fail-soft per row
            }
        }

        $this->info("ok: upserted={$inserted}, missing_ticker={$skippedMissingTicker}");
        return 0;
    }

    private function normalizeCheckedAt(string $tradeDate, string $checkedAt): ?string
    {
        $checkedAt = trim($checkedAt);
        if ($checkedAt === '') return null;

        // If only HH:MM(:SS) provided, glue to trade_date
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $checkedAt)) {
            $t = strlen($checkedAt) === 5 ? $checkedAt . ':00' : $checkedAt;
            return $tradeDate . ' ' . $t;
        }

        // Try parse ISO/RFC3339 to local datetime string
        $ts = strtotime($checkedAt);
        if ($ts === false) return null;

        return date('Y-m-d H:i:s', $ts);
    }

    private function numOrNull($v): ?float
    {
        if ($v === null) return null;
        if (is_string($v)) $v = trim($v);
        if ($v === '' || $v === false) return null;
        if (!is_numeric($v)) return null;
        return (float) $v;
    }

    private function intOrNull($v): ?int
    {
        if ($v === null) return null;
        if (is_string($v)) $v = trim($v);
        if ($v === '' || $v === false) return null;
        if (!is_numeric($v)) return null;
        return (int) $v;
    }
}
