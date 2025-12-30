<?php

namespace App\Console\Commands;

use App\Repositories\IntradayRepository;
use App\Services\YahooIntradayService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IntradayCaptureCommand extends Command
{
    protected $signature = 'intraday:capture
        {--interval=1m : Yahoo interval}
        {--limit=150 : jumlah ticker per run}
        {--concurrency=15 : parallel request}
        {--ticker= : capture 1 ticker saja (manual)}
        {--force : jalankan walaupun di luar jam bursa}';

    protected $description = 'Capture intraday snapshot (round-robin slice + parallel)';

    public function handle(YahooIntradayService $svc, IntradayRepository $repo): int
    {
        $ticker      = $this->option('ticker');
        $interval    = (string) $this->option('interval');
        $limit       = max(1, (int) $this->option('limit'));
        $concurrency = max(1, (int) $this->option('concurrency'));
        $force       = (bool) $this->option('force');

        // Guard jam bursa (skip cepat biar Task Scheduler boleh running seharian)
        if (!$force && empty($ticker) && !$this->isTradingSessionNow()) {
            $this->line('Skip: outside trading sessions.');
            return self::SUCCESS;
        }

        // Lock anti overlap 50 detik (biar tidak numpuk kalau dipanggil tiap menit)
        // Pastikan CACHE_DRIVER=file atau redis, jangan array.
        if (!Cache::add('lock:intraday:capture', 1, 120)) {
            $this->warn('Skip: intraday capture masih berjalan (lock aktif).');
            return self::SUCCESS;
        }

        try {
            // Mode manual 1 ticker
            if (!empty($ticker)) {
                $stats = $svc->capture($ticker, $interval);
                $this->info(json_encode($stats));
                return self::SUCCESS;
            }

            $total = $repo->countActiveTickers();
            if ($total <= 0) {
                $this->warn('No active tickers.');
                return self::SUCCESS;
            }

            $offset = (int) Cache::get('intraday:capture:cursor', 0);
            if ($offset >= $total) $offset = 0;

            $stats = $svc->captureSlice($offset, $limit, $interval, $concurrency);

            $next = $offset + $limit;
            if ($next >= $total) $next = 0;
            Cache::put('intraday:capture:cursor', $next, 86400);

            $stats['total'] = $total;
            $stats['next_cursor'] = $next;
            $stats['ran_at_wib'] = Carbon::now('Asia/Jakarta')->toDateTimeString();

            $this->info(json_encode($stats));
            return self::SUCCESS;
        } finally {
            Cache::forget('lock:intraday:capture');
        }
    }

    /**
     * Jam bursa IDX (Pasar Reguler) WIB:
     * Senin-Kamis: 09:00-12:00 dan 13:30-15:50
     * Jumat:       09:00-11:30 dan 14:00-15:50
     *
     * Note: batas 15:50 dipakai sebagai cutoff operasional capture (mendekati close).
     */
    private function isTradingSessionNow(): bool
    {
        $now = Carbon::now('Asia/Jakarta');
        $dow = (int) $now->dayOfWeekIso; // 1=Mon ... 7=Sun
        if ($dow >= 6) return false; // Sabtu/Minggu

        // ✅ Cek hari bursa dari market_calendar (holiday / non-trading day)
        $today = $now->toDateString();
        if (!$this->isTradingDay($today)) {
            return false;
        }

        $hm = (int) $now->format('Hi'); // contoh 0930 => 930

        if ($dow <= 4) {
            // Senin-Kamis
            $inMorning   = ($hm >= 900 && $hm <= 1200);
            $inAfternoon = ($hm >= 1330 && $hm <= 1550);
        } else {
            // Jumat
            $inMorning   = ($hm >= 900 && $hm <= 1130);
            $inAfternoon = ($hm >= 1400 && $hm <= 1550);
        }

        return $inMorning || $inAfternoon;
    }

    private function isTradingDay(string $date): bool
    {
        // Cache biar nggak query DB tiap menit
        $key = "market_calendar:is_trading_day:{$date}";

        return (bool) Cache::remember($key, 86400, function () use ($date) {
            $val = DB::table('market_calendar')
                ->whereDate('cal_date', $date)
                ->value('is_trading_day');

            // Fallback permissive: kalau kalender belum ada untuk tanggal tsb, tetap anggap trading day
            // (supaya capture tetap jalan walau kalender belum diisi).
            if ($val === null) return true;



            return (int) $val === 1;
        });
    }
}