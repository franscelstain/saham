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
- `trade_date` *(mengikuti effective date Market Data: cutoff + trading day)*
- `dow` (Mon/Tue/Wed/Thu/Fri)
- `market_regime` (risk-on / neutral / risk-off)
- `market_notes` (contoh: “IHSG down 5D”, “breadth lemah”)

### 1.1.1 Data freshness gate (wajib)
Watchlist ini **EOD-driven**. Karena itu, rekomendasi **NEW ENTRY** hanya boleh keluar jika data EOD **CANONICAL** untuk `trade_date` sudah tersedia.

**Rule deterministik**
1) Tentukan `trade_date` dari **effective date Market Data** (cutoff + trading day).

**Definisi waktu (wajib konsisten)**
- `generated_at`: waktu JSON dibuat.
- `trade_date`: tanggal EOD yang dipakai untuk scoring/plan (basis data canonical).
- `as_of_trade_date`: *trading day terakhir yang seharusnya sudah punya EOD canonical pada saat `generated_at`*.
  - Jika **sebelum cutoff EOD** (pre-open / pagi hari) → `as_of_trade_date` = trading day **kemarin**.
  - Jika **sesudah cutoff + publish sukses** → `as_of_trade_date` = trading day **hari ini**.
- `missing_trading_dates`: daftar trading day dari (`trade_date` .. `as_of_trade_date`) yang belum punya canonical. **Jangan pernah** memasukkan “today” sebelum cutoff.

2) Cek ketersediaan CANONICAL:
   - `ticker_ohlc_daily` tersedia untuk `trade_date` (coverage lolos / publish sudah jalan), dan
   - fitur `ticker_indicators_daily` tersedia untuk `trade_date`.
3) Jika salah satu **tidak tersedia** (missing / held / incomplete window):
   - Set output global: `trade_plan_mode = NO_TRADE` untuk **NEW ENTRY**,
   - Semua ticker hanya boleh berstatus **watch-only** (`entry_style = No-trade`, `size_multiplier = 0.0`),
   - Tambahkan `market_notes`: “EOD CANONICAL belum tersedia (atau held). Entry ditahan.”
   - Wajib tulis reason code global: `EOD_NOT_READY`.

**Catatan**
- Policy `INTRADAY_LIGHT` boleh dipakai **hanya** jika snapshot intraday yang disyaratkan benar-benar ada (kalau tidak ada → tetap NO_TRADE).
- `CARRY_ONLY` untuk posisi yang sudah ada tetap boleh tampil, tetapi **tanpa** membuka posisi baru.


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
- `trade_disabled` (boolean; **true** jika global mode = `NO_TRADE` atau policy melarang entry)
- `trade_disabled_reason` (ringkas; contoh: `EOD_STALE`, `EOD_NOT_READY`, `RISK_OFF`)
- `risk_notes` (1–2 kalimat, bukan essay)

**D. Position context (kalau ticker sudah kamu pegang)**
- `has_position` (boolean)
- `position_avg_price`, `position_lots` (opsional jika ada input portfolio)
- `days_held` (trading days)
- `position_state`: `HOLD | REDUCE | EXIT | TRAIL_SL`
- `action_window[]` (jam eksekusi untuk aksi di atas; terutama exit/trim)
- `updated_stop_loss_price` (jika rule trailing/BE mengubah SL)
- `position_notes` (1 kalimat; contoh: “Naikkan SL ke BE karena TP1 tercapai; bias exit Jumat.”)

**E. Disable reason (lebih detail, biar UI tidak ambigu)**
- `trade_disabled_reason_codes[]` (array; contoh: `GAP_UP_BLOCK`, `CHASE_BLOCK_DISTANCE_TOO_FAR`, `WEEKEND_RISK_BLOCK`)

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



### 2.1 Strategy Policy itu apa (dan kenapa harus eksplisit)
Watchlist punya beberapa **Strategy Policy**. Policy adalah paket aturan end-to-end yang deterministik:
- horizon holding (berapa hari/minggu)
- syarat data (EOD saja atau butuh snapshot intraday)
- kandidat apa yang boleh dipilih
- kapan entry (window) + kapan harus NO TRADE
- exit template (SL/TP/BE/time-stop)
- expiry rule (setup kadaluarsa kapan)

> Prinsip SRP: Market Data = data, Compute EOD = feature/indikator, Watchlist = policy/seleksi/plan.

### 2.2 Daftar Strategy Policy (resmi)
| policy_code | Target | Holding | Data minimum | Kapan dipakai |
|---|---|---|---|---|
| `WEEKLY_SWING` | profit 2–5% mingguan dengan disiplin | 2–5 trading days | EOD canonical + indikator | default (paling sering) |
| `DIVIDEND_SWING` | capture dividen + tetap risk-controlled | 3–10 trading days | EOD + calendar event (ex-date) | hanya saat ada event dividen yang valid |
| `INTRADAY_LIGHT` | timing entry lebih presisi (tanpa full intraday system) | 0–2 trading days | EOD + *opening range snapshot* | opsional, kalau snapshot ada |
| `POSITION_TRADE` | trend-follow 2–8 minggu | 10–40 trading days | EOD + trend quality | saat market risk-on & trend kuat |
| `NO_TRADE` | proteksi modal | n/a | market regime + quality gate | saat data/market tidak mendukung |

### 2.3 Policy: WEEKLY_SWING (default)
**Tujuan:** ambil move mingguan yang realistis, bukan “tebak puncak”.

**A. Data dependency (wajib):**
- `ticker_ohlc_daily` canonical (trade_date = effective_end_date)
- `ticker_indicators_daily`: MA20/50/200, RSI14, vol_sma20, vol_ratio, signal_code, signal_age_days
- fitur murah: `atr14`, `atr_pct`, `gap_pct`, `dv20`, candle flags

**B. Candidate gates (deterministik):**
- Likuiditas: `dv20` minimal (mis. bucket A/B). Bucket C boleh masuk kandidat, tapi **bukan recommended**.
- Volatilitas: `atr_pct` maksimal (saham terlalu liar → turunkan confidence / NO TRADE).
  - **Default weekly swing (bisa di-config):**
    - `atr_pct <= 0.07` → ok untuk recommended
    - `0.07 < atr_pct <= 0.10` → kandidat boleh, **recommended turun** (size_multiplier max 0.8)
    - `atr_pct > 0.10` → default `trade_disabled` (kecuali ada override manual)
- Data window lengkap: indikator inti tidak boleh NULL.
- Corporate action suspected/unadjusted → skip.

**C. Entry window (EOD → waktu eksekusi):**
- Default: pakai engine di Bagian 8 (window + avoid windows).
- Tambahan weekly swing:
  - **Senin–Selasa:** boleh entry agresif (breakout confirm / pullback buy)
  - **Rabu:** entry selektif; prefer pullback, hindari chasing
  - **Kamis:** entry hanya kalau setup “sangat kuat” (High confidence) dan risk kecil
  - **Jumat:** default NO NEW ENTRY (kecuali signal baru + risk rendah)


**C.1 Anti-chasing & gap guard (WEEKLY_SWING wajib, deterministik)**
Karena watchlist EOD-only, guard ini mencegah entry “ketinggalan kereta” dan jebakan gap.
- **Gap-up block (pre-buy checklist + reason code):** jika saat eksekusi harga/open gap-up > `max_gap_up_pct` dari `prev_close` → **NO ENTRY**, tunggu pullback.
  - Default: `max_gap_up_pct = min(0.025, 1.0*atr_pct)`.
- **Distance-to-trigger block (berbasis EOD):** untuk breakout, jika `close > trigger + 0.5*ATR14` → jangan entry breakout; ubah jadi `PULLBACK_LIMIT` atau `WATCH_ONLY`.
- **Late-week entry penalty:** Kamis/Jumat, kalau bukan High confidence → downgrade menjadi `WATCH_ONLY`.
- Semua guard di atas harus memunculkan reason codes: `GAP_UP_BLOCK`, `CHASE_BLOCK_DISTANCE_TOO_FAR`, `LATE_WEEK_ENTRY_PENALTY`.

**D. Expiry (setup kadaluarsa):**
- Breakout/strong burst: valid maksimal 1–2 trading days setelah sinyal muncul.
- Pullback/continuation: valid 2–4 trading days.
- Aturan praktis: `signal_age_days > 3` → turun confidence; `> 5` → bukan recommended.

**E. Exit & lifecycle rules (WEEKLY_SWING wajib, bukan opsional):**
- **Hard SL:** selalu ada (ATR/support/MA). Saat entry terjadi, SL **tidak boleh NULL**. (lihat Bagian 20)
- **Partial take:** default ambil parsial di TP1 (contoh 40%–60%), sisanya ke TP2/trailing.
- **Break-even rule:** setelah TP1 hit, naikkan SL minimal ke `entry` (atau `entry + 0.2R` untuk cover fee).
- **Time stop (deterministik):**
  - Setelah **2 trading days** dari entry: jika belum mencapai `+0.5R` → `REDUCE 50%` atau `EXIT` (tergantung market regime).
  - Setelah **3 trading days** dari entry: jika masih “flat” / gagal follow-through → `EXIT`.
- **Max holding (deterministik):**
  - Breakout/Strong burst: max **2–3** trading days.
  - Pullback/Continuation: max **3–5** trading days.
  - Reversal: max **2–4** trading days.
- **Friday exit bias:** Jumat siang/sore default **prioritas exit** untuk posisi yang belum hit TP1 (hindari nyangkut). Reason: `FRIDAY_EXIT_BIAS`.
- **Weekend rule:** default **hindari carry over weekend** kecuali `confidence=High` + trend kuat + SL sudah naik (BE/trailing). Reason: `WEEKEND_RISK_BLOCK`.

**F. Algoritma ringkas (deterministik, step-by-step)**
1) Tentukan `effective_trade_date` dari Market Data (cutoff-aware) → ini jadi `trade_date` watchlist.
2) Ambil fitur per ticker untuk `trade_date` dari `ticker_indicators_daily` (plus fitur murah: atr/gap/dv20/candle).
3) Jalankan **Hard Filters** (Bagian 5.1). Jika gagal → drop.
4) Jalankan **Soft Filters** (Bagian 5.2). Jika kena → turunkan confidence/score.
5) Hitung **watchlist_score** (Bagian 6). Wajib simpan breakdown reason codes.
6) Tentukan `setup_type` (Bagian 7).
7) Tentukan `entry_windows` & `avoid_windows` (Bagian 8) + day-of-week adjustment.
8) Ranking: pilih Top Picks (Bagian 10).
9) Tentukan `buy_mode` dan `% alloc` (Bagian 19).
10) Untuk recommended pick: hitung `trade_plan` (Bagian 20) + sizing lots (Bagian 21).
11) Simpan output ke tabel watchlist (Bagian 12) + log summary per hari.

**G. Anti-bias penting:**
- Jangan “memaksa” selalu ada BUY. Kalau market regime risk-off atau kualitas data jelek → `NO_TRADE`.
- Kalau indikator window tidak lengkap (Compute EOD mengembalikan NULL), watchlist harus downgrade/skip.


### 2.4 Policy: DIVIDEND_SWING (event-driven)
**Tujuan:** dapat dividen tanpa bunuh diri karena gap risk.

**A. Data tambahan yang dibutuhkan (kalau belum ada, lihat Bagian 11):**
- `dividend_calendar` (ticker, ex_date, pay_date, dividend_amount, yield_est)
- flag corporate action/split adjusted

**B. Gates khusus:**
- Wajib **likuid** (dv20 bucket A)
- Hindari saham dengan `atr_pct` tinggi (gap risk besar)
- Market regime minimal neutral (risk-off → NO TRADE)

**C. Timing:**
- Entry ideal: H-3 sampai H-1 ex-date (bukan H0). Hindari entry mepet close jika spread jelek.
- Exit rule:
  - Conservative: exit H-1 / H0 (sebelum ex-date) jika target tercapai.
  - Hold-through: tahan sampai ex-date hanya jika trend kuat + risk rendah.

**D. Algoritma ringkas (deterministik)**
1) Ambil event `ex_date` untuk 7–14 hari ke depan.
2) Filter ticker event:
   - yield_est memadai (opsional) dan **likuid** (dv20 bucket A)
   - atr_pct tidak liar
3) Jika market regime risk-off → `NO_TRADE` (policy batal).
4) Entry window default mengikuti Bagian 8, tapi tambah rule:
   - hindari entry mepet close (match risk)
   - hindari entry H0 (ex-date) kecuali super liquid + follow-through
5) Buat trade plan:
   - SL lebih ketat (gap risk)
   - TP lebih konservatif (karena tujuan event-driven)
6) Exit:
   - default: exit H-1/H0 bila target tercapai
   - hold-through hanya jika trend kualitas tinggi (MA alignment + signal continuation)

**E. Catatan penting:** dividend policy butuh data event; tanpa itu jangan dipaksakan (lebih baik tidak aktif daripada salah).


### 2.5 Policy: INTRADAY_LIGHT (opsional)
**Tujuan:** memperbaiki timing entry untuk setup EOD kuat tanpa membangun sistem intraday penuh.

**Syarat mutlak:** ada *opening range snapshot* (09:00–09:15/09:30) minimal berisi: open_range_high/low, volume_opening, gap_pct_real.

**Rule ringkas:**
- Setup dari EOD tetap sumber utama (breakout/pullback/continuation).
- Intraday dipakai hanya untuk:
  - konfirmasi breakout (break above opening range high)
  - menghindari fake move (break lalu balik di bawah range)
- Kalau snapshot tidak ada → policy ini **tidak boleh aktif**.

**Algoritma ringkas (deterministik)**
1) Pastikan snapshot tersedia untuk `trade_date` (kalau tidak → policy nonaktif).
2) Dari EOD: pilih kandidat setup kuat (score tinggi) yang biasanya masuk top picks.
3) Definisikan entry trigger intraday:
   - Breakout: buy hanya jika harga break **di atas** `opening_range_high` dan tidak langsung gagal (OR fail).
   - Pullback: buy hanya jika harga bertahan di atas OR mid / reclaim OR high (pilih 1 rule dan konsisten).
4) SL intraday tetap mengacu ke level plan (ATR/support) dari EOD, bukan dibuat random.
5) Jika tidak ada konfirmasi sampai akhir window → `NO_TRADE` untuk ticker itu.

**Batasan:** policy ini bukan scalping. Ini hanya “konfirmasi entry” agar mengurangi fake breakout.


### 2.6 Policy: POSITION_TRADE (2–8 minggu)
**Tujuan:** ride trend besar, bukan trading mingguan.

**Gates:**
- Trend quality tinggi (MA alignment kuat + signal continuation)
- Market regime risk-on
- Exit lebih longgar (ATR-based, trailing)

### 2.7 Urutan pemilihan policy (deterministik)
Agar hasil watchlist konsisten:
1) Jika market regime = risk-off → `NO_TRADE` (kecuali ada policy khusus hedge, kalau nanti ada)
2) Jika ada event dividen valid + lulus gates → `DIVIDEND_SWING`
3) Jika snapshot intraday tersedia + setup EOD kuat → `INTRADAY_LIGHT` (opsional)
4) Default → `WEEKLY_SWING`
5) Jika trend super kuat & kamu pilih mode long horizon → `POSITION_TRADE`

> Ini urutan default. Kalau nanti kamu mau mengunci “mode” (mis. minggu ini hanya weekly swing), tinggal override di layer config/preset UI.



---

## 3) Data yang dibutuhkan (akurat & audit-able)

### 3.1 Data mentah wajib (ticker_ohlc_daily)
Catatan penting:
- `ticker_ohlc_daily` dianggap **CANONICAL output** dari Market Data.
- Watchlist **tidak boleh** membaca RAW market data; hanya canonical.

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
  - **Default bucket (IDR, bisa diubah via config):**
    - **A (liquid):** `dv20 >= 20_000_000_000` (≥ 20B)
    - **B (ok):** `5_000_000_000 <= dv20 < 20_000_000_000` (5B – <20B)
    - **C (illiquid):** `dv20 < 5_000_000_000` (<5B)
  - **Catatan wajib:**
    - `dv20` dihitung dari **20 trading days** terakhir (exclude today) menggunakan **CANONICAL EOD**.
    - Threshold harus berasal dari config (mis. `liq.dv20_a_min`, `liq.dv20_b_min`) dan boleh dituning setelah lihat distribusi pasar & constraints broker.


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

## 4) Pipeline pembuatan watchlist (konsep)

> Aturan SRP/performa global mengikuti `SRP_Performa.md`. Bagian ini hanya menjelaskan urutan proses dan kontrak input/output antar job.

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

> Prinsip: **watchlist tidak menghitung indikator berat**. Watchlist membaca hasil compute job (compute-eod/market-context), lalu fokus ke seleksi, scoring, ranking, dan reason codes.

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
- Volatilitas ekstrem: `atr_pct > 0.10` → default drop (weekly swing), kecuali override manual
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
  - checklist menekankan follow-through + guard anti-chasing:
    - Jika open/last price gap-up > `max_gap_up_pct` dari close kemarin → NO ENTRY (tunggu pullback)
    - Jika harga sudah jauh di atas trigger (> 0.5*ATR14) → NO CHASE
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
- `GAP_UP_BLOCK`
- `CHASE_BLOCK_DISTANCE_TOO_FAR`
- `NO_FOLLOW_THROUGH`
- `SETUP_EXPIRED`
- `TIME_STOP_TRIGGERED`
- `FRIDAY_EXIT_BIAS`
- `WEEKEND_RISK_BLOCK`
- `MIN_EDGE_FAIL`
- `FEE_IMPACT_HIGH`

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


## 11) Data yang mungkin “belum ada” tapi sebaiknya disediakan (supaya akurasi naik)
Bagian ini menjelaskan **apa yang perlu ditambah** di Market Data / Compute EOD sebelum kamu “naik kelas” strategi.

### 11.1 Wajib untuk akurasi EOD-only (WEEKLY_SWING default)
> Semua di bawah ini bisa dihitung dari OHLC canonical (murah, tapi efeknya besar).
1) `prev_close` + `gap_pct`
2) `atr14` + `atr_pct`
3) `dvalue = close*volume` + `dv20` + `liq_bucket`
4) candle metrics (body/wick/inside/engulfing flags)
5) market regime IHSG (ret_1d/ret_5d + MA20/MA50) + (opsional) breadth
6) `signal_first_seen_date` + `signal_age_days`

**Kalau poin 1–6 belum ada:**
- yang paling tepat: dihitung di **Compute EOD** (feature layer) lalu disimpan ke `ticker_indicators_daily` (atau tabel feature harian lain).
- watchlist hanya membaca, tidak menghitung ulang.

### 11.2 Tambahan untuk DIVIDEND_SWING (event-driven)
Butuh **calendar event**, bukan sekadar indikator:
- `dividend_calendar` minimal: `ticker_id`, `ex_date`, `record_date` (opsional), `pay_date` (opsional), `cash_dividend` (opsional), `yield_est` (opsional)
- flag corporate action adjusted (split/rights) agar yield tidak menipu

**Kalau belum ada:**
- ini bukan tugas Compute EOD. Ini tugas **Market Data (Corporate Actions / Events)**.

### 11.3 Tambahan untuk INTRADAY_LIGHT (tanpa full intraday)
Butuh 1 record per ticker per hari dari sesi awal:
- `opening_range_high`, `opening_range_low` (09:00–09:15 atau 09:00–09:30)
- `opening_range_volume`
- `gap_pct_real` (gap pada open aktual)
- opsional: `or_breakout_flag` (break above OR high?), `or_fail_flag` (break lalu balik?)

**Kalau belum ada:**
- ini bukan Market Data EOD. Buat modul kecil terpisah: **Intraday Snapshot (opening range)**.
- watchlist policy `INTRADAY_LIGHT` harus otomatis nonaktif kalau data ini tidak tersedia.

### 11.4 Tambahan untuk POSITION_TRADE (2–8 minggu)
Butuh metrik trend yang lebih stabil:
- slope proxy MA (mis. `ma20_slope`, `ma50_slope` dalam % per hari trading)
- volatility filter jangka menengah (ATR% rata-rata 20–50 hari)
- drawdown proxy (mis. distance from 20d high/low)

**Kalau belum ada:**
- tetap dihitung di **Compute EOD** (feature layer), karena turunannya dari OHLC.

### 11.5 Penyesuaian yang “wajib kalau mau akurat” di Market Data & Compute EOD
Ini bukan fitur mewah. Ini mencegah data “kelihatan jalan tapi salah”.

**A. Market Data (wajib):**
- CANONICAL gating: kalau coverage jelek / held, watchlist harus bisa `NO_TRADE`.
- corporate action awareness: minimal flag split/discontinuity (ideal: adjusted canonical).
- `trade_date` konsisten WIB + market_calendar.

**B. Compute EOD (wajib):**
- rolling window = **N trading days** (bukan kalender) untuk semua indikator.
- jika window tidak lengkap → indikator NULL + downgrade decision + log warning.
- tidak menghitung “strategi”; hanya feature/signal.


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

Bagian ini adalah “aturan kerja” supaya watchlist **stabil, bisa diaudit, dan gampang di-upgrade** tanpa merusak hasil yang sudah ada.

### 14.1 Contract test (JSON schema) – wajib
Watchlist adalah **API contract**. Setiap build harus lulus test yang memvalidasi:
- Root keys: `trade_date`, `groups`, `meta`, `recommendations`
- `meta.counts.total_count` konsisten dengan jumlah kandidat yang tampil
- Semua kode di `reason_codes[]` dan `rank_reason_codes[]` harus terdaftar di katalog (`meta.rank_reason_catalog`) jika katalog disertakan
- Tipe data stabil (angka tetap angka; array tetap array)

**Aturan kompatibilitas**
- Kalau ada rename field (breaking) → wajib dicatat di `MANIFEST.md` + bump build_id.
- Kalau ingin aman untuk UI lama → gunakan alias (field lama tetap ada sementara) sebelum benar-benar dihapus.

### 14.2 Invariants – harus true setiap waktu
Ini bukan “best practice”, ini **aturan hard** yang kalau gagal berarti output tidak valid.

**A. Global gating lock**
- Jika `recommendations.mode = NO_TRADE`:
  - semua ticker wajib `trade_disabled = true`
  - `entry_windows = []`, `avoid_windows = []`
  - `size_multiplier = 0.0`, `max_positions_today = 0`
  - `entry_style = "No-trade"`
  - `timing_summary` dan `pre_buy_checklist` boleh pakai default global

**B. Top picks quality**
- `groups.top_picks[]` tidak boleh punya `liq_bucket in ('C','U')`
- `passes_hard_filter` harus true
- `trade_plan.errors` harus kosong untuk ticker yang direkomendasikan BUY (kalau mode BUY)

**C. Data consistency**
- `signal_age_days` konsisten dengan `signal_first_seen_date` + kalender bursa
- `corp_action_suspected = true` → ticker **tidak boleh** direkomendasikan BUY
- Nilai `dv20` dan `liq_bucket` harus konsisten (dv20 menentukan bucket)

### 14.3 Reason code governance (biar tidak liar)
Tambah reason code baru **wajib** memenuhi:
- Punya definisi singkat (1 kalimat) + severity (`info|warn|block`)
- Deterministik (tidak bergantung “perasaan”)
- Sumber datanya jelas (kolom indikator/fitur apa)
- Masuk katalog (`meta.rank_reason_catalog` / label catalog) dan test “unknown code → fail”

**Rekomendasi:** pisahkan alasan menjadi:
- `reason_codes` = untuk UI (ringkas & actionable)
- `rank_reason_codes` = untuk audit/scoring (boleh verbose), bisa dipindah ke `debug.*`

### 14.4 Snapshot strategy (compute vs serve)
Untuk hasil maksimal dan stabil:
- Default endpoint **serve-from-snapshot** bila snapshot untuk (`trade_date`, `source`) sudah ada.
- Compute hanya jika:
  - snapshot belum ada, atau
  - `force=1`, atau
  - ada perubahan policy version yang memang ingin regenerate.

Ini membuat:
- output konsisten (hit endpoint berulang hasilnya sama),
- beban DB lebih ringan,
- evaluasi mingguan lebih gampang.

### 14.5 Calibration loop (supaya makin akurat, bukan teori)
Minimal tiap minggu/bulan, buat laporan:
- hit-rate TP1/TP2 (1D/3D/5D)
- MFE/MAE sederhana (maks profit vs maks drawdown setelah entry)
- frekuensi false breakout untuk setup tertentu

Dari sini update:
- bobot score (trend/momentum/volume/risk/market),
- threshold (vol_ratio, atr_pct, dv20 bucket),
- expiry rule (`signal_age_days`).

**Aturan:** perubahan threshold harus tercatat di dokumen (dan idealnya `policy_version`).

### 14.6 Post-mortem wajib (supaya WATCHLIST.md makin baru)
Simpan minimal untuk top picks/recommended:
- snapshot indikator + reason codes saat rekomendasi dibuat
- outcome ringkas (TP/SL/time-stop)
- catatan eksekusi: spread melebar? gap? follow-through?

Tujuannya: WATCHLIST.md tidak jadi “dokumen teori”, tapi terus di-upgrade berdasarkan data nyata.

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
- `pos_pct` (Pos% = posisi **close** dalam range hari itu, 0–100): `100*(close-low)/(high-low)` lalu clamp 0..100; jika `high==low` → `null`.
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

### 19.0 Prioritas posisi existing (weekly swing yang realistis)
Jika ada posisi open di portfolio, watchlist **harus** memprioritaskan manajemen posisi dulu, baru entry baru.
- Jika ada posisi yang kena rule `EXIT` / `TIME_STOP_TRIGGERED` / `FRIDAY_EXIT_BIAS` → mode harian minimal `CARRY_ONLY` (no new entry) sampai selesai dieksekusi.
- Jika ada posisi yang valid untuk di-hold (trend lanjut, SL sudah naik) → boleh tetap `BUY_1/BUY_2`, tapi `max_positions_today` dipotong 1.
- Definisi `CARRY_ONLY`: watchlist **hanya** memberi aksi `HOLD/REDUCE/EXIT/TRAIL_SL` untuk posisi existing; `allocations[]` untuk NEW ENTRY harus kosong.

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

### 20.4 Guards (anti-chasing, gap, viability) – default WEEKLY_SWING
Guards ini membuat plan **boleh batal** walau setup bagus di EOD.

**A) Gap-up guard (saat eksekusi)**
- Jika open/last price > `prev_close * (1 + max_gap_up_pct)` → set `entry_type = WATCH_ONLY` untuk hari itu.
- Default `max_gap_up_pct = min(2.5%, 1.0*atr_pct)`.
- Reason codes: `GAP_UP_BLOCK`.

**B) No-chase guard (berbasis EOD, sebelum market buka)**
- Jika `close > trigger + 0.5*ATR14` → jangan entry breakout (ubah ke pullback atau watch-only).
- Reason codes: `CHASE_BLOCK_DISTANCE_TOO_FAR`.

**C) Minimum edge guard (biar weekly swing tidak buang-buang fee/spread)**
- Jika `rr_tp1 < 1.0` → downgrade ke `WATCH_ONLY`.
- Jika fee aktif dan `profit_tp1_net` terlalu kecil (mis. < 0.7%) → downgrade/disable.
- Reason codes: `MIN_EDGE_FAIL`, `FEE_IMPACT_HIGH`.

**D) Time-stop / follow-through guard (untuk posisi yang sudah entry)**
- Jika setelah 2 trading days belum `+0.5R` → `REDUCE/EXIT` (sesuai policy di Bagian 2.3E).
- Jika harga kembali close di bawah area breakout dan body besar → `EXIT`.
- Reason codes: `NO_FOLLOW_THROUGH`, `TIME_STOP_TRIGGERED`.

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
- **ComputeEOD**: hitung indikator + feature layer (MA/RSI/vol_ratio, ATR, hhv/llv, wick/body, dv20, dsb). **Bukan** entry/SL/TP/plan.
- **MarketContext job**: IHSG regime + breadth + kalender.
- **WatchlistBuild**: scoring + ranking + setup_type + timing windows + reason codes.
- **TradePlanBuild** (bisa bagian dari watchlist build): entry/SL/TP/BE/out + rr.
- **PositionSizing**: hanya jalan kalau user memasukkan `capital_total` (atau ada default dari profile).

Semua output disimpan di DB agar UI bisa menampilkan kartu kandidat seperti mockup, dan recommended pick punya strategi eksekusi lengkap.