<?php

namespace App\Services;

use App\Repositories\ScreenerRepository;
use Carbon\Carbon;

class ScreenerService
{
    /** @var ScreenerRepository */
    private $repo;

    // ==============================
    // Konfigurasi operasional (swing mingguan)
    // ==============================

    // Kandidat EOD boleh dipakai maksimal N hari bursa setelah EOD.
    // Contoh: EOD Jumat -> valid Senin & Selasa (2 hari bursa).
    private const CANDIDATE_WINDOW_TRADING_DAYS = 2;

    // Jam entry (WIB)
    private const ENTRY_END_MON_WED      = '14:30';
    private const ENTRY_END_THURSDAY     = '12:00';
    private const ENTRY_END_FRIDAY       = '10:30'; // setelah ini: SKIP_DAY_FRIDAY (entry baru stop)

    // Filter intraday
    private const MIN_REL_VOL = 0.30; // wajib
    private const MIN_POS     = 0.55; // last minimal 55% dari range hari ini
    private const TOP_CHASE   = 0.80; // kalau last di top 20% range: jangan chase

    // Risk filters
    private const DEFAULT_RISK_PCT           = 0.01; // 1% modal per trade
    private const MAX_RISK_PCT_FROM_ENTRY    = 0.03; // (entry - SL) / entry <= 3%
    private const MIN_RR_TO_TP2              = 1.20; // RR minimal ke TP2

    // EOD trend/RSI filters
    private const REQUIRE_MA_STACK    = true;  // close > ma20 > ma50 > ma200
    private const RSI_MAX_HARD        = 75.0;  // hard max
    private const RSI_MAX_SOFT        = 70.0;  // prefer <= 70, 70-75 = minta pullback kecuali Strong Burst

    // Auto recommendation (opsional)
    private const MAX_POSITIONS_DEFAULT = 3;
    private const MIN_CAPITAL_PER_POS   = 1500000;
    private const CAPITAL_USAGE_PCT     = 0.95;

    // IDX lot
    private const LOT_SIZE = 100;

    public function __construct(ScreenerRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Halaman Candidates (EOD): signal (4,5) + volume_label (8,9).
     */
    public function getCandidatesPageData(?string $date = null): array
    {
        $tradeDate = $date ?: $this->repo->getLatestEodDate();

        if (!$tradeDate) {
            return [
                'trade_date' => null,
                'rows' => collect(),
            ];
        }

        $rows = $this->repo->getCandidatesByDate($tradeDate, [4, 5], [8, 9]);

        // Enforce juga di Candidates page biar transparan
        $rows = $rows->map(function ($c) {
            [$ok, $why, $sev] = $this->checkEodGuards($c);
            $c->eod_guard_ok = $ok ? 1 : 0;
            $c->eod_guard_reason = $why;
            $c->eod_guard_severity = $sev;
            $c->signal_name = $this->signalName((int) $c->signal_code);
            $c->volume_label_name = $this->volumeLabelName($c->volume_label_code !== null ? (int) $c->volume_label_code : null);
            return $c;
        });

        return [
            'trade_date' => $tradeDate,
            'rows' => $rows,
        ];
    }

    /**
     * Buylist operasional hari ini (intraday eksekusi).
     *
     * @param string|null $today   YYYY-mm-dd
     * @param float|null  $capital modal / buying power (untuk lot sizing & risk)
     */
    public function getTodayBuylistData(?string $today = null, ?float $capital = null): array
    {
        $today = $today ?: Carbon::now('Asia/Jakarta')->toDateString();

        $eodDate = $this->repo->getEodReferenceForToday($today);
        if (!$eodDate) {
            return [
                'today' => $today,
                'eod_date' => null,
                'capital' => $capital,
                'rows' => collect(),
            ];
        }

        $td  = Carbon::parse($today, 'Asia/Jakarta');
        $eod = Carbon::parse($eodDate, 'Asia/Jakarta');

        $expiryDateStr = $this->repo->getNthTradingDateAfter($eodDate, self::CANDIDATE_WINDOW_TRADING_DAYS);
        if (!$expiryDateStr) {
            $expiryDateStr = $this->addTradingDays($eod, self::CANDIDATE_WINDOW_TRADING_DAYS)->toDateString();
        }

        $expiry = Carbon::parse($expiryDateStr, 'Asia/Jakarta');

        // “now” WIB buat rule jam entry (operasional real time)
        $nowWib  = Carbon::now('Asia/Jakarta');
        $timeNow = $nowWib->format('H:i');

        // Kandidat EOD (repo sudah filter: signal (4,5) + volume_label (8,9))
        $candidates = $this->repo->getEodCandidates($eodDate);

        // Intraday (1 row per ticker_id + trade_date)
        $intraday = $this->repo->getLatestIntradayByDate($today)->keyBy('ticker_id');

        // Level EOD (minimal low kemarin)
        $levels   = $this->repo->getEodLevels($eodDate)->keyBy('ticker_id');

        // AvgVol20 (EOD)
        $avgVol20 = $this->repo->getAvgVol20ByEodDate($eodDate)->keyBy('ticker_id');

        $rows = $candidates->map(function ($c) use (
            $intraday, $levels, $avgVol20,
            $td, $expiry, $nowWib, $timeNow, $capital
        ) {
            $tid = (int) $c->ticker_id;

            $in = $intraday->get($tid);
            $lv = $levels->get($tid);
            $av = $avgVol20->get($tid);

            // ===== defaults output =====
            $status = 'WAIT';
            $reason = null;

            $priceOk = null;
            $posInRange = null;

            // ===== expiry kandidat (berdasarkan hari bursa, bukan ISO week) =====
            if ($td->gt($expiry)) {
                $status = 'EXPIRED';
                $reason = 'Lewat window kandidat ('.self::CANDIDATE_WINDOW_TRADING_DAYS.' hari bursa)';
            }

            // ===== EOD guards (close>ma20>ma50>ma200 & RSI<=75) =====
            if ($status !== 'EXPIRED') {
                [$eodOk, $eodWhy, $eodSev] = $this->checkEodGuards($c);
                $c->eod_guard_ok = $eodOk ? 1 : 0;
                $c->eod_guard_reason = $eodWhy;
                $c->eod_guard_severity = $eodSev;

                if (!$eodOk) {
                    if ($eodSev === 'HARD') {
                        $status = 'SKIP_EOD_GUARD';
                        $reason = $eodWhy;
                    } else {
                        // SOFT: jangan BUY dulu, tunggu pullback/konfirmasi
                        $status = 'WAIT_EOD_GUARD';
                        $reason = $eodWhy;
                    }
                }
            }

            // ===== ambil intraday =====
            $snapshotAt = $in->snapshot_at ?? null;
            $lastPrice  = $in->last_price ?? null;
            $volSoFar   = $in->volume_so_far ?? null;
            $openToday  = $in->open_price ?? null;
            $highToday  = $in->high_price ?? null;
            $lowToday   = $in->low_price ?? null;

            // ===== level EOD =====
            $eodLow = ($lv && $lv->low !== null) ? (float) $lv->low : null;

            // ===== relvol =====
            $avg20 = $av->avg_vol20 ?? null;
            $relvol = null;
            if ($volSoFar !== null && $avg20 !== null) {
                $relvol = $this->computeTimedRelVol((float)$volSoFar, (float)$avg20, $nowWib);
            }

            // =========================
            // Pipeline status (urut dari paling gating)
            // =========================
            if (!in_array($status, ['EXPIRED','SKIP_EOD_GUARD'], true)) {

                // 0) snapshot hari ini harus bener (hindari libur tapi data “kemarin” ke-upsert sebagai hari ini)
                if ($snapshotAt === null) {
                    $status = 'NO_INTRADAY';
                    $reason = 'Snapshot intraday belum ada';
                } else {
                    $snapDate = Carbon::parse($snapshotAt, 'Asia/Jakarta')->toDateString();
                    if ($snapDate !== $td->toDateString()) {
                        $status = 'STALE_INTRADAY';
                        $reason = 'Snapshot bukan tanggal hari ini (WIB), kemungkinan libur / belum capture';
                    }
                }

                // 1) data intraday harus lengkap
                if ($status === 'WAIT' || $status === 'WAIT_EOD_GUARD') {
                    if ($lastPrice === null || $volSoFar === null || $openToday === null || $highToday === null || $lowToday === null) {
                        $status = 'NO_INTRADAY';
                        $reason = 'Snapshot intraday belum lengkap';
                    }
                }

                // 2) aturan hari & jam (2 sesi, configurable)
                if ($status === 'WAIT' || $status === 'WAIT_EOD_GUARD') {

                    $sessions = $this->getSessionTimes();

                    // entry end by day (configurable)
                    $entryEndRaw = (string) config('screener.entry_end.mon_wed', self::ENTRY_END_MON_WED);
                    if ($nowWib->isThursday()) $entryEndRaw = (string) config('screener.entry_end.thu', self::ENTRY_END_THURSDAY);
                    if ($nowWib->isFriday())   $entryEndRaw = (string) config('screener.entry_end.fri', self::ENTRY_END_FRIDAY);

                    $entryEnd = $this->normalizeTimeHHMM($entryEndRaw) ?? $this->normalizeTimeHHMM(self::ENTRY_END_MON_WED) ?? '14:30';

                    // LOCK: entryEnd tidak boleh melewati sesi 2 end (biar tidak entry setelah market tutup)
                    $s2End = $this->normalizeTimeHHMM($sessions[1]['end'] ?? null);
                    if ($s2End !== null && $entryEnd > $s2End) {
                        $entryEnd = $s2End;
                    }
                    
                    // sebelum sesi 1 mulai => tunggu market buka
                    $s1Start = $sessions[0]['start'] ?? '09:00';
                    if ($timeNow < $s1Start) {
                        $status = 'WAIT_ENTRY_WINDOW';
                        $reason = 'Belum masuk jam bursa (mulai '.$s1Start.' WIB)';
                    }
                    // lewat batas entry harian => skip/late
                    elseif ($timeNow > $entryEnd) {
                        if ($nowWib->isFriday()) {
                            $status = 'SKIP_DAY_FRIDAY';
                            $reason = 'Jumat: entry lewat batas (maks '.$entryEnd.' WIB) (hindari gap weekend)';
                        } elseif ($nowWib->isThursday()) {
                            $status = 'SKIP_DAY_THURSDAY_LATE';
                            $reason = 'Kamis: entry lewat batas (maks '.$entryEnd.' WIB) biar tidak kebawa minggu depan';
                        } else {
                            $status = 'LATE_ENTRY';
                            $reason = 'Sudah lewat batas entry (maks '.$entryEnd.' WIB)';
                        }
                    }
                    // di luar sesi 1/2 => dianggap lunch / jeda antar sesi
                    elseif (!$this->isWithinAnySession($timeNow, $sessions)) {
                        $s1Start = $sessions[0]['start'] ?? '09:00';
                        $s1End   = $sessions[0]['end']   ?? '11:30';
                        $s2Start = $sessions[1]['start'] ?? '13:30';
                        $s2End   = $sessions[1]['end']   ?? '15:50';

                        // antara sesi 1 dan sesi 2 => break antar sesi
                        if ($timeNow > $s1End && $timeNow < $s2Start) {
                            $status = 'LUNCH_WINDOW';
                            $reason = "Break antar sesi (Sesi 2 mulai {$s2Start} WIB)";
                        }
                        // setelah sesi 2 tutup (walau masih <= entryEnd) => market udah ga liquid, tunggu besok
                        elseif ($timeNow > $s2End) {
                            $status = 'LATE_ENTRY';
                            $reason = "Market sudah tutup (Sesi 2 berakhir {$s2End} WIB)";
                        }
                        // sebelum sesi 1 start (sebenernya sudah di-handle sebelumnya, tapi aman)
                        else {
                            $status = 'WAIT_ENTRY_WINDOW';
                            $reason = "Belum masuk jam bursa (mulai {$s1Start} WIB)";
                        }
                    }
                }

                // 3) GAP DOWN guard (open < low kemarin)
                if ($status === 'WAIT' || $status === 'WAIT_EOD_GUARD') {
                    if ($eodLow !== null && (float)$openToday < $eodLow) {
                        $status = 'SKIP_GAP_DOWN';
                        $reason = 'Open hari ini di bawah Low kemarin';
                        $priceOk = false;
                    }
                }

                // 4) Breakdown guard (low/last < low kemarin)
                if ($status === 'WAIT' || $status === 'WAIT_EOD_GUARD') {
                    $priceOk = true;
                    if ($eodLow !== null) {
                        if ((float)$lowToday < $eodLow || (float)$lastPrice < $eodLow) {
                            $priceOk = false;
                        }
                    }

                    if (!$priceOk) {
                        $status = 'SKIP_BREAKDOWN';
                        $reason = 'Low/Last tembus low kemarin';
                    }
                }

                // 5) RelVol wajib
                if ($status === 'WAIT' || $status === 'WAIT_EOD_GUARD') {
                    if ($relvol === null || $relvol < self::MIN_REL_VOL) {
                        $status = 'WAIT_REL_VOL';
                        $reason = 'RelVol belum memenuhi';
                    }
                }

                // 6) Strength + anti-chase
                if ($status === 'WAIT' || $status === 'WAIT_EOD_GUARD') {
                    $range = (float)$highToday - (float)$lowToday;
                    if ($range > 0) {
                        $posInRange = (((float)$lastPrice - (float)$lowToday) / $range); // 0..1
                    }

                    if ((float)$lastPrice < (float)$openToday) {
                        $status = 'WAIT_STRENGTH';
                        $reason = 'Last masih di bawah Open hari ini';
                    } elseif ($posInRange !== null && $posInRange < self::MIN_POS) {
                        $status = 'WAIT_STRENGTH';
                        $reason = 'Posisi harga masih lemah di range';
                    } elseif ($posInRange !== null && $posInRange >= self::TOP_CHASE) {
                        $status = 'WAIT_PULLBACK';
                        $reason = 'Terlalu dekat High (rawan chase)';
                    } else {
                        // kalau masih WAIT_EOD_GUARD (soft), jangan langsung BUY
                        if ($status === 'WAIT_EOD_GUARD') {
                            $status = 'WAIT_PULLBACK';
                            // reason sudah dari guard
                        } else {
                            $status = 'BUY_OK';
                            $reason = 'Valid intraday';
                        }
                    }
                }
            }

            // ===== Isi field untuk UI (selalu isi, biar transparan) =====
            $c->snapshot_at   = $snapshotAt;

            $c->last_price    = $lastPrice;
            $c->vol_so_far    = $volSoFar;
            $c->avg_vol20     = $avg20;
            $c->relvol_today  = $relvol !== null ? round($relvol, 4) : null;

            $c->open_price    = $openToday;
            $c->high_price    = $highToday;
            $c->low_price     = $lowToday;
            $c->eod_low       = $eodLow;

            $c->price_ok      = ($priceOk === null) ? null : (($priceOk === true) ? 1 : 0);

            $c->pos_in_range  = $posInRange !== null ? round($posInRange * 100, 2) : null;

            // ===== Trade plan + risk filters =====
            if ($status === 'BUY_OK') {
                $plan = $this->buildTradePlan($c, $capital);
                foreach ($plan as $k => $v) {
                    $c->{$k} = $v;
                }

                if (($c->lots ?? 0) < 1) {
                    $status = 'CAPITAL_TOO_SMALL';
                    $reason = $c->plan_blocked_reason ?? 'Modal tidak cukup untuk 1 lot sesuai risk rule';
                }
            
                if ($status === 'BUY_OK') {
                    // Risk lebar? (entry-SL)/entry
                    if (isset($c->entry_ideal, $c->stop_loss) && (float)$c->entry_ideal > 0) {
                        $riskPctFromEntry = (((float)$c->entry_ideal - (float)$c->stop_loss) / (float)$c->entry_ideal);
                        if ($riskPctFromEntry > self::MAX_RISK_PCT_FROM_ENTRY) {
                            $status = 'RISK_TOO_WIDE';
                            $reason = 'Risk terlalu lebar (> '.(self::MAX_RISK_PCT_FROM_ENTRY * 100).'%)';
                        }
                    }

                    // RR minimal ke TP2
                    if ($status === 'BUY_OK' && isset($c->entry_ideal, $c->stop_loss, $c->tp2)) {
                        $risk   = max(0.0000001, (float)$c->entry_ideal - (float)$c->stop_loss);
                        $reward = max(0.0, (float)$c->tp2 - (float)$c->entry_ideal);
                        $rr = $reward / $risk;

                        if ($rr < self::MIN_RR_TO_TP2) {
                            $status = 'RR_TOO_LOW';
                            $reason = 'RR ke TP2 terlalu kecil (< '.self::MIN_RR_TO_TP2.')';
                        }
                    }

                    // BUY_PULLBACK variant (zona entry ideal)
                    if ($status === 'BUY_OK') {
                        if ($posInRange !== null && $posInRange >= 0.60 && $posInRange <= 0.75) {
                            $status = 'BUY_PULLBACK';
                            $reason = 'Valid intraday (zona pullback ideal)';
                        }
                    }
                }
            }

            // set final
            $c->status = $status;
            $c->reason = $reason;

            // RR(TP2) + Rank score
            $c->rr_tp2 = null;
            if (isset($c->entry_ideal, $c->stop_loss, $c->tp2)) {
                $risk   = max(0.0000001, (float)$c->entry_ideal - (float)$c->stop_loss);
                $reward = max(0.0, (float)$c->tp2 - (float)$c->entry_ideal);
                $c->rr_tp2 = round($reward / $risk, 3);
            }

            $c->rank_score = round($this->computeRankScore($c), 3);

            return $c;
        });

        // Sorting: rank_score tertinggi
        $rows = $rows->sortByDesc('rank_score')->values();

        return [
            'today' => $today,
            'eod_date' => $eodDate,
            'capital' => $capital,
            'rows' => $rows,
        ];
    }

    /**
     * (Opsional) Rekomendasi picks dari BUY_OK / BUY_PULLBACK berdasarkan modal.
     * Tidak mengubah fungsi existing, cuma tambahan biar controller bisa tampilkan “yang direkomendasikan”.
     */
    public function getTodayRecommendations(?string $today = null, ?float $capital = null, ?int $maxPositions = null): array
    {
        $data = $this->getTodayBuylistData($today, $capital);
        $rows = $data['rows'] ?? collect();

        $cands = $rows->filter(function ($r) {
            return in_array(($r->status ?? ''), ['BUY_OK','BUY_PULLBACK'], true);
        })->values();

        if ($cands->isEmpty()) {
            return [
                'today' => $data['today'] ?? ($today ?: date('Y-m-d')),
                'eod_date' => $data['eod_date'] ?? null,
                'capital' => $capital,
                'picks' => collect(),
                'note' => 'Tidak ada kandidat BUY_OK / BUY_PULLBACK saat ini.',
            ];
        }

        if ($capital === null || $capital <= 0) {
            // tanpa modal: kasih top 3 by rank_score saja
            return [
                'today' => $data['today'],
                'eod_date' => $data['eod_date'],
                'capital' => $capital,
                'picks' => $cands->take(3),
                'note' => 'Modal belum diisi, jadi ini top picks berdasarkan rank_score (tanpa sizing).',
            ];
        }

        $usage = $capital * self::CAPITAL_USAGE_PCT;
        $capByMin = (int) floor($usage / self::MIN_CAPITAL_PER_POS);
        $capByMin = max(1, $capByMin);

        $capMax = $maxPositions ?? self::MAX_POSITIONS_DEFAULT;
        $slots = min($capMax, $capByMin, $cands->count());

        // ambil top by rank_score
        $picks = $cands->take($slots)->values();

        // alokasi sederhana: 50% top1, sisanya rata
        $allocs = [];
        if ($slots === 1) {
            $allocs = [$usage];
        } else {
            $first = $usage * 0.50;
            $rest = $usage - $first;
            $each = $rest / ($slots - 1);

            $allocs[] = $first;
            for ($i=1; $i<$slots; $i++) $allocs[] = $each;
        }

        // build plan per pick dengan capital_alloc
        $picks = $picks->map(function ($r, $i) use ($allocs) {
            $alloc = $allocs[$i] ?? null;
            $r->capital_alloc = $alloc !== null ? round($alloc, 2) : null;

            $plan = $this->buildTradePlan($r, $alloc);
            foreach ($plan as $k => $v) {
                $r->{$k} = $v;
            }

            return $r;
        });

        return [
            'today' => $data['today'],
            'eod_date' => $data['eod_date'],
            'capital' => $capital,
            'picks' => $picks,
            'note' => "Rekomendasi {$slots} posisi (alokasi modal displit).",
        ];
    }

    // ==============================
    // Helpers
    // ==============================

    /**
     * Tambah N hari bursa (skip Sabtu/Minggu).
     * NOTE: tidak mempertimbangkan libur bursa nasional.
     */
    private function addTradingDays(Carbon $d, int $n): Carbon
    {
        $x = $d->copy();
        while ($n > 0) {
            $x->addDay();
            if (!$x->isWeekend()) $n--;
        }
        return $x;
    }

    /**
     * Compare time "H:i" (string). Inclusive.
     */
    private function isTimeBetween(string $time, string $start, string $end): bool
    {
        return ($time >= $start && $time <= $end);
    }

    /**
     * EOD guard:
     * - close > ma20 > ma50 > ma200 (wajib)
     * - RSI <= 75 (wajib)
     * - RSI 70-75: SOFT (minta pullback) kecuali Strong Burst (code 9)
     *
     * Return: [bool ok, string|null reason, 'HARD'|'SOFT'|null]
     */
    private function checkEodGuards($c): array
    {
        // ambil field dari row repo (pastikan select kolom ini di repo)
        $close = isset($c->close) ? (float)$c->close : null;
        $ma20  = isset($c->ma20) ? (float)$c->ma20 : null;
        $ma50  = isset($c->ma50) ? (float)$c->ma50 : null;
        $ma200 = isset($c->ma200) ? (float)$c->ma200 : null;
        $rsi   = isset($c->rsi14) ? (float)$c->rsi14 : null;

        $vlabel = isset($c->volume_label_code) ? (int)$c->volume_label_code : null;

        // MA stack
        if (self::REQUIRE_MA_STACK) {
            if ($close === null || $ma20 === null || $ma50 === null || $ma200 === null) {
                return [false, 'MA stack tidak lengkap (close/ma20/ma50/ma200 null)', 'HARD'];
            }
            if (!($close > $ma20 && $ma20 > $ma50 && $ma50 > $ma200)) {
                return [false, 'Tidak memenuhi: close > ma20 > ma50 > ma200', 'HARD'];
            }
        }

        // RSI
        if ($rsi === null) {
            return [false, 'RSI belum ada', 'HARD'];
        }
        if ($rsi > self::RSI_MAX_HARD) {
            return [false, 'RSI terlalu tinggi (> '.self::RSI_MAX_HARD.')', 'HARD'];
        }

        // SOFT zone 70-75
        if ($rsi > self::RSI_MAX_SOFT) {
            // Strong Burst (9) boleh lewat, Volume Burst (8) minta pullback
            if ($vlabel !== 9) {
                return [false, 'RSI 70-75: tunggu pullback/konfirmasi (bukan Strong Burst)', 'SOFT'];
            }
        }

        return [true, null, null];
    }

    /**
     * Ranking:
     * - BUY_OK / BUY_PULLBACK paling berat
     * - RR ke TP2 dominan
     * - RelVol penting
     * - Score_total pelengkap
     * - penalti risk_pct_real kalau modal ada dan risk kebesaran
     */
    private function computeRankScore($r): float
    {
        $status = $r->status ?? '';

        $statusWeight = 0.0;
        if (in_array($status, ['BUY_OK','BUY_PULLBACK'], true)) {
            $statusWeight = 1000.0;
        } elseif (in_array($status, [
            'WAIT_PULLBACK',
            'WAIT_STRENGTH',
            'WAIT_REL_VOL',
            'WAIT_ENTRY_WINDOW',
            'WAIT_EOD_GUARD',
            'LUNCH_WINDOW'
        ], true)) {
            $statusWeight = 200.0;
        } elseif (in_array($status, ['NO_INTRADAY','STALE_INTRADAY'], true)) {
            $statusWeight = 50.0;
        } else {
            $statusWeight = 0.0;
        }

        $relvol     = (float)($r->relvol_today ?? 0);
        $scoreTotal = (float)($r->score_total ?? 0);

        // pakai NET kalau ada, fallback ke gross
        $rrNet   = (float)($r->rr_net_tp2 ?? 0);
        $rrGross = (float)($r->rr_tp2 ?? 0);
        $rrUse   = $rrNet > 0 ? $rrNet : $rrGross;

        $profitPctNet = (float)($r->est_profit_pct_tp2 ?? 0);

        $rank = $statusWeight
            + (220.0 * $rrUse)
            + (80.0  * $profitPctNet)
            + (50.0  * min($relvol, 5))
            + (1.0   * $scoreTotal);

        // plan diblok -> buang jauh
        if (!empty($r->plan_blocked_reason)) {
            $rank -= 5000.0;
        }

        $riskPctReal = isset($r->risk_pct_real) ? (float)$r->risk_pct_real : null;
        if ($riskPctReal !== null) {
            if ($riskPctReal > 0.015) $rank -= 300.0;
            elseif ($riskPctReal > 0.012) $rank -= 150.0;
        }

        return $rank;
    }

    /**
     * Trade plan + lot sizing (kalau modal ada).
     */
    private function buildTradePlan($c, ?float $capital = null): array
    {
        $last = $c->last_price !== null ? (float)$c->last_price : null;
        $open = $c->open_price !== null ? (float)$c->open_price : null;
        $high = $c->high_price !== null ? (float)$c->high_price : null;
        $low  = $c->low_price !== null ? (float)$c->low_price : null;

        $eodLow = $c->eod_low !== null ? (float)$c->eod_low : null;
        $atr    = isset($c->atr14) && $c->atr14 !== null ? (float)$c->atr14 : null;

        $volLabel = isset($c->volume_label_code) ? (int)$c->volume_label_code : 0;

        if ($last === null) return [];

        $range = ($high !== null && $low !== null) ? max(0.0, $high - $low) : 0.0;

        // Entry ideal: hindari chase
        $entry = $last;
        if ($range > 0) {
            $topBand = $low + 0.80 * $range;
            if ($last >= $topBand) {
                $entry = $low + 0.65 * $range;
            }
        } elseif ($open !== null) {
            $entry = $open;
        }

         // ===== APPLY TICK (IDX) =====
        // Entry: round down biar realistis (nggak overpay)
        $entry = $this->roundDownToTick($entry);
        $entry = $this->clampMinTick($entry);

        // SL swing-safe: struktur kemarin
        if ($eodLow !== null) $sl = $eodLow;
        elseif ($low !== null) $sl = $low;
        else $sl = $entry * 0.98;

        if ($sl >= $entry) $sl = $entry * 0.98;

        // SL: round down (lebih longgar, sesuai struktur support)
        $sl = $this->roundDownToTick($sl);
        $sl = $this->clampMinTick($sl);

        // guard ulang setelah tick: SL harus < entry
        if ($sl >= $entry) {
            $sl = $this->prevTickDown($entry);
            $sl = $this->clampMinTick($sl);
        }

        $res20 = isset($c->resistance_20d) && $c->resistance_20d !== null ? (float)$c->resistance_20d : null;

        $tp1 = $entry * 1.03;

        // === TP2 adaptif: pakai baseline + resistance_20d + ATR ===
        $tp2Candidates = [];

        // baseline weekly target (wajib supaya array tidak kosong)
        $tp2Candidates[] = $entry * 1.05;

        // ATR-based target (contoh 2x ATR)
        if ($atr !== null && $atr > 0) {
            $tp2Candidates[] = $entry + (2.0 * $atr);
        }

        // resistance 20d sebagai target realistis (kalau di atas entry)
        if ($res20 !== null && $res20 > $entry) {
            $tp2Candidates[] = $res20;
        }

        // pilih yang paling realistis (paling dekat) supaya sering kena dalam minggu yang sama
        $tp2 = min($tp2Candidates);

        // TP: round down supaya target lebih realistis sering kena
        $tp1 = $this->roundDownToTick($tp1);
        $tp2 = $this->roundDownToTick($tp2);

        $tp1 = $this->clampMinTick($tp1);
        $tp2 = $this->clampMinTick($tp2);

        // guard setelah tick
        if ($tp1 <= $entry) {
            $tp1 = $this->nextTickUp($entry);
        }

        $minTp2 = $this->nextTickUp($entry * 1.05);
        if ($tp2 < $minTp2) {
            $tp2 = $minTp2;
        }
        
        if ($tp2 < $tp1) {
            $tp2 = $tp1;
        }

        // ===== sizing =====
        $riskPct = self::DEFAULT_RISK_PCT;
        $riskPerShare = max(0.0, $entry - $sl);

        $lots = null;
        $riskBudget = null;
        $estRisk = null;
        $estCost = null;
        $riskPctReal = null;
        $planBlockedReason = null;

        if ($capital !== null && $capital > 0 && $riskPerShare > 0) {
            $riskBudget = $capital * $riskPct;

            $maxSharesByRisk = (int) floor($riskBudget / $riskPerShare);
            $maxSharesByCash = (int) floor($capital / $entry);

            $shares = min($maxSharesByRisk, $maxSharesByCash);
            $lots = (int) floor($shares / self::LOT_SIZE);

            if ($lots < 1) {
                // Tidak cukup untuk entry sesuai risk/cash -> blok
                $lots = 0;
                $estCost = null;
                $estRisk = null;
                $riskPctReal = null;
                $planBlockedReason = 'Modal/risk budget tidak cukup untuk 1 lot sesuai aturan risk';
            } else {
                $estCost = $lots * self::LOT_SIZE * $entry;
                $estRisk = $lots * self::LOT_SIZE * $riskPerShare;
                $riskPctReal = $estRisk / $capital;
                $planBlockedReason = null;
            }
        }

        $hasLots = is_int($lots) && $lots > 0;

        // ===== fees + break-even (NET) =====
        $feeBuyRate  = null;
        $feeSellRate = null;     // keep untuk backward compat (isi = TP2)

        $feeSellRateTp1 = null;
        $feeSellRateTp2 = null;
        $feeSellRateBe  = null;

        $breakEven = null;

        $estFeeBuy = null;
        $estOutTotal = null;

        $estFeeSellTp1 = null;
        $estFeeSellTp2 = null;

        $estProfitTp1 = null;
        $estProfitTp2 = null;

        // NET metrics (buat ranking & UI)
        $estProfitPctTp2 = null; // profit TP2 / total out (buy+fee)
        $rrNetTp2 = null;        // net reward TP2 / gross risk

        if ($hasLots) {
            $shares = $lots * self::LOT_SIZE;
            $notionalBuy = $shares * $entry;

            // BUY rate harus based on notional BUY (entry)
            [$feeBuyRate, $feeSellRateBe] = $this->resolveFeeRates($notionalBuy);

            // Break-even: sell rate bisa berubah tergantung tier notional SELL.
            // Iterasi 1–2x biar tier-nya akurat di boundary.
            $breakEven = $this->breakEvenSellPrice($entry, $feeBuyRate, $feeSellRateBe);
            for ($iter = 0; $iter < 2; $iter++) {
                $notionalBeSell = $shares * $breakEven;
                [, $sellRateNew] = $this->resolveFeeRates($notionalBeSell);

                if (abs($sellRateNew - $feeSellRateBe) < 1e-12) {
                    break;
                }

                $feeSellRateBe = $sellRateNew;
                $breakEven = $this->breakEvenSellPrice($entry, $feeBuyRate, $feeSellRateBe);
            }

            // SELL rate harus based on notional SELL (TP1 / TP2)
            [, $feeSellRateTp1] = $this->resolveFeeRates($shares * $tp1);
            [, $feeSellRateTp2] = $this->resolveFeeRates($shares * $tp2);

            // Keep key lama: fee_sell_rate = rate yang dipakai TP2 (paling relevan)
            $feeSellRate = $feeSellRateTp2;

            // BUY side (fee buy tetap berdasarkan notional BUY)
            $estFeeBuy   = $notionalBuy * $feeBuyRate;
            $estOutTotal = $notionalBuy + $estFeeBuy;

            // TP1 / TP2 net profit (pakai sellRate masing-masing)
            [$estFeeSellTp1, $estProfitTp1] = $this->estimateSellFeeAndNetProfit(
                $shares, $entry, $tp1, $feeBuyRate, $feeSellRateTp1
            );

            [$estFeeSellTp2, $estProfitTp2] = $this->estimateSellFeeAndNetProfit(
                $shares, $entry, $tp2, $feeBuyRate, $feeSellRateTp2
            );

            // ==== NET metrics (untuk ranking & transparansi UI) ====
            if ($estOutTotal !== null && $estOutTotal > 0 && $estProfitTp2 !== null) {
                $estProfitPctTp2 = $estProfitTp2 / $estOutTotal; // contoh 0.05 = 5% net
            }

            // RR NET: reward(net TP2) / risk(gross entry->SL)
            if ($estProfitTp2 !== null) {
                $riskGross = max(0.0000001, ($entry - $sl) * $shares);
                $rrNetTp2 = $estProfitTp2 / $riskGross;
            }
        }

        // ===== buy steps =====        
        $buyType  = 'Sekali';
        $buySteps = '100% di '.(int)$entry;

        if ($volLabel === 8) {
            $buyType = 'Bertahap (2x)';

            $e1 = $entry;
            if ($atr !== null && $atr > 0) $e2 = $e1 - 0.30 * $atr;
            elseif ($range > 0)            $e2 = $e1 - 0.20 * $range;
            else                          $e2 = $e1;

            if ($eodLow !== null) $e2 = max($e2, $eodLow);

            // apply tick untuk step entry
            $e1 = $this->roundDownToTick($e1);
            $e2 = $this->roundDownToTick($e2);

            $e1 = $this->clampMinTick($e1);
            $e2 = $this->clampMinTick($e2);

            // jaga jangan sampai e2 > e1 setelah rounding
            if ($e2 > $e1) $e2 = $e1;

            if ($lots === 0) {
                // plan blocked -> jangan tampilkan lot
                $buySteps = "60% di ".(int)$e1." | 40% di ".(int)$e2;
            } elseif ($hasLots) {                
                // pastikan pembagian valid
                if ($lots === 1) {
                    $buyType  = 'Sekali';
                    $buySteps = "1 lot di ".(int)$e1;
                } else {
                    $lot1 = (int) floor($lots * 0.60);
                    $lot2 = $lots - $lot1;

                    if ($lot1 < 1) { $lot1 = 1; $lot2 = $lots - 1; }
                    if ($lot2 < 1) { $lot2 = 1; $lot1 = $lots - 1; }
                
                    $buySteps = "{$lot1} lot di ".(int)$e1." | {$lot2} lot di ".(int)$e2;
                }
            } else {
                $buySteps = "60% di ".(int)$e1." | 40% di ".(int)$e2;
            }
        } else {
            // non-burst
            if ($lots === 0) {
                $buySteps = "100% di ".(int)$entry; // plan blocked -> persen saja
            } elseif ($hasLots) {
                $buySteps = "{$lots} lot di ".(int)$entry;
            } else {
                $buySteps = "100% di ".(int)$entry;
            }
        }

        // ===== sell steps =====
        $sellType = 'Bertahap (2x)';
        if ($hasLots) {
            if ($lots === 1) {
                $sellType  = 'Sekali';
                $sellSteps = "Jual 1 lot di ".(int)$tp2
                    ." | Setelah TP1: SL naik ke Entry | Time stop: T+2 belum +2% → keluar";
            } else {
                $s1 = (int) floor($lots * 0.50);
                $s1 = max(1, min($lots - 1, $s1)); // pastikan s2 minimal 1
                $s2 = $lots - $s1;

                $sellSteps = "Jual {$s1} lot di ".(int)$tp1
                    ." | Jual {$s2} lot di ".(int)$tp2
                    ." | Setelah TP1: SL naik ke Entry | Time stop: T+2 belum +2% → keluar";
            } 
        } else {
            $sellSteps = "Jual 50% di ".(int)$tp1
                ." | Jual 50% di ".(int)$tp2
                ." | Setelah TP1: SL naik ke Entry | Time stop: T+2 belum +2% → keluar";
        }

        return [
            'entry_ideal' => (int)$entry,
            'stop_loss'   => (int)$sl,
            'tp1'         => (int)$tp1,
            'tp2'         => (int)$tp2,

            'buy_type'      => $buyType,
            'buy_steps'     => $buySteps,
            'sell_type'     => $sellType,
            'sell_steps'    => $sellSteps,

            'lots'          => $lots,
            'est_cost'      => $estCost !== null ? round($estCost, 2) : null,
            'est_risk'      => $estRisk !== null ? round($estRisk, 2) : null,
            'risk_pct'      => $riskPct,
            'risk_pct_real' => $riskPctReal !== null ? round($riskPctReal, 4) : null,
            'risk_per_share'=> round($riskPerShare, 4),
            'risk_budget'   => $riskBudget !== null ? round($riskBudget, 2) : null,
            'plan_blocked_reason' => $planBlockedReason,

            'fee_buy_rate'       => $feeBuyRate,
            'fee_sell_rate'      => $feeSellRate,      // tetap ada (TP2)
            'fee_sell_rate_tp1'  => $feeSellRateTp1,
            'fee_sell_rate_tp2'  => $feeSellRateTp2,
            'fee_sell_rate_be'   => $feeSellRateBe,
            'break_even'         => $breakEven !== null ? (int)$breakEven : null,

            'est_fee_buy'       => $estFeeBuy !== null ? round($estFeeBuy, 2) : null,
            'est_out_total'     => $estOutTotal !== null ? round($estOutTotal, 2) : null,

            'est_fee_sell_tp1'  => $estFeeSellTp1 !== null ? round($estFeeSellTp1, 2) : null,
            'est_fee_sell_tp2'  => $estFeeSellTp2 !== null ? round($estFeeSellTp2, 2) : null,

            'est_profit_tp1'    => $estProfitTp1 !== null ? round($estProfitTp1, 2) : null,
            'est_profit_tp2'    => $estProfitTp2 !== null ? round($estProfitTp2, 2) : null,

            // NET metrics
            'est_profit_pct_tp2' => $estProfitPctTp2 !== null ? round($estProfitPctTp2, 6) : null,
            'rr_net_tp2'         => $rrNetTp2 !== null ? round($rrNetTp2, 4) : null,
        ];
    }

    private function computeTimedRelVol(float $volSoFar, float $avgVol20, Carbon $nowWib): ?float
    {
        if ($avgVol20 <= 0) return null;

        $minRatio = (float) config('screener.relvol_min_time_ratio', 0.05);

        $sessions = $this->getSessionTimes();

        if (empty($sessions)) return null;

        // Build session boundaries for "today" in WIB
        $bounds = [];
        foreach ($sessions as $s) {
            if (!is_array($s) || empty($s['start']) || empty($s['end'])) continue;

            $st = $nowWib->copy()->setTimeFromTimeString($s['start']);
            $en = $nowWib->copy()->setTimeFromTimeString($s['end']);

            if ($en->lte($st)) continue;

            $bounds[] = [$st, $en, $st->diffInMinutes($en)];
        }

        if (empty($bounds)) return null;

        // If before first session start
        $firstStart = $bounds[0][0];
        if ($nowWib->lt($firstStart)) return 0.0;

        // Total active minutes
        $totalActive = 0;
        foreach ($bounds as $b) $totalActive += $b[2];
        $totalActive = max(1, $totalActive);

        // If after last session end -> full day relvol
        $lastEnd = $bounds[count($bounds) - 1][1];
        if ($nowWib->gte($lastEnd)) {
            return $volSoFar / $avgVol20;
        }

        // Elapsed active minutes (exclude gaps automatically)
        $elapsedActive = 0;

        foreach ($bounds as [$st, $en, $mins]) {
            if ($nowWib->lt($st)) {
                // haven't started this session
                break;
            }

            if ($nowWib->gte($en)) {
                // finished this session
                $elapsedActive += $mins;
                continue;
            }

            // within this session
            $elapsedActive += max(1, $st->diffInMinutes($nowWib));
            break;
        }

        $elapsedActive = max(1, $elapsedActive);

        $ratioTime = $elapsedActive / $totalActive;       // 0..1
        $ratioTime = min(1.0, max($minRatio, $ratioTime));

        $expected  = $avgVol20 * $ratioTime;
        if ($expected <= 0) return null;

        $relvol = $volSoFar / $expected;

        // Optional clamp max
        $maxRel = config('screener.relvol_max', null);
        if ($maxRel !== null && is_numeric($maxRel)) {
            $relvol = min((float)$maxRel, $relvol);
        }

        return $relvol;
    }

    public function signalName(int $code): string
    {
        switch ($code) {
            case 1: return 'False Breakout / Batal';
            case 2: return 'Hati - Hati';
            case 3: return 'Hindari';
            case 4: return 'Perlu Konfirmasi';
            case 5: return 'Layak Beli';
            default: return 'Unknown';
        }
    }

    public function volumeLabelName(?int $code): string
    {
        if ($code === null) return '-';
        switch ($code) {
            case 1:  return 'Climax / Euphoria – hati-hati';
            case 2:  return 'Quiet/Normal – Volume lemah';
            case 3:  return 'Ultra Dry';
            case 4:  return 'Dormant';
            case 5:  return 'Quiet';
            case 6:  return 'Normal';
            case 7:  return 'Early Interest';
            case 8:  return 'Volume Burst / Accumulation';
            case 9:  return 'Strong Burst / Breakout';
            case 10: return 'Climax / Euphoria';
            default: return '-';
        }
    }

    private function idxTickSize(float $price): float
    {
        // hardening: kalau input aneh, jangan bikin tick 0/negatif
        if (!is_finite($price) || $price <= 0) return 1;

        if ($price < 200)  return 1;
        if ($price < 500)  return 2;
        if ($price < 2000) return 5;
        if ($price < 5000) return 10;
        return 25;
    }

    private function roundDownToTick(float $price): float
    {
        if (!is_finite($price) || $price <= 0) return 0.0;

        $tick = $this->idxTickSize($price);
        if ($tick <= 0) return $price;

        return floor($price / $tick) * $tick;
    }

    private function roundUpToTick(float $price): float
    {
        if (!is_finite($price) || $price <= 0) return 0.0;

        $tick = $this->idxTickSize($price);
        if ($tick <= 0) return $price;

        return ceil($price / $tick) * $tick;
    }

    private function nextTickUp(float $price): float
    {
        if (!is_finite($price) || $price <= 0) return 1.0;
        // +1 supaya boundary tick dihitung ulang dengan benar
        return $this->roundUpToTick($price + 1);
    }

    private function prevTickDown(float $price): float
    {
        if (!is_finite($price) || $price <= 0) return 0.0;
        // -1 supaya boundary tick dihitung ulang dengan benar
        return $this->roundDownToTick($price - 1);
    }

    private function clampMinTick(float $price): float
    {
        if (!is_finite($price) || $price <= 0) return 0.0;
        return max($price, $this->idxTickSize($price));
    }

    private function resolveFeeRates(?float $notionalBuy = null): array
    {
        // fallback kalau config belum ada
        $defaultBuy  = (float) config('screener.fee_buy_rate', 0.001513);
        $defaultSell = (float) config('screener.fee_sell_rate', 0.002513);

        $broker = (string) config('screener.broker', 'ajaib');
        $tiers  = config("screener.fees.{$broker}", null);

        if (!is_array($tiers)) {
            return [$defaultBuy, $defaultSell];
        }

        // ambil tier list (yang punya max/buy/sell)
        $tierList = [];
        foreach ($tiers as $k => $v) {
            if (is_array($v) && isset($v['max'], $v['buy'], $v['sell'])) {
                $tierList[] = $v;
            }
        }

        if (empty($tierList)) {
            return [$defaultBuy, $defaultSell];
        }

        usort($tierList, fn($a,$b) => (float)$a['max'] <=> (float)$b['max']);

        // kalau notional belum ada (mis. lots null), pakai tier pertama
        if ($notionalBuy === null || $notionalBuy <= 0) {
            $t = $tierList[0];
            return [(float)$t['buy'], (float)$t['sell']];
        }

        // pilih tier berdasar notional
        foreach ($tierList as $t) {
            if ($notionalBuy <= (float)$t['max']) {
                return [(float)$t['buy'], (float)$t['sell']];
            }
        }

        // fallback tier terakhir
        $t = end($tierList);
        return [(float)$t['buy'], (float)$t['sell']];
    }

    private function breakEvenSellPrice(float $entry, float $feeBuyRate, float $feeSellRate): float
    {
        $den = max(0.0000001, 1.0 - $feeSellRate);
        $be  = $entry * (1.0 + $feeBuyRate) / $den;

        // BE harus valid tick & aman (round up)
        $be = $this->roundUpToTick($be);
        $be = $this->clampMinTick($be);
        return $be;
    }

    private function estimateSellFeeAndNetProfit(
        int $shares,
        float $entry,
        float $sellPrice,
        float $feeBuyRate,
        float $feeSellRate
    ): array {
        $buyNotional  = $shares * $entry;
        $buyFee       = $buyNotional * $feeBuyRate;
        $outTotal     = $buyNotional + $buyFee;

        $sellNotional = $shares * $sellPrice;
        $sellFee      = $sellNotional * $feeSellRate;
        $inNet        = $sellNotional - $sellFee;

        $profit = $inNet - $outTotal;

        return [$sellFee, $profit];
    }

    private function getSessionTimes(): array
    {
        $s = (array) config('screener.sessions', []);

        $s1 = $s['session1'] ?? ['start' => '09:00', 'end' => '11:30'];
        $s2 = $s['session2'] ?? ['start' => '13:30', 'end' => '15:50'];

        // fallback kalau config rusak
        $s1Start = $this->normalizeTimeHHMM($s1['start'] ?? null) ?? '09:00';
        $s1End   = $this->normalizeTimeHHMM($s1['end'] ?? null)   ?? '11:30';
        $s2Start = $this->normalizeTimeHHMM($s2['start'] ?? null) ?? '13:30';
        $s2End   = $this->normalizeTimeHHMM($s2['end'] ?? null)   ?? '15:50';

        return [
            ['start' => $s1Start, 'end' => $s1End],
            ['start' => $s2Start, 'end' => $s2End],
        ];
    }

    private function isWithinAnySession(string $timeNow, array $sessions): bool
    {
        foreach ($sessions as $s) {
            $start = $s['start'] ?? null;
            $end   = $s['end'] ?? null;
            if (!$start || !$end) continue;

            if ($this->isTimeBetween($timeNow, $start, $end)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeTimeHHMM($t): ?string
    {
        if (!is_string($t)) return null;

        // trim + toleransi format "9:00" / "09:00"
        $t = trim($t);

        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            return null;
        }

        $h = (int) $m[1];
        $i = (int) $m[2];

        if ($h < 0 || $h > 23 || $i < 0 || $i > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $i);
    }
}
