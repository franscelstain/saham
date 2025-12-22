<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScreenerComputeDaily extends Command
{
    protected $signature = 'screener:compute-daily
        {--date= : Tanggal EOD (YYYY-MM-DD). Default ambil MAX(trade_date) dari ticker_ohlc_daily}
        {--ticker= : Proses 1 ticker_code saja (contoh: BBCA)}
        {--source=yahoo : Isi field source (default: yahoo)}
        {--chunk=200 : Chunk tickers per batch}';

    protected $description = 'Compute daily indicators + signals + scores and upsert into ticker_indicators_daily';

    public function handle(): int
    {
        $date = $this->resolveDate();
        if (!$date) {
            $this->error('Tidak ada data di ticker_ohlc_daily.');
            return 1;
        }

        $tickerCode = $this->option('ticker');
        $source = (string) $this->option('source');
        $chunk = (int) $this->option('chunk');

        $this->info('Compute screener for date: ' . $date->toDateString());

        $tickersQuery = DB::table('tickers')
            ->select('ticker_id', 'ticker_code')
            ->where('is_deleted', 0);

        if ($tickerCode) {
            $tickersQuery->where('ticker_code', strtoupper(trim($tickerCode)));
        }

        $tickersQuery->orderBy('ticker_id')
            ->chunkById($chunk, function ($tickers) use ($date, $source) {
                $rows = [];
                $now = now();

                foreach ($tickers as $t) {
                    $row = $this->computeForTicker((int)$t->ticker_id, $date, $source, $now);
                    if ($row) {
                        $rows[] = $row;
                    }
                }

                if (!empty($rows)) {
                    DB::table('ticker_indicators_daily')->upsert(
                        $rows,
                        ['ticker_id', 'trade_date'],
                        [
                            'open','high','low','close','volume',
                            'ma20','ma50','ma200',
                            'vol_sma20','vol_ratio',
                            'rsi14','atr14',
                            'support_20d','resistance_20d',
                            'signal_code','volume_label_code',
                            'score_total','score_trend','score_momentum','score_volume','score_breakout','score_risk',
                            'source','is_deleted','updated_at'
                        ]
                    );
                }
            }, 'ticker_id');

        $this->info('Done.');
        return 0;
    }

    private function resolveDate(): ?Carbon
    {
        $opt = $this->option('date');
        if ($opt) {
            return Carbon::createFromFormat('Y-m-d', $opt)->startOfDay();
        }

        $max = DB::table('ticker_ohlc_daily')->max('trade_date');
        if (!$max) return null;

        return Carbon::parse($max)->startOfDay();
    }

    private function computeForTicker(int $tickerId, Carbon $date, string $source, $now): ?array
    {
        // Ambil window cukup buat MA200 + buffer hari libur
        $from = $date->copy()->subDays(420)->toDateString();
        $to = $date->toDateString();

        $ohlc = DB::table('ticker_ohlc_daily')
            ->select('trade_date','open','high','low','close','volume')
            ->where('ticker_id', $tickerId)
            ->where('is_deleted', 0)
            ->whereBetween('trade_date', [$from, $to])
            ->orderBy('trade_date', 'asc')
            ->get();

        if ($ohlc->isEmpty()) return null;

        // Pastikan ada baris untuk tanggal target
        $last = $ohlc->last();
        if ((string)$last->trade_date !== $to) {
            // belum ada EOD untuk tanggal tersebut
            return null;
        }

        $closes = [];
        $highs  = [];
        $lows   = [];
        $vols   = [];

        foreach ($ohlc as $r) {
            $closes[] = (float) $r->close;
            $highs[]  = (float) $r->high;
            $lows[]   = (float) $r->low;
            $vols[]   = (float) $r->volume;
        }

        $openToday  = (float) $last->open;
        $highToday  = (float) $last->high;
        $lowToday   = (float) $last->low;
        $closeToday = (float) $last->close;
        $volToday   = (int) $last->volume;

        $ma20  = $this->sma($closes, 20);
        $ma50  = $this->sma($closes, 50);
        $ma200 = $this->sma($closes, 200);

        $volSma20 = $this->sma($vols, 20);
        $volRatio = ($volSma20 && $volSma20 > 0) ? ($volToday / $volSma20) : null;

        // support/resistance: pakai 20 hari sebelum hari ini (exclude hari ini)
        $support20 = $this->rollingMinExcludeToday($lows, 20);
        $resist20  = $this->rollingMaxExcludeToday($highs, 20);

        $rsi14 = $this->rsiWilder($closes, 14);
        $atr14 = $this->atrWilder($highs, $lows, $closes, 14);

        // volume label (baseline; nanti kamu bisa tuning rule-nya)
        $volumeLabel = $this->mapVolumeLabel($volRatio);

        // signal + score (baseline default)
        $falseBreakout = ($resist20 !== null) && ($highToday > $resist20) && ($closeToday <= $resist20);

        $scoreTrend = 0;
        if ($ma20 !== null && $closeToday > $ma20) $scoreTrend += 10;
        if ($ma20 !== null && $ma50 !== null && $ma20 > $ma50) $scoreTrend += 10;
        if ($ma50 !== null && $ma200 !== null && $ma50 > $ma200) $scoreTrend += 10;

        $scoreMomentum = 0;
        if ($rsi14 !== null) {
            if ($rsi14 >= 50 && $rsi14 <= 70) $scoreMomentum += 10;
            elseif ($rsi14 > 70) $scoreMomentum -= 5;
            elseif ($rsi14 < 40) $scoreMomentum -= 5;
        }

        $scoreVolume = 0;
        if ($volRatio !== null) {
            if ($volRatio >= 1.5) $scoreVolume += 10;
            if ($volRatio >= 2.0) $scoreVolume += 10;
        }

        $scoreBreakout = 0;
        if ($falseBreakout) $scoreBreakout -= 20;
        elseif ($resist20 !== null && $closeToday > $resist20) $scoreBreakout += 15;

        $scoreRisk = 0;
        // contoh risk sederhana: makin dekat ke support (dibanding ATR) makin ok
        if ($support20 !== null && $atr14 !== null && $atr14 > 0) {
            $dist = $closeToday - $support20;
            if ($dist <= 1.0 * $atr14) $scoreRisk += 5;
            if ($dist >= 3.0 * $atr14) $scoreRisk -= 5;
        }

        $scoreTotal = $scoreTrend + $scoreMomentum + $scoreVolume + $scoreBreakout + $scoreRisk;

        // signal_code baseline:
        // 1 False Breakout/Batal
        // 2 Hati-hati
        // 3 Hindari
        // 4 Perlu Konfirmasi
        // 5 Layak Beli
        $signalCode = 4;
        if ($falseBreakout) {
            $signalCode = 1;
        } else {
            // hindari/hati-hati berdasarkan overheat sederhana
            if (($rsi14 !== null && $rsi14 >= 78) || $volumeLabel === 1) {
                $signalCode = 2; // hati-hati (climax/euphoria)
            }
            if ($ma20 !== null && $closeToday < $ma20) {
                $signalCode = 3; // hindari (trend lemah)
            }

            // layak beli
            $layak =
                ($ma20 !== null && $closeToday > $ma20) &&
                ($ma20 !== null && $ma50 !== null && $ma20 > $ma50) &&
                ($volRatio !== null && $volRatio >= 1.5) &&
                ($rsi14 !== null && $rsi14 >= 50 && $rsi14 <= 70);

            if ($layak) {
                $signalCode = 5;
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

            'vol_sma20'         => $volSma20,
            'vol_ratio'         => $volRatio,

            'rsi14'             => $rsi14,
            'atr14'             => $atr14,

            'support_20d'       => $support20,
            'resistance_20d'    => $resist20,

            'signal_code'       => $signalCode,
            'volume_label_code' => $volumeLabel,

            'score_total'       => $scoreTotal,
            'score_trend'       => $scoreTrend,
            'score_momentum'    => $scoreMomentum,
            'score_volume'      => $scoreVolume,
            'score_breakout'    => $scoreBreakout,
            'score_risk'        => $scoreRisk,

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
        $slice = array_slice($values, $count - $n, $n);
        return array_sum($slice) / $n;
    }

    private function rollingMinExcludeToday(array $values, int $n): ?float
    {
        $count = count($values);
        if ($count < ($n + 1)) return null; // butuh n hari sebelum hari ini
        $slice = array_slice($values, $count - 1 - $n, $n);
        return min($slice);
    }

    private function rollingMaxExcludeToday(array $values, int $n): ?float
    {
        $count = count($values);
        if ($count < ($n + 1)) return null;
        $slice = array_slice($values, $count - 1 - $n, $n);
        return max($slice);
    }

    // RSI Wilder smoothing (lebih standar daripada RSI SMA)
    private function rsiWilder(array $closes, int $period): ?float
    {
        $n = count($closes);
        if ($n < ($period + 1)) return null;

        $gains = 0.0;
        $losses = 0.0;

        // initial avg gain/loss
        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            if ($diff >= 0) $gains += $diff;
            else $losses += abs($diff);
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        // smooth sampai akhir (kita butuh last RSI)
        for ($i = $period + 1; $i < $n; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $gain = $diff > 0 ? $diff : 0.0;
            $loss = $diff < 0 ? abs($diff) : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }

        if ($avgLoss == 0.0) return 100.0;
        $rs = $avgGain / $avgLoss;
        $rsi = 100.0 - (100.0 / (1.0 + $rs));
        return round($rsi, 2);
    }

    // ATR Wilder smoothing
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

        // initial ATR = SMA TR
        $atr = array_sum(array_slice($trs, 0, $period)) / $period;

        // smooth
        for ($i = $period; $i < count($trs); $i++) {
            $atr = (($atr * ($period - 1)) + $trs[$i]) / $period;
        }

        return round($atr, 4);
    }

    private function mapVolumeLabel(?float $volRatio): ?int
    {
        if ($volRatio === null) return null;

        // baseline sederhana:
        // 1 = Climax/Euphoria (warning)
        // 9 = Strong Burst
        // 8 = Volume Burst
        // 6 = Normal
        // 2 = Quiet/weak
        if ($volRatio >= 3.0) return 1;
        if ($volRatio >= 2.0) return 9;
        if ($volRatio >= 1.5) return 8;
        if ($volRatio >= 1.0) return 6;
        return 2;
    }
}
