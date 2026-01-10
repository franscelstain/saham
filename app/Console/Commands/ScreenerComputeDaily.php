<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScreenerComputeDaily extends Command
{
    protected $signature = 'screener:compute-daily
        {--date= : Tanggal target (YYYY-MM-DD). Jika tanggal tsb tidak ada EOD, fallback ke last trade_date <= date}
        {--ticker= : Proses 1 ticker_code saja (contoh: BBCA)}
        {--source=yahoo : Isi field source (default: yahoo)}
        {--chunk=200 : Chunk tickers per batch}
        {--lookback=420 : Lookback days untuk ambil OHLC (default 420)}';

    protected $description = 'Compute daily indicators + decision + scores and upsert into ticker_indicators_daily';

    public function handle(): int
    {
        $date = $this->resolveDate();
        if (!$date) {
            $this->error('Tidak ada data di ticker_ohlc_daily.');
            return 1;
        }

        $tickerCode = $this->option('ticker');
        $source     = (string) $this->option('source');
        $chunk      = max(1, (int) $this->option('chunk'));
        $lookback   = max(220, (int) $this->option('lookback')); // default aman untuk MA200 + buffer

        $now = Carbon::now(config('app.timezone', 'Asia/Jakarta'));

        $this->info('Compute screener for EOD date: ' . $date->toDateString());
        if ($tickerCode) {
            $this->info('Only ticker: ' . strtoupper(trim($tickerCode)));
        }

        $tickersQuery = DB::table('tickers')
            ->select('ticker_id', 'ticker_code')
            ->where('is_deleted', 0);

        if ($tickerCode) {
            $tickersQuery->where('ticker_code', strtoupper(trim($tickerCode)));
        }

        $processed = 0;
        $saved = 0;
        $skippedNoEod = 0;

        $fromDate = $date->copy()->subDays($lookback)->toDateString();
        $toDate   = $date->toDateString();

        $tickersQuery->orderBy('ticker_id')->chunkById($chunk, function ($tickers) use (
            $date, $source, $now, $fromDate, $toDate,
            &$processed, &$saved, &$skippedNoEod
        ) {
            $tickerIds = $tickers->pluck('ticker_id')->map(function ($v) {
                return (int) $v;
            })->values()->all();

            if (empty($tickerIds)) return;

            // 1 query OHLC untuk 1 batch ticker
            $ohlcRows = DB::table('ticker_ohlc_daily')
                ->select('ticker_id','trade_date','open','high','low','close','volume')
                ->whereIn('ticker_id', $tickerIds)
                ->where('is_deleted', 0)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->orderBy('ticker_id', 'asc')
                ->orderBy('trade_date', 'asc')
                ->get();

            // group per ticker_id
            $grouped = [];
            foreach ($ohlcRows as $r) {
                $tid = (int) $r->ticker_id;
                if (!isset($grouped[$tid])) $grouped[$tid] = [];
                $grouped[$tid][] = $r;
            }

            $rowsToUpsert = [];

            foreach ($tickers as $t) {
                $processed++;
                $tid = (int) $t->ticker_id;

                $rows = $grouped[$tid] ?? [];
                if (empty($rows)) {
                    $skippedNoEod++;
                    continue;
                }

                $row = $this->computeFromRows($tid, $rows, $date, $source, $now);
                if ($row) {
                    $rowsToUpsert[] = $row;
                    $saved++;
                } else {
                    $skippedNoEod++;
                }
            }

            if (!empty($rowsToUpsert)) {
                DB::table('ticker_indicators_daily')->upsert(
                    $rowsToUpsert,
                    ['ticker_id', 'trade_date'],
                    [
                        'open','high','low','close','volume',
                        'ma20','ma50','ma200',
                        'vol_sma20','vol_ratio',
                        'rsi14','atr14',
                        'support_20d','resistance_20d',
                        'decision_code','volume_label_code',
                        'score_total','score_trend','score_momentum','score_volume','score_breakout','score_risk',
                        'source','is_deleted','updated_at'
                    ]
                );
            }
        }, 'ticker_id');

        $this->info("Done. processed={$processed}, saved={$saved}, skipped(no_eod/insufficient)={$skippedNoEod}");
        return 0;
    }

    private function resolveDate(): ?Carbon
    {
        $opt = $this->option('date');

        if ($opt) {
            try {
                $target = Carbon::createFromFormat('Y-m-d', $opt)->startOfDay()->toDateString();
            } catch (\Throwable $e) {
                $this->error("Format --date invalid, harus YYYY-MM-DD. Dapat: {$opt}");
                return null;
            }

            $maxLe = DB::table('ticker_ohlc_daily')
                ->where('trade_date', '<=', $target)
                ->where('is_deleted', 0)
                ->max('trade_date');

            if (!$maxLe) return null;

            if ($maxLe !== $target) {
                $this->warn("Tanggal {$target} tidak ada EOD. Fallback ke last trading date: {$maxLe}");
            }

            return Carbon::parse($maxLe)->startOfDay();
        }

        $max = DB::table('ticker_ohlc_daily')
            ->where('is_deleted', 0)
            ->max('trade_date');
        if (!$max) return null;

        return Carbon::parse($max)->startOfDay();
    }

    /**
     * Compute 1 ticker dari rows OHLC (sudah ASC).
     */
    private function computeFromRows(int $tickerId, array $rows, Carbon $date, string $source, Carbon $now): ?array
    {
        $to = $date->toDateString();

        $last = end($rows);
        if (!$last || (string)$last->trade_date !== $to) {
            return null; // ticker tidak punya EOD pada tanggal target
        }

        // Guard EOD hari ini: jangan paksa null jadi 0
        if ($last->high === null || $last->low === null || $last->close === null) {
            return null;
        }

        $openToday  = ($last->open !== null) ? (float)$last->open : null;
        $highToday  = (float) $last->high;
        $lowToday   = (float) $last->low;
        $closeToday = (float) $last->close;
        $volToday   = (int)   ($last->volume ?? 0);

        $closes = [];
        $highs  = [];
        $lows   = [];
        $vols   = [];

        foreach ($rows as $r) {
            if ($r->close === null || $r->high === null || $r->low === null) continue;
            $closes[] = (float) $r->close;
            $highs[]  = (float) $r->high;
            $lows[]   = (float) $r->low;
            $vols[]   = (int)   ($r->volume ?? 0);
        }

        // Minimal untuk RSI/ATR stabil
        if (count($closes) < 30) return null;

        $ma20  = $this->sma($closes, 20);
        $ma50  = $this->sma($closes, 50);
        $ma200 = $this->sma($closes, 200);

        // Vol SMA 20 exclude today (lebih fair)
        $volSma20Prev = $this->smaExcludeToday($vols, 20);
        $volRatio = ($volSma20Prev !== null && $volSma20Prev > 0) ? round($volToday / $volSma20Prev, 4) : null;

        $support20 = $this->rollingMinExcludeToday($lows, 20);
        $resist20  = $this->rollingMaxExcludeToday($highs, 20);

        $rsi14 = $this->rsiWilder($closes, 14);
        $atr14 = $this->atrWilder($highs, $lows, $closes, 14);

        $volumeLabel = $this->mapVolumeLabel($volRatio);

        // ===== Breakout detection =====
        $falseBreakout = ($resist20 !== null) && ($highToday > $resist20) && ($closeToday <= $resist20);
        $isBreakout    = ($resist20 !== null) && ($closeToday > $resist20);

        // ===== Rule wajib screener (kandidat operasional) =====
        $trendOk = ($ma20 !== null && $ma50 !== null && $ma200 !== null)
            && ($closeToday > $ma20)
            && ($ma20 > $ma50)
            && ($ma50 > $ma200);

        $rsiOk = ($rsi14 !== null) && ($rsi14 <= 75.0);

        // Untuk decision 4/5 wajib volume burst minimal
        $volOk = ($volRatio !== null && $volRatio >= 1.5);

        // ===== Scoring (simple + konsisten) =====
        $scoreTrend = 0;
        if ($ma20 !== null && $closeToday > $ma20) $scoreTrend += 10;
        if ($ma20 !== null && $ma50 !== null && $ma20 > $ma50) $scoreTrend += 10;
        if ($ma50 !== null && $ma200 !== null && $ma50 > $ma200) $scoreTrend += 10;
        if ($trendOk) $scoreTrend += 10;

        $scoreMomentum = 0;
        if ($rsi14 !== null) {
            if ($rsi14 >= 50 && $rsi14 <= 70) $scoreMomentum += 10;
            elseif ($rsi14 > 70 && $rsi14 <= 75) $scoreMomentum += 3;
            elseif ($rsi14 > 75) $scoreMomentum -= 15;
            elseif ($rsi14 < 40) $scoreMomentum -= 5;
        }

        $scoreVolume = 0;
        if ($volRatio !== null) {
            if ($volRatio >= 1.5) $scoreVolume += 10;
            if ($volRatio >= 2.0) $scoreVolume += 10;
            if ($volRatio >= 3.0) $scoreVolume -= 5; // euphoria rawan fake move
            if ($volRatio < 1.0)  $scoreVolume -= 5; // volume lemah
        }

        $scoreBreakout = 0;
        if ($falseBreakout) $scoreBreakout -= 20;
        elseif ($isBreakout) $scoreBreakout += 15;

        $scoreRisk = 0;
        if ($support20 !== null && $atr14 !== null && $atr14 > 0) {
            $dist = $closeToday - $support20;
            if ($dist <= 1.0 * $atr14) $scoreRisk += 5;
            if ($dist >= 3.0 * $atr14) $scoreRisk -= 5;
        }

        $scoreTotal = $scoreTrend + $scoreMomentum + $scoreVolume + $scoreBreakout + $scoreRisk;

        // ===== Decision final (disinkronkan dengan operasional buylist) =====
        // 1 False Breakout / Batal
        // 2 Hindari (trend chain tidak terpenuhi / data tidak cukup)
        // 3 Hati-hati (tunggu volume / overheat)
        // 4 Perlu Konfirmasi (trend+rsi+volume ok, belum breakout confirm)
        // 5 Layak Beli (trend+rsi+volume ok + breakout)
        $decisionCode = 2;

        if ($falseBreakout) {
            $decisionCode = 1;
        } else {
            // Jika MA chain belum kebentuk, jangan bikin seolah “jelek” tapi kita tetap set aman = Hindari
            if (!$trendOk) {
                $decisionCode = 2;
            } else {
                if (!$rsiOk) {
                    $decisionCode = 3;
                } else {
                    // di sini trend+rsi ok
                    if (!$volOk) {
                        $decisionCode = 3; // tunggu volume (biar gak muncul “Perlu Konfirmasi” tapi vol normal/lemah)
                    } else {
                        $decisionCode = $isBreakout ? 5 : 4;
                    }
                }
            }
        }

        return [
            'ticker_id'         => $tickerId,
            'trade_date'        => $date->toDateString(),

            'open'              => $openToday,
            'high'              => $highToday,
            'low'               => $lowToday,
            'close'             => $closeToday,
            'volume'            => $volToday,

            'ma20'              => $ma20,
            'ma50'              => $ma50,
            'ma200'             => $ma200,

            'vol_sma20'         => $volSma20Prev !== null ? round($volSma20Prev, 4) : null,
            'vol_ratio'         => $volRatio,

            'rsi14'             => $rsi14,
            'atr14'             => $atr14,

            'support_20d'       => $support20,
            'resistance_20d'    => $resist20,

            'decision_code'     => $decisionCode,
            'volume_label_code' => $volumeLabel,

            'score_total'       => (int) $scoreTotal,
            'score_trend'       => (int) $scoreTrend,
            'score_momentum'    => (int) $scoreMomentum,
            'score_volume'      => (int) $scoreVolume,
            'score_breakout'    => (int) $scoreBreakout,
            'score_risk'        => (int) $scoreRisk,

            'source'            => $source,
            'is_deleted'        => 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];
    }

    private function sma(array $values, int $n): ?float
    {
        $count = count($values);
        if ($count < $n) return null;
        $sum = 0.0;
        for ($i = $count - $n; $i < $count; $i++) $sum += $values[$i];
        return round($sum / $n, 4);
    }

    private function smaExcludeToday(array $values, int $n): ?float
    {
        $count = count($values);
        if ($count < ($n + 1)) return null;
        $sum = 0.0;
        for ($i = $count - 1 - $n; $i < $count - 1; $i++) $sum += $values[$i];
        return $sum / $n;
    }

    private function rollingMinExcludeToday(array $values, int $n): ?float
    {
        $count = count($values);
        if ($count < ($n + 1)) return null;
        $min = null;
        for ($i = $count - 1 - $n; $i < $count - 1; $i++) {
            $v = (float)$values[$i];
            $min = ($min === null) ? $v : min($min, $v);
        }
        return round($min, 4);
    }

    private function rollingMaxExcludeToday(array $values, int $n): ?float
    {
        $count = count($values);
        if ($count < ($n + 1)) return null;
        $max = null;
        for ($i = $count - 1 - $n; $i < $count - 1; $i++) {
            $v = (float)$values[$i];
            $max = ($max === null) ? $v : max($max, $v);
        }
        return round($max, 4);
    }

    private function rsiWilder(array $closes, int $period): ?float
    {
        $n = count($closes);
        if ($n < ($period + 1)) return null;

        $gains = 0.0;
        $losses = 0.0;

        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            if ($diff >= 0) $gains += $diff;
            else $losses += abs($diff);
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        for ($i = $period + 1; $i < $n; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $gain = $diff > 0 ? $diff : 0.0;
            $loss = $diff < 0 ? abs($diff) : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }

        if ($avgLoss == 0.0) return 100.0;

        $rs  = $avgGain / $avgLoss;
        $rsi = 100.0 - (100.0 / (1.0 + $rs));
        return round($rsi, 2);
    }

    private function atrWilder(array $highs, array $lows, array $closes, int $period): ?float
    {
        $n = count($closes);
        if ($n < ($period + 1)) return null;

        $trs = [];
        for ($i = 1; $i < $n; $i++) {
            $tr = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
            $trs[] = $tr;
        }

        if (count($trs) < $period) return null;

        $atr = array_sum(array_slice($trs, 0, $period)) / $period;

        for ($i = $period; $i < count($trs); $i++) {
            $atr = (($atr * ($period - 1)) + $trs[$i]) / $period;
        }

        return round($atr, 4);
    }

    /**
     * Map volRatio ke 10 label (lebih nyambung dengan list kamu).
     * Fokus operasional tetap: kandidat hanya label 8/9 (burst).
     */
    private function mapVolumeLabel(?float $volRatio): ?int
    {
        if ($volRatio === null) return null;

        if ($volRatio >= 3.0) return 8; // Climax / Euphoria
        if ($volRatio >= 2.0) return 7;  // Strong Burst / Breakout
        if ($volRatio >= 1.5) return 6;  // Volume Burst / Accumulation
        if ($volRatio >= 1.0) return 4;  // Normal
        if ($volRatio >= 0.7) return 3;  // Quiet
        if ($volRatio >= 0.4) return 2;  // Quiet/Normal – Volume lemah
        return 1; // Dormant (sangat sepi)
    }
}
