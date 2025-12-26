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
    private const ENTRY_START            = '09:20';
    private const ENTRY_END_MON_WED      = '14:30';
    private const ENTRY_END_THURSDAY     = '12:00';
    private const ENTRY_END_FRIDAY       = '10:30'; // setelah ini: SKIP_DAY_FRIDAY (entry baru stop)

    // Window rawan (WIB)
    private const LUNCH_START = '11:30';
    private const LUNCH_END   = '13:00';

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
        $today = $today ?: date('Y-m-d');

        $eodDate = $this->repo->getLatestEodDate();
        if (!$eodDate) {
            return [
                'today' => $today,
                'eod_date' => null,
                'capital' => $capital,
                'rows' => collect(),
            ];
        }

        $td     = Carbon::parse($today);
        $eod    = Carbon::parse($eodDate);
        $expiry = $this->addTradingDays($eod, self::CANDIDATE_WINDOW_TRADING_DAYS);

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
            if ($volSoFar !== null && $avg20 !== null && (float)$avg20 > 0) {
                $relvol = (float)$volSoFar / (float)$avg20;
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

                // 2) aturan hari & jam (disiplin swing mingguan)
                if ($status === 'WAIT' || $status === 'WAIT_EOD_GUARD') {
                    $entryEnd = self::ENTRY_END_MON_WED;
                    if ($nowWib->isThursday()) $entryEnd = self::ENTRY_END_THURSDAY;
                    if ($nowWib->isFriday())   $entryEnd = self::ENTRY_END_FRIDAY;

                    if ($timeNow < self::ENTRY_START) {
                        $status = 'WAIT_ENTRY_WINDOW';
                        $reason = 'Belum masuk jam entry (mulai '.self::ENTRY_START.' WIB)';
                    } elseif ($this->isTimeBetween($timeNow, self::LUNCH_START, self::LUNCH_END)) {
                        $status = 'LUNCH_WINDOW';
                        $reason = 'Jam rawan ('.self::LUNCH_START.'-'.self::LUNCH_END.' WIB), tunggu selesai';
                    } elseif ($timeNow > $entryEnd) {
                        if ($nowWib->isFriday()) {
                            $status = 'SKIP_DAY_FRIDAY';
                            $reason = 'Jumat: entry lewat batas (maks '.self::ENTRY_END_FRIDAY.' WIB) (hindari gap weekend)';
                        } elseif ($nowWib->isThursday()) {
                            $status = 'SKIP_DAY_THURSDAY_LATE';
                            $reason = 'Kamis: entry lewat batas (maks '.self::ENTRY_END_THURSDAY.' WIB) biar tidak kebawa minggu depan';
                        } else {
                            $status = 'LATE_ENTRY';
                            $reason = 'Sudah lewat batas entry (maks '.$entryEnd.' WIB)';
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

            $c->price_ok      = ($priceOk === true) ? 1 : 0;
            $c->pos_in_range  = $posInRange !== null ? round($posInRange * 100, 2) : null;

            // ===== Trade plan + risk filters =====
            if ($status === 'BUY_OK') {
                $plan = $this->buildTradePlan($c, $capital);
                foreach ($plan as $k => $v) {
                    $c->{$k} = $v;
                }

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
        } elseif (in_array($status, ['WAIT_PULLBACK','WAIT_STRENGTH','WAIT_REL_VOL','WAIT_ENTRY_WINDOW','LUNCH_WINDOW'], true)) {
            $statusWeight = 200.0;
        } elseif (in_array($status, ['NO_INTRADAY','STALE_INTRADAY'], true)) {
            $statusWeight = 50.0;
        } else {
            $statusWeight = 0.0;
        }

        $relvol     = (float)($r->relvol_today ?? 0);
        $scoreTotal = (float)($r->score_total ?? 0);
        $rr         = (float)($r->rr_tp2 ?? 0);

        $rank = $statusWeight
            + (200.0 * $rr)
            + (50.0 * min($relvol, 5))
            + (1.0 * $scoreTotal);

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

        // SL swing-safe: struktur kemarin
        if ($eodLow !== null) $sl = $eodLow;
        elseif ($low !== null) $sl = $low;
        else $sl = $entry * 0.98;

        if ($sl >= $entry) $sl = $entry * 0.98;

        $tp1 = $entry * 1.03;
        $tp2 = $entry * 1.05;

        // ===== sizing =====
        $riskPct = self::DEFAULT_RISK_PCT;
        $riskPerShare = max(0.0, $entry - $sl);

        $lots = null;
        $riskBudget = null;
        $estRisk = null;
        $estCost = null;
        $riskPctReal = null;

        if ($capital !== null && $capital > 0 && $riskPerShare > 0) {
            $riskBudget = $capital * $riskPct;

            $maxSharesByRisk = (int) floor($riskBudget / $riskPerShare);
            $maxSharesByCash = (int) floor($capital / $entry);

            $shares = min($maxSharesByRisk, $maxSharesByCash);
            $lots = (int) floor($shares / self::LOT_SIZE);

            if ($lots < 1) $lots = 1;

            $estCost = $lots * self::LOT_SIZE * $entry;
            $estRisk = $lots * self::LOT_SIZE * $riskPerShare;
            $riskPctReal = $estRisk / $capital;
        }

        // ===== buy steps =====
        $buyType  = 'Sekali';
        $buySteps = '100% di '.round($entry, 4);

        if ($volLabel === 8) {
            $buyType = 'Bertahap (2x)';

            $e1 = $entry;
            if ($atr !== null && $atr > 0) $e2 = $e1 - 0.30 * $atr;
            elseif ($range > 0)            $e2 = $e1 - 0.20 * $range;
            else                          $e2 = $e1;

            if ($eodLow !== null) $e2 = max($e2, $eodLow);

            if ($lots !== null) {
                $lot1 = (int) floor($lots * 0.60);
                $lot2 = $lots - $lot1;
                if ($lot1 < 1) { $lot1 = 1; $lot2 = max(0, $lots - 1); }

                $buySteps = "{$lot1} lot di ".round($e1, 4)." | {$lot2} lot di ".round($e2, 4);
            } else {
                $buySteps = "60% di ".round($e1, 4)." | 40% di ".round($e2, 4);
            }
        } else {
            if ($lots !== null) {
                $buySteps = "{$lots} lot di ".round($entry, 4);
            }
        }

        // ===== sell steps =====
        $sellType = 'Bertahap (2x)';
        if ($lots !== null) {
            $s1 = (int) max(1, floor($lots * 0.50));
            $s2 = $lots - $s1;

            $sellSteps = "Jual {$s1} lot di ".round($tp1, 4)
                ." | Jual {$s2} lot di ".round($tp2, 4)
                ." | Setelah TP1: SL naik ke Entry | Time stop: T+2 belum +2% → keluar";
        } else {
            $sellSteps = "Jual 50% di ".round($tp1, 4)
                ." | Jual 50% di ".round($tp2, 4)
                ." | Setelah TP1: SL naik ke Entry | Time stop: T+2 belum +2% → keluar";
        }

        return [
            'entry_ideal'   => round($entry, 4),
            'stop_loss'     => round($sl, 4),
            'tp1'           => round($tp1, 4),
            'tp2'           => round($tp2, 4),

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
        ];
    }
}
