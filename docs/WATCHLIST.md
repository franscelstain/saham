# TradeAxis Watchlist – Design Spec (EOD-driven)  
File: `WATCHLIST.md`

> Tujuan watchlist di TradeAxis **bukan “menentukan beli”**, tapi:
> - Memilih kandidat paling kuat berdasarkan data yang tersedia.
> - Memberi **analisa kuat** + **saran jam beli** yang realistis (window), termasuk **jam yang sebaiknya dihindari**.
> - Menjelaskan **alasan** secara ringkas dan bisa diaudit.
> - Menyimpan “daftar saran & alasan” agar bisa dievaluasi ulang (post-mortem).

Watchlist di bawah ini **berbasis data EOD** sebagai sumber utama, namun boleh memakai:
- Perhitungan turunan dari OHLC (ATR, gap risk, candle structure, dv20, dll).
- Konteks market (IHSG regime, breadth).
- Kalender bursa (hari kerja, libur).
- (Opsional) snapshot intraday ringan (opening range) jika suatu saat dibutuhkan untuk meningkatkan akurasi timing.

---

## 1) Output yang harus dihasilkan watchlist

### 1.1 Output global (per hari)
- `trade_date`
- `dow` (Mon/Tue/Wed/Thu/Fri)
- `market_regime` (risk-on / neutral / risk-off)
- `market_notes` (contoh: “IHSG down 5D”, “breadth lemah”)

### 1.2 Output per kandidat (per ticker)
Minimal field yang harus ada dalam hasil watchlist:

**A. Identitas & skor**
- `ticker_id`, `ticker_code`
- `rank` (1..N)
- `watchlist_score` (0–100)
- `confidence` (High/Med/Low)
- `setup_type` (Breakout / Pullback / Continuation / Reversal / Base)
- `reason_codes[]` (list kode alasan yang deterministik)

**B. Saran eksekusi (timing & risk)**
- `entry_windows[]` (mis. `["09:20-10:30", "13:35-14:30"]`)
- `avoid_windows[]` (mis. `["09:00-09:15", "15:50-close"]`)
- `entry_style` (Breakout-confirm / Pullback-wait / Reversal-confirm / No-trade)
- `size_multiplier` (1.0 / 0.8 / 0.6 / 0.3)
- `max_positions_today` (1 atau 2, bergantung hari)
- `risk_notes` (1–2 kalimat, bukan essay)

**C. “Checklist sebelum buy” (untuk meyakinkan)**
Walau berbasis EOD, watchlist harus selalu memberi checklist minimal:
- `pre_buy_checklist[]` contoh:
  1) “Spread rapat, bid/ask padat (cek top-5)”
  2) “Tidak gap-up terlalu jauh dari close kemarin”
  3) “Ada follow-through, bukan spike 1 menit”
  4) “Stoploss level jelas (support/MA/ATR)”

> Catatan: checklist ini bukan memerlukan intraday data di sistem. Ini “human checklist” agar saran watchlist realistis.

---

## 2) Filosofi desain: EOD-driven tapi realistis soal jam beli

Karena watchlist menggunakan EOD, watchlist **tidak boleh** bilang “beli jam 09:07 pasti benar”.
Yang benar:
- Saran “jam beli” harus berupa **window** (rentang waktu).
- Setiap window harus punya **alasan** yang diturunkan dari metrik EOD + konteks market.
- Watchlist harus bisa bilang: **“Tidak disarankan entry hari ini”** walau ada kandidat (NO TRADE).

---

## 3) Data yang dibutuhkan (akurat & audit-able)

### 3.1 Data mentah wajib (ticker_ohlc_daily)
Per `ticker_id + trade_date`:
- `open`, `high`, `low`, `close`, `volume`

Wajib ada constraint:
- unik `(ticker_id, trade_date)`
- validasi: `low <= open/close <= high`
- `volume >= 0`

### 3.2 Hasil compute EOD wajib (ticker_indicators_daily)
Minimal:
- `ma20`, `ma50`, `ma200`
- `rsi14`
- `vol_sma20`, `vol_ratio`
- `signal_code`, `signal_label`
- `signal_first_seen_date`, `signal_age_days` (streak)

### 3.3 Perhitungan tambahan (sangat disarankan) – murah tapi meningkatkan akurasi
> Semua bisa dihitung dari OHLC (tanpa intraday).

**A. Volatilitas & gap risk**
- `prev_close`
- `gap_pct = (open - prev_close)/prev_close`
- `tr` (true range)
- `atr14`
- `atr_pct = atr14/close`
- `range_pct = (high-low)/close`

**B. Candle structure (jebakan open vs continuation)**
- `body_pct = abs(close-open)/(high-low)`
- `upper_wick_pct`, `lower_wick_pct`
- flag: `is_long_upper_wick`, `is_long_lower_wick`, `is_inside_day`, `is_engulfing`

**C. Likuiditas proxy (untuk masalah “match/antrian”)**
- `dvalue = close * volume`
- `dv20 = SMA20(dvalue)`
- `liq_bucket` (A/B/C berdasarkan dv20)

### 3.4 Konteks market wajib
**market_calendar**
- `cal_date`, `is_trading_day`, `holiday_name`

**market_index_daily** (minimal IHSG)
- `trade_date`
- `close`, `ret_1d`, `ret_5d`
- `ma20`, `ma50` (opsional tapi disarankan)
- `regime` (risk-on/neutral/risk-off)

**market_breadth_daily** (opsional tapi kuat)
- `advancers`, `decliners`
- `new_high_20d`, `new_low_20d`

### 3.5 Corporate actions (agar indikator tidak “palsu”)
Jika memungkinkan:
- `is_adjusted`, `split_factor` / `adj_factor`
Minimal:
- flag “data discontinuity suspected” untuk outlier extreme.

---

## 4) Pipeline pembuatan watchlist (SRP + performa)

### Step 0 – Ingestion OHLC
- Import OHLC EOD dari sumber(s).
- Normalisasi tanggal & ticker.
- Validasi data (range, null, duplikat).

### Step 1 – ComputeEOD (per hari / per rentang)
Menghasilkan `ticker_indicators_daily` + kolom turunan penting:
- MA/RSI/vol_ratio
- signal_code/label
- fitur candle
- ATR/gap/dv20 (jika diputuskan dihitung di compute-eod)

> Prinsip: **watchlist tidak menghitung berat**. Watchlist hanya membaca hasil yang sudah dihitung.

### Step 2 – ComputeMarketContext (per hari)
Menghasilkan:
- market regime (IHSG + breadth)
- hari dalam minggu (dow)
- special flags (dekat libur, setelah libur)

### Step 3 – WatchlistBuild (per hari)
Input:
- `ticker_indicators_daily` (trade_date)
- `ticker_ohlc_daily` (trade_date)
- `market_index_daily` (trade_date)
- `market_calendar` (trade_date)

Output:
- `watchlist_daily` (per hari) + `watchlist_candidates` (per ticker)

---

## 5) Candidate selection (filter awal)

### 5.1 Hard filters (buang kandidat yang tidak layak dipertimbangkan)
Contoh:
- Data tidak lengkap untuk indikator (ma/vol_sma/atr)
- Likuiditas terlalu rendah: `dv20 < threshold` (mis. bucket C) → bisa “watch only”
- Harga ekstrem / outlier yang terindikasi corporate action belum adjusted → skip

### 5.2 Soft filters (boleh lolos tapi confidence turun)
- `atr_pct` terlalu tinggi (saham liar)
- Candle EOD “long upper wick” + close jauh dari high (indikasi distribusi)
- RSI terlalu tinggi (overheated) → entry harus pullback/wait

---

## 6) Scoring model (deterministik & audit-able)

### 6.1 Komponen skor (contoh)
Total 100 poin:
- Trend quality (0–30)
  - MA alignment (MA20>MA50>MA200), slope proxy, close di atas MA
- Momentum/candle (0–20)
  - close_to_high, body_pct, breakout structure
- Volume & liquidity (0–20)
  - vol_ratio, dv20 bucket
- Risk quality (0–20)
  - atr_pct, gap risk, range_pct
- Market alignment (0–10)
  - IHSG regime, breadth

> Setiap komponen harus menghasilkan “reason code” saat memberi atau mengurangi poin.

### 6.2 Confidence mapping
- High: score ≥ 82 dan tidak kena red-flag risk
- Med: score 72–81 atau ada 1 red-flag minor
- Low: score < 72 atau red-flag risk dominan

---

## 7) Setup type classification (berbasis EOD)
Watchlist harus mengklasifikasi kandidat agar timing advice masuk akal.

Contoh mapping:
- **Breakout / Strong Burst**
  - close near high, vol_ratio tinggi, break resistance/HHV, trend mendukung
- **Pullback**
  - trend bagus tapi close pullback ke MA20/MA50/support
- **Continuation**
  - sinyal konsisten beberapa hari (signal_age_days), trend kuat, volatilitas wajar
- **Reversal**
  - downtrend pendek + reversal candle + volume naik
- **Base**
  - range sempit, volatilitas turun, volume mulai naik tapi belum breakout

Output:
- `setup_type`
- `setup_notes` (singkat)

---

## 8) Engine “Jam beli” (EOD → window + larangan jam + alasan)

### 8.1 Default window (paling sering)
- `entry_windows`: `["09:20-10:30", "13:35-14:30"]`
- `avoid_windows`: `["09:00-09:15", "11:45-12:00", "15:50-close"]`

> Ini baseline. Lalu watchlist menggeser window berdasarkan risk & setup.

### 8.2 Day-of-week adjustment (weekly swing)
- **Senin**: lebih banyak fake move → prefer entry sedikit lebih siang
  - entry_windows: `["09:35-11:00", "13:35-14:30"]`
  - max_positions_today: 1
  - size_multiplier: 0.6–0.8
- **Selasa**: window terbaik (sweet spot)
  - entry_windows: `["09:20-10:30", "13:35-14:30"]`
  - max_positions_today: 1–2
  - size_multiplier: 1.0
- **Rabu**: late entry → hanya yang sudah “jalan”
  - entry_windows: `["09:30-10:45", "13:35-14:15"]`
  - max_positions_today: 1
  - size_multiplier: 0.7–0.9
- **Kamis**: sangat selektif
  - max_positions_today: 1
  - size_multiplier: 0.4–0.6
- **Jumat**: default NO TRADE, kecuali mode “carry” yang sangat ketat
  - max_positions_today: 0 (atau 1 dengan size_multiplier 0.2–0.4 dan reason khusus)

### 8.3 Risk-driven window shifting (berdasarkan metrik EOD)
Gunakan rules berikut (contoh):

**Rule: Gap risk tinggi**
- Kondisi: `gap_pct` historis besar atau `atr_pct` tinggi atau market risk-off
- Aksi:
  - tambah avoid: `["pre-open", "09:00-09:30"]`
  - entry_windows geser lebih siang: `["09:45-11:15", "13:45-14:30"]`
- Reason codes: `GAP_RISK_HIGH`, `VOLATILITY_HIGH`

**Rule: Likuiditas rendah (dv20 kecil)**
- Aksi:
  - hindari open & mendekati close (spread/antrian)
  - entry_windows: `["10:00-11:30", "13:45-14:30"]`
- Reason: `LIQ_LOW_MATCH_RISK`

**Rule: Breakout kuat**
- Aksi:
  - entry window tetap pagi setelah tenang: `["09:20-10:15"]`
  - checklist menekankan follow-through & tidak gap-up jauh
- Reason: `BREAKOUT_SETUP`

**Rule: Pullback**
- Aksi:
  - entry window lebih fleksibel, tunggu stabil: `["09:35-11:00", "13:35-14:45"]`
- Reason: `PULLBACK_SETUP`

**Rule: Reversal**
- Aksi:
  - hindari pagi awal; butuh konfirmasi: `["10:00-11:30", "13:45-14:30"]`
- Reason: `REVERSAL_CONFIRM`

---

## 9) “Daftar saran & alasan” yang disimpan (wajib)

Watchlist harus menyimpan teks/JSON yang ringkas tapi kaya makna.
Tujuannya: kamu bisa lihat kembali kenapa watchlist menyarankan jam tertentu.

### 9.1 Reason codes (contoh)
- `TREND_STRONG`
- `MA_ALIGN_BULL`
- `VOL_RATIO_HIGH`
- `BREAKOUT_CONF_BIAS`
- `PULLBACK_ENTRY_BIAS`
- `REVERSAL_RISK`
- `GAP_RISK_HIGH`
- `VOLATILITY_HIGH`
- `LIQ_LOW_MATCH_RISK`
- `MARKET_RISK_OFF`
- `LATE_WEEK_ENTRY_PENALTY`

### 9.2 Saran jam & larangan jam (disimpan sebagai data)
- `entry_windows` (array)
- `avoid_windows` (array)
- `timing_summary` (1 kalimat, contoh: “Hindari open karena gap/ATR tinggi; entry terbaik setelah 09:45 ketika spread stabil.”)

### 9.3 Checklist sebelum buy (disimpan sebagai array)
Minimal 3 item per kandidat. Template item bisa reusable.

---

## 10) Top picks vs kandidat lain (3 rekomendasi vs sisanya)
Watchlist menampilkan:
- **Top Picks (mis. 3 ticker)**: yang skor tertinggi & lolos quality gate.
- **Secondary Candidates**: skor bagus tapi ada warning (low liq, risk tinggi, market risk-off, dll).
- **NO TRADE**: jika market regime buruk atau semua kandidat gagal quality gate.

Selain ranking, watchlist harus memberi saran portofolio harian:
- **BUY 1 ONLY** (100%) bila pick #1 jauh lebih kuat (gap skor besar)
- **BUY 2 SPLIT** (70/30 atau 60/40) bila top 2 sama kuat dan likuid
- **NO TRADE** bila risk dominan

---

## 11) Data yang mungkin “belum ada” tapi sebaiknya disediakan
Untuk akurasi timing advice tanpa intraday:
1) `atr14`, `atr_pct`
2) `gap_pct` + `prev_close`
3) `dv20` (close*volume rolling)
4) candle metrics (wick/body)
5) IHSG regime (ret_1d/ret_5d + MA)
6) signal_age_days & signal_first_seen_date (streak)

Opsional upgrade akurasi timing:
- snapshot intraday ringan (opening range 15 menit) → bukan full intraday.

---

## 12) Skema tabel output watchlist (saran)

### 12.1 watchlist_daily
- `trade_date` (PK)
- `dow`
- `market_regime`
- `market_ret_1d`, `market_ret_5d` (opsional)
- `notes` (text pendek)
- `created_at`

### 12.2 watchlist_candidates
PK: `(trade_date, ticker_id)`
- `rank`
- `watchlist_score`
- `confidence`
- `setup_type`
- `entry_windows` (json)
- `avoid_windows` (json)
- `size_multiplier`
- `max_positions_today`
- `reason_codes` (json)
- `timing_summary` (text pendek)
- `pre_buy_checklist` (json)
- `created_at`

Index penting:
- `(trade_date, rank)`
- `(trade_date, watchlist_score desc)`
- `(trade_date, setup_type)`

---

## 13) Contoh output (1 kandidat)

**Ticker: ABCD**
- setup_type: Breakout
- score: 86 (High)
- entry_windows: ["09:20-10:15", "13:35-14:15"]
- avoid_windows: ["09:00-09:15", "15:50-close"]
- size_multiplier: 1.0 (Selasa)
- reason_codes:
  - TREND_STRONG, MA_ALIGN_BULL, VOL_RATIO_HIGH, BREAKOUT_CONF_BIAS
- timing_summary:
  - “Breakout kuat; hindari open untuk mengurangi spike risk. Entry terbaik setelah 09:20 saat spread stabil.”
- checklist:
  1) “Jangan buy jika gap-up terlalu jauh dari close kemarin”
  2) “Pastikan follow-through, bukan spike 1 menit”
  3) “Spread rapat, bid/ask padat”

---

## 14) Quality assurance (QA) & audit
Agar watchlist bisa dipercaya:
- Simpan input snapshot untuk kandidat top picks (opsional):
  - nilai indikator utama di hari itu (ma, rsi, vol_ratio, atr_pct, dv20)
- Log reason codes dan scoring breakdown
- Buat laporan mingguan:
  - berapa pick yang “jadi bergerak sesuai rencana”
  - berapa yang “false breakout”
  - apakah timing windows perlu disetel ulang

---

## 15) Roadmap (opsional)
Tahap 1 (EOD-only):
- Implement semua metrik turunan + market regime
- Implement reason_codes + timing windows

Tahap 2 (semi-intraday ringan):
- Tambah opening range 15m (1 record per ticker per hari)
- Timing rule jadi lebih tajam (mis. “OR breakout valid/invalid”)

Tahap 3 (intraday penuh, bila dibutuhkan):
- Hanya untuk kandidat saja (tiering), bukan 900 ticker.

---

## 16) Checklist implementasi cepat
1) Pastikan kolom-kolom wajib ada (lihat bagian 3 & 11).
2) Putuskan: ATR/gap/dv20 dihitung di ComputeEOD atau job terpisah (disarankan di ComputeEOD).
3) Tambah tabel market context (IHSG minimal).
4) Bangun scoring + reason codes.
5) Bangun timing engine (window + avoid) berbasis setup_type + risk + dow + market regime.
6) Simpan output watchlist ke DB agar “daftar saran” bisa diarsipkan.

---

## 17) Catatan keras (biar tidak salah arah)
- Watchlist harus berani output: **NO TRADE**.
- Watchlist harus selalu mengeluarkan **alasan** yang bisa diuji (reason codes), bukan opini.
- Jam beli yang disaranin harus bisa dijelaskan dari:
  - setup_type (EOD),
  - risk metrics (ATR/gap/dv20/candle),
  - day-of-week,
  - market regime.

---

## 18) UI Output Spec (kandidat vs recommended pick)

UI kamu (sesuai mockup) sudah tepat: **semua kandidat tetap punya kartu data** yang konsisten, lalu **khusus recommended pick** ditambah strategi pembelian lengkap (allocation + trade plan + lots).

### 18.1 Field yang tampil untuk *setiap kandidat* (baseline card)

**OHLC**
- `open`, `high`, `low`, `close`

**Market**
- `rel_vol` (RelVol / vol_ratio)
- `pos_pct` (Pos% / posisi close terhadap range atau terhadap MA—definisikan konsisten)
- `eod_low` (low EOD / level risiko reference)
- `price_ok` (boolean; lolos price filter)

**Plan (ringkas, untuk kandidat biasa)**
- `entry` (tipe entry ringkas: breakout/pullback/reversal/watch-only)
- `sl` (jika sudah bisa dihitung dari EOD; kalau tidak, tampil “TBD” + reason)
- `tp1`, `tp2` (opsional untuk kandidat biasa; minimal tampil “target zone”)
- `out` (exit rule ringkas / invalidation)
- `buy_steps` (untuk kandidat biasa: bisa “single entry” / “wait retest”)
- `lots` (untuk kandidat biasa: boleh kosong jika modal belum dimasukkan)
- `est_cost` (boleh kosong jika modal belum dimasukkan)

**Risk / Result**
- `rr` (RR ke TP1 atau RR utama)
- `risk_pct` (risk% dari modal—jika sizing aktif)
- `profit_tp2_net` (jika sizing aktif + fee diset)
- `rr_tp2` dan/atau `rr_tp2_net`

**Meta**
- `rank`
- `snapshot_at`
- `last_bar_at` (tanggal bar terakhir yang dipakai)

**Reason**
- ringkasan + reason codes (lihat Section 9)

> Catatan: untuk kandidat biasa, “Plan” boleh lebih ringkas (entry + invalidation + risk note).
> Tapi **field set-nya tetap sama** supaya UI konsisten.

### 18.2 Tambahan khusus untuk *recommended pick*
Recommended pick harus menampilkan **strategi eksekusi yang bisa dipakai**:
- Alokasi beli (BUY 1 / BUY 2 split / BUY 3 small / NO TRADE)
- Entry price (trigger/range), SL, TP1, TP2, BE, trailing, buy steps
- Lots + estimasi biaya berdasarkan modal user
- RR, risk%, profit net (opsional, tapi ideal)

---

## 19) Portfolio Allocation Engine (Top 3 → BUY 0/1/2 + %)

Bagian ini mengubah “Top 3 pick” menjadi **keputusan portofolio harian** yang realistis untuk weekly swing:
- beli 1 saja, atau beli 2 split, atau tidak beli sama sekali.
- outputnya juga mengatur “size multiplier” berdasar hari (Mon/Tue/Wed/Thu/Fri) dan kondisi market.

### 19.1 Output yang disimpan (per hari)
- `trade_plan_mode`: `NO_TRADE | BUY_1 | BUY_2_SPLIT | BUY_3_SMALL | CARRY_ONLY`
- `max_positions_today`
- `allocations`: array object `{ticker_id, ticker_code, alloc_pct, alloc_amount?}`
- `capital_total` (jika user memasukkan modal; kalau tidak ada, simpan null)
- `risk_per_trade_pct` (default config, mis. 0.5%–1.0%)

### 19.2 Rule deterministik untuk memilih BUY_1 / BUY_2 / NO_TRADE
**NO_TRADE**
- `market_regime == risk-off` (atau breadth jelek + index down) DAN/ATAU
- semua kandidat gagal quality gate (liq sangat rendah / ATR terlalu tinggi / gap risk ekstrem / data incomplete)

**BUY_1**
- `score1 - score2 >= gap_threshold` (mis. 8–10) ATAU
- pick #2/#3 punya red-flag (liq bucket C, volatility ekstrem, gap risk tinggi)
- atau modal kecil sehingga diversifikasi justru bikin eksekusi jelek

**BUY_2_SPLIT**
- top2 lolos quality gate, confidence minimal `Med`, dan gap skor kecil
- (opsional) beda sektor untuk menghindari korelasi tinggi

**BUY_3_SMALL** (jarang)
- semua top3 confidence High, likuiditas bagus, market risk-on

### 19.3 Aturan split % (langsung keluar angka)
- default: `70/30` jika score1 > score2 cukup jelas
- `60/40` jika skor sangat dekat
- `50/30/20` untuk BUY_3_SMALL

> Semua split harus tercatat di `allocations[]`.

---

## 20) Trade Plan Engine (Entry, SL, TP1, TP2, BE, Out) – Top picks & kandidat lain

Engine ini menghasilkan level-level plan **berbasis EOD**, bukan prediksi intraday.
Karena watchlist EOD-only, entry harus berupa:
- **trigger** (breakout) atau
- **range limit** (pullback) atau
- **confirm trigger** (reversal).

### 20.1 Output yang disimpan (per kandidat)
- `entry_type`: `BREAKOUT_TRIGGER | PULLBACK_LIMIT | REVERSAL_CONFIRM | WATCH_ONLY`
- `entry_trigger_price` atau `entry_limit_low/high`
- `stop_loss_price`
- `tp1_price`, `tp2_price`
- `be_price` (break-even rule; biasanya = entry setelah TP1)
- `out_rule` (invalid if / exit rule ringkas)
- `buy_steps` (mis. “60% on trigger, 40% on retest”)
- `rr_tp1`, `rr_tp2` (gross)
- (opsional) `rr_tp2_net`, `profit_tp2_net` jika fee aktif

### 20.2 Data tambahan yang wajib disediakan agar plan akurat
Selain OHLC + MA/RSI/vol_ratio, plan butuh:
- `prev_close`, `prev_high`, `prev_low`
- `atr14`, `atr_pct`
- `hhv20`, `llv20` (atau minimal highest/lowest N days)
- `tick_size` (fraksi harga sesuai price band; untuk “+1 tick” yang benar)
- (opsional) `support_level`, `resistance_level` dari swing detection
- `fee_buy`, `fee_sell` (opsional untuk net profit)

### 20.3 Formula plan per setup_type (contoh deterministik)
Gunakan tick rounding setiap kali menghasilkan harga.

**A) Breakout / Strong Burst**
- Entry: `trigger = max(prev_high, hhv20) + 1_tick`
- Buy steps: `60% on trigger`, `40% on retest (optional)`
- SL: `min(prev_low, trigger - 1.0*ATR)` (pilih yang paling “logis & ketat”)
- TP1: `entry + 1R`
- TP2: `entry + 2R` (atau target weekly +4%/+5% jika kamu mau mode itu)
- BE: setelah TP1 tercapai, `SL = entry` (atau `entry + 0.2R`)
- Out: invalid jika `gap_up > x*ATR` atau close jatuh kembali di bawah level breakout

**B) Pullback (uptrend)**
- Entry: `limit_range = [MA20 - 0.2ATR, MA20 + 0.3ATR]` (contoh; tune)
- SL: di bawah support/MA (atau `entry - 1ATR`)
- TP1: ke `prev_high` atau `entry + 1R`
- TP2: `entry + 2R`
- Out: batal jika breakdown support jelas (close < support/MA dengan range besar)

**C) Reversal**
- Entry: confirm `trigger = prev_high + 1_tick` (setelah reversal candle EOD)
- SL: `swing_low` atau `llvN` (N=5/10)
- TP1/TP2: konservatif: `1R` dan `2R`
- Out: jika gagal follow-through (kembali close di bawah area reversal)

**D) Base / Sideways**
- Default: `WATCH_ONLY` sampai breakout trigger valid
- Entry/SL/TP mengikuti breakout rule saat trigger terjadi

> Kandidat non-top pick tetap dibuatkan plan, tapi bisa diberi:
> - `entry_type = WATCH_ONLY` atau
> - `size_multiplier` kecil
> agar tidak mendorong overtrading.

---

## 21) Position Sizing Engine (Modal → Lots)

Ini yang membuat watchlist bisa bilang “beli berapa lots” saat user memasukkan modal.

### 21.1 Input
- `capital_total` (modal user)
- `alloc_pct` per ticker (hasil Section 19)
- `entry_price`, `stop_loss_price`
- `risk_per_trade_pct` (default config)
- `lot_size = 100` (IDX)
- (opsional) `fee_buy`, `fee_sell`

### 21.2 Output per kandidat (khususnya recommended pick)
- `alloc_amount`
- `lots_recommended`
- `est_cost` (≈ entry * lots * 100 + fee_buy)
- `max_loss_if_sl` (≈ (entry - sl) * lots * 100 + fee)
- `risk_pct` (max_loss_if_sl / capital_total)

### 21.3 Formula sizing (deterministik)
- `risk_budget = capital_total * risk_per_trade_pct`
- `risk_per_share = entry - sl`
- `shares_by_risk = floor(risk_budget / risk_per_share)`
- `shares_by_alloc = floor((capital_total * alloc_pct) / entry)`
- `shares_final = min(shares_by_risk, shares_by_alloc)`
- `lots = floor(shares_final / 100)`

Rules:
- jika `lots == 0` → ticker otomatis menjadi `WATCH_ONLY` (atau alokasi dialihkan)
- jika `risk_per_share <= 0` → plan invalid (data/level salah) → jangan trade
- jika `risk_pct` melewati batas config → turunkan lots atau ubah entry (tunggu pullback)

---

## 22) Integration Notes (supaya cepat & SRP tetap rapi)
- **ComputeEOD**: hitung semua indikator + level-level plan (ATR, hhv/llv, wick/body, dv20).
- **MarketContext job**: IHSG regime + breadth + kalender.
- **WatchlistBuild**: scoring + ranking + setup_type + timing windows + reason codes.
- **TradePlanBuild** (bisa bagian dari watchlist build): entry/SL/TP/BE/out + rr.
- **PositionSizing**: hanya jalan kalau user memasukkan `capital_total` (atau ada default dari profile).

Semua output disimpan di DB agar UI bisa menampilkan kartu kandidat seperti mockup, dan recommended pick punya strategi eksekusi lengkap.
