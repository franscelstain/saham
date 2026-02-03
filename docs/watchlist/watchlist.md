# TradeAxis Watchlist — Cross-Policy Contract + Universe Filter (SOP)

> **Source of Truth (LOCKED)**
> - `watchlist.md` mengunci: **Universe Filter**, **kontrak global PLAN (EOD-only)**, **grouping**, dan **algoritma recommendations (selection+allocation)**.
> - Detail Hard/Soft/Risk/Scoring per strategi ada di `policy/<policy>.md`.
> - Aturan CONFIRM (intraday checks) ada di `scorecard.md`.
> **Jika ada kalimat lain yang bertentangan, anggap tidak binding dan ikuti yang LOCKED.**
> **Catatan Anti Salah Tafsir — `schema.md` & `strategi.md` adalah LIVING DOCS (bukan acuan normatif)**
> - `docs/watchlist/schema.md` dan `docs/watchlist/strategi.md` adalah **catatan kondisi sistem saat ini** yang **wajib selalu diupdate** bila ada perubahan di code/DB/command/output yang belum tercatat.
> - Jika ada gap antara implementasi dan dua dokumen tersebut, maka **yang dibetulkan adalah dokumennya** (supaya kembali sinkron), bukan memaksa implementasi mengikuti dokumen yang tertinggal.
> - Dua dokumen itu **tidak meng-override** aturan **LOCKED** di dokumen ini; mereka berfungsi untuk **mencegah salah pakai** dan memudahkan operasional.
>
> **Ruang lingkup wajib masing-masing dokumen**
> 1) `schema.md`:
>    - Mencatat **semua tabel** yang dipakai Watchlist beserta **fungsi tabelnya**.
>    - Mencatat **fungsi kolom** (kenapa ada, dipakai untuk apa).
>    - Menandai sumber pengisian data: **manual oleh user** vs **otomatis oleh sistem**, termasuk:
>      - dari **commands**,
>      - dari proses terjadwal,
>      - atau dari pipeline lain (mis. ingest/compute).
> 2) `strategi.md`:
>    - Mencatat **aktivitas operasional** Watchlist end-to-end (alur kerja).
>    - Mencatat **commands** yang tersedia + urutan eksekusi + contoh pemakaian.
>    - Menjadi **panduan penggunaan** saat aplikasi sudah jadi (runbook mini untuk operator/user).

## Glossary (LOCKED)

Istilah inti yang dipakai lintas dokumen. Semua definisi di bawah bersifat **binding**.

- `trade_date` = tanggal eksekusi hari ini (PLAN dihitung dari EOD hari sebelumnya).
- `asof_eod_date` = tanggal EOD yang dipakai untuk PLAN.
- `dv20_idr` = rata-rata **nilai transaksi harian** 20 hari (IDR). Dipakai sebagai metrik likuiditas utama.
- `turnover20_idr` = fallback metrik likuiditas (IDR). **Jika tidak tersedia → treat missing** (jangan dihitung dari asumsi).
- `atr14` = ATR 14 hari (IDR).
- `atr_pct` = `atr14 / close` (range [0..1]).
- `tick_pct` = `tick_size / close` (range [0..1]).
- `R` = `plan_entry - plan_stop` (IDR). Kontrak global: `R <= 0` → DROP, `R < tick` → DROP.
- `rr_est` = `(plan_tp1 - plan_entry) / R` (tanpa magic number).
- `score_total` = skor final per policy, **range [0..1]**.
- `reasons[]` = array object `{code,message,severity?}` (lihat section Reasons object).

## Global Contract (wajib lintas policy)

Bagian ini adalah **kontrak universal**. Semua policy wajib patuh. Policy **tidak boleh** mengubah definisi di bawah ini.

### 1) Data readiness & canonical consistency
- **Source of truth OHLC:** gunakan `ticker_ohlc_daily` sebagai kebenaran OHLC. `ticker_indicators_daily` adalah **turunan**.
- **Canonical ready gate:** jika data EOD untuk `trade_date` belum final/canonical → **wajib** `recommendations = []` (No Trade untuk eksekusi). Namun **groups** (Top Picks/Secondary/Watch Only/Avoid) tetap boleh dihitung untuk monitoring, dengan flag global `EOD_NOT_READY` dan reason `GL_EOD_NOT_READY`.
- **Satu `trade_date` yang sama:** semua ticker dinilai pada tanggal EOD yang sama (PLAN).
- **Run consistency (jika ada `run_id`):** pada `trade_date`, hasil scoring wajib pakai run yang sama (canonical). Jika OHLC berbeda antar run → dianggap belum ready.

### 2) Window & lookback definition (anti-bias)

#### Contract: `trading_days_between(a, b)` untuk event counting (GLOBAL)
Dipakai untuk menghitung jarak hari bursa antar dua tanggal (khususnya event dividend).

Definisi **wajib**:
- `trading_days_between(a, b)` = jumlah **trading days** `d` sehingga `a < d <= b`.
  - Artinya: **exclude** `a`, **include** `b` jika `b` adalah trading day.
- Jika `a == b` → hasil = `0`.
- Jika `a > b` → **invalid**. Implementasi wajib mengembalikan `null` (bukan negatif). Untuk dividend gate, kondisi ini → DROP reason khusus.

Konsekuensi untuk dividend:
- Entry harus terjadi **sebelum** `ex_date`, jadi wajib `exec_trade_date < ex_date`.
- Jika `exec_trade_date >= ex_date` → DROP (`DS_TOO_LATE_EXDATE`).

#### Contract: `highest_high(N)` / `lowest_low(N)` (GLOBAL, non-negotiable)
Untuk menghindari hasil beda antar modul/versi, definisi ini **wajib sama** di seluruh engine:

- `highest_high(N, trade_date)` = max(`high`) dari **N trading days sebelum** `trade_date` (**exclude `trade_date`**).
- `lowest_low(N, trade_date)`  = min(`low`)  dari **N trading days sebelum** `trade_date` (**exclude `trade_date`**).

Jika ada modul yang meng-include `trade_date`, itu dianggap bug (`LOOKBACK_INCLUDES_TODAY`).

Definisi window harus konsisten agar hasil tidak bias:
- `highest_high(N)` / `lowest_low(N)` dihitung dari **N hari trading sebelumnya** dan **exclude hari ini**.
- `dv20_idr = SMA20(close*volume)` dengan `min_periods=20`. Jika kurang dari 20 baris → `DATA_INSUFFICIENT`.
- `vol_sma20` / `ma20/50/200` mengikuti definisi indikator harian yang sama untuk semua ticker.
- **Missing day handling:** jika ada missing EOD pada window lookback yang membuat indikator/setup tidak valid → ticker **gugur** untuk hard rules (reason: `DATA_INSUFFICIENT`), bukan dipaksa lolos.

### 3) Tick ladder, rounding, lot sizing, fee model (feasibility)

#### Contract: Risk unit `R` dan `rr_est` (GLOBAL)
Untuk mencegah “magic number” dan RR yang ngawur:

- `tick = tick_size(entry_price)` (dari tick ladder).
- `R = entry_price - stop_price`.
- Jika `R <= 0` → **DROP** (`R_INVALID_NONPOSITIVE`).
- Jika `R < tick` → **DROP** (`R_INVALID_LT_TICK`) karena risk terlalu kecil dan rawan rounding.
- `rr_est = (tp1_price - entry_price) / R` (tanpa `max(R, 1)`).

#### Contract enforcement: RR definition (GLOBAL)
Policy **tidak boleh** mendefinisikan ulang `rr_est` atau memakai fallback seperti `max(R,1)`.
Jika ditemukan, itu dianggap bug dan harus disamakan dengan kontrak global:
- `rr_est = (tp1 - entry) / R` dengan `R > 0` dan `R >= tick`.

Catatan: rounding tick dilakukan **setelah** menghitung raw plan level, tapi validasi `R` wajib pakai harga yang sudah rounded.

- `lot_size = 100`.
- **Rounding (wajib konsisten):**
  - Entry: round **UP** ke tick.
  - Stop: round **DOWN** ke tick.
  - TP: round **DOWN** ke tick.
- Fee harus dihitung konsisten untuk Recommendations (`estimated_cost` include fee). Tanpa feasibility (min 1 lot) → tidak boleh masuk Recommendations.

#### Contract: `fee_buy(gross_amount)` (GLOBAL, binding shape)
Agar `estimated_cost` dan feasibility lots konsisten, bentuk fungsi fee harus dikunci.

- `gross_amount` dalam IDR (rupiah) untuk satu transaksi (mis. 1 lot pada harga tertentu).
- `fee_buy(gross_amount)` = ceil(gross_amount * FEE_BUY_RATE)
- Jika ada minimum fee broker, tambahkan:
  - `fee_buy` = max(fee_buy, FEE_BUY_MIN)
- Pembulatan fee: **ceil ke 1 rupiah** (bukan floor/round).

Nilai parameter (`FEE_BUY_RATE`, opsional `FEE_BUY_MIN`) **wajib** berasal dari konfigurasi aplikasi yang sama untuk semua modul (watchlist/recommendations/portfolio). Dokumen ini mengunci *bentuk perhitungan*, bukan angka broker tertentu.

### 4) PLAN vs CONFIRM (pemisahan ketat)
- **PLAN = EOD-only** (data kemarin). PLAN tidak memakai snapshot intraday.
- **CONFIRM = guard intraday** untuk eksekusi hari ini (gap/chase/spread). CONFIRM **tidak boleh** memodifikasi PLAN; hanya mengubah status `confirmed`/`blocked` pada eksekusi.

### 5) Auditability minimum (wajib)
Setiap ticker yang muncul harus bisa diaudit:
- `reasons[]` (rule hits, drop reasons, avoid reasons)
- PLAN fields: `plan.entry`, `plan.stop`, `plan.tp1`, `plan.tp2`, `plan.rr`, `plan.stop_pct`, `plan.atr_pct`, `plan.dv20_idr`
- Recommendations: `planned_lots`, `estimated_cost` (include fee)

### 6) Validation & acceptance criteria (SOP)
Tambahkan metrik agar sistem bisa di-tuning dan tidak “asal feeling”:
- **Universe pass rate**: % ticker lolos Universe (target awal 30–70%).
- **Candidate rate per policy**: jumlah ticker lolos hard rules (tidak harus ada setiap hari, tapi tidak boleh “sering nol” tanpa alasan).
- **Reco feasibility rate**: % recommendations yang valid min 1 lot (target >95%).
- **Outcome tracking** (opsional tapi dianjurkan): dalam 5/10 hari, berapa yang hit TP1/stop untuk evaluasi policy.

File: `watchlist.md`
Tujuan dokumen ini: **kontrak lintas-policy** + **Universe Filter**.
Detail strategi (Hard/Soft/Risk/Ranking/PLAN/CONFIRM) ada di `policy/*.md`.

Policy docs:
- `policy/weekly_swing.md`
- `policy/dividend_swing.md`
- `policy/position_trade.md`
- `policy/intraday_light.md`
- `policy/no_trade.md`
---

## 0) Core design (wajib)

### 0.1 PLAN (wajib, EOD-only)
PLAN dibuat **hanya** dari data EOD `trade_date` (canonical) + indikator/label berbasis EOD.

PLAN mencakup:
- Universe Filter (dokumen ini)
- Strategy rules (policy)
- Ranking (policy)
- Grouping (global, dokumen ini)
- Recommendations (PLAN allocation) **tanpa** snapshot intraday

**Invariant PLAN:**
- PLAN tidak boleh baca snapshot intraday.
- PLAN tidak boleh diubah oleh CONFIRM.
- Tidak ada “filler/placeholder ticker”.

### 0.2 CONFIRM (opsional, intraday guard)
CONFIRM adalah guard eksekusi di `exec_trade_date` menggunakan snapshot (preopen/open/last):
- gap ekstrem vs close EOD
- chase (harga lari jauh dari entry plan)
- spread/liquidity intraday buruk

Jika snapshot tidak tersedia → status `PENDING` (bukan menggugurkan PLAN).

**Invariant CONFIRM:**
- CONFIRM hanya menambah status `OK | SKIP | PENDING` + reason, tidak mengubah kandidat/ranking/grouping/recommendations PLAN.

---

## 1) Time model (wajib konsisten)
- `trade_date`: tanggal EOD canonical yang dipakai PLAN (basis level & scoring).
- `exec_trade_date`: tanggal eksekusi (biasanya trading day berikutnya setelah `trade_date`).
- Semua indikator yang dipakai PLAN harus dihitung pada `trade_date`.

---

## 2) Universe Filter (global, hard rules)
Universe Filter berlaku untuk semua policy dan dievaluasi sebelum policy rules.

Output dari tahap ini: `UniverseEligible`.

### 2.1 Data readiness gate (DROP)
DROP jika:
- OHLCV EOD untuk `trade_date` tidak lengkap/invalid
- indikator minimum yang dipakai engine tidak tersedia (lookback tidak cukup)
Reason codes:
- `GL_DATA_INCOMPLETE`
### 2.2 Canonical EOD ready gate (NEW ENTRY lock)
Watchlist boleh tetap menampilkan monitoring, tapi **NEW ENTRY diblok** jika canonical EOD belum ready.
Rule:
- jika `meta.canonical_ready == false` → `recommendations=[]` dan groups.top_picks tetap dihitung (tidak dikosongkan) (NEW ENTRY off)
Reason code:
- `GL_EOD_NOT_READY`
Catatan:
- Ini bukan “DROP”, tapi global lock terhadap recommendations (SOP: jangan entry pakai data setengah matang).

### 2.3 Tradeability gate (TRADE_DISABLED, not DROP)

Tujuan gate ini adalah **mencegah entry** pada ticker yang secara mekanisme tidak layak dieksekusi, tapi masih boleh muncul untuk monitoring.

Aturan (LOCKED):
- Suspend/halts → `tradeability = TRADE_DISABLED`, group = `Avoid`, reason `GL_SUSPENDED`.
- Trading mechanism tidak regular (mis. FCA) → `tradeability = TRADE_DISABLED`, group = `Avoid`, reason `GL_MECHANISM_FCA`.
- Special notation `X` → `tradeability = TRADE_DISABLED`, group = `Avoid`, reason `GL_SPECIAL_NOTATION_X`.
- Special notation `E` → **tidak disable**, tetap tradeable; tambahkan warning reason `GL_SPECIAL_NOTATION_E` (group ditentukan oleh hasil policy/risk).

### 2.4 Liquidity gate (DROP)

Wajib memenuhi minimal likuiditas EOD (LOCKED).
Universe Filter adalah minimum untuk masuk universe; tiap policy boleh menetapkan threshold yang lebih ketat (lebih tinggi) dan itu dievaluasi di policy hard rules.

#### Definisi metrik (LOCKED)
- `dv20_idr`: rata-rata nilai transaksi 20 hari (IDR), dihitung dari data EOD: `avg(close * volume)` untuk 20 trading day terakhir.
- `turnover20_idr`: **alias/alternatif input** pada horizon yang sama (20 trading day) dengan skala yang sama (IDR). **Kontrak: engine tidak menghitung turnover20_idr dari sumber lain.** Jika field ini tidak ada/null di dataset input, dianggap **missing**.

#### Threshold (LOCKED)
- `MIN_DV20_IDR = 2000000000` (Rp 2B)
- `MIN_TURNOVER20_IDR = 2000000000` (Rp 2B)

#### Aturan deterministik (LOCKED)
1) Jika `dv20_idr` tersedia (not null) → gunakan `dv20_idr` sebagai satu-satunya metrik:
   - Wajib `dv20_idr >= MIN_DV20_IDR`, jika gagal → DROP `GL_LIQ_TOO_LOW`.
2) Jika `dv20_idr` missing (null) dan `turnover20_idr` tersedia → gunakan `turnover20_idr` sebagai fallback:
   - Wajib `turnover20_idr >= MIN_TURNOVER20_IDR`, jika gagal → DROP `GL_LIQ_TOO_LOW`.
3) Jika **keduanya missing** → DROP `GL_LIQ_METRIC_MISSING`.

Catatan:
- Jika sistem kamu mengisi `turnover20_idr = dv20_idr`, itu sah (alias), tapi bukan perhitungan engine.

### 2.5 Price sanity gate (DROP)
DROP jika:
- `close < MIN_PRICE`
Reason:
- `GL_PRICE_TOO_LOW`
Default (rekomendasi awal):
- `MIN_PRICE = 50`
### 2.6 Extreme volatility guard (DROP, konservatif)
Tujuan: buang ticker chaos ekstrem yang merusak semua strategi.
DROP jika:
- `atr_pct > MAX_ATR_PCT_UNIVERSE`
Reason:
- `GL_VOL_TOO_HIGH`
Default:
- `MAX_ATR_PCT_UNIVERSE = 0.20` (20%)

---

## 3) Derived metrics (global, definisi wajib)
Definisi ini dipakai lintas policy.

### 3.1 Candle shape (dari OHLC)
- `range = max(high - low, 1)`
- `close_pos = (close - low) / range`  (0..1)
- `candle_body = abs(close - open)`
- `upper_wick = high - max(open, close)`
- `lower_wick = min(open, close) - low`
Jika kamu sudah punya `*_pct` di feature table, pastikan definisinya konsisten dengan rumus ini.

### 3.2 Gap & chase (CONFIRM only)
- `gap_pct = (exec_price / close) - 1` (close = EOD `trade_date`)
- `chase_pct = (exec_price / plan.entry) - 1` (hanya jika entry_price ada)

Jika `exec_price` null → status confirm `PENDING`.

---

## 4) Group semantics (global)
Semantics group sama di semua policy; yang beda hanya kandidat & ranking dari policy.

- **Top Picks**: kandidat valid (lolos universe + hard rules policy) dengan skor tertinggi & risk rendah.
- **Secondary**: kandidat valid tapi kualitas di bawah Top Picks.
- **Watch Only**: lolos universe tapi belum memenuhi hard rules (belum trigger) atau ada trade_disabled situasional; masih relevan untuk dipantau.
- **Avoid**: red flag risk / tradeability lock; disarankan dihindari.
- **No_Trade**: tidak ada kandidat qualified untuk policy aktif, atau policy aktif adalah NO_TRADE.

Tidak ada hard cap jumlah item yang “dipilih internal”.
Jika UI butuh limit, itu **hanya limit publish** (config) dan harus eksplisit (bukan mempengaruhi keputusan recommendations).

---

## 5) Recommendations (PLAN execution plan)
Recommendations adalah rencana eksekusi beli hari itu (bukan sekadar ranking).

Rules:
1. Hanya boleh berisi ticker yang lolos **universe + hard rules policy** dan tidak trade_disabled.
2. Harus mempertimbangkan `capital`, lot size, tick rounding, dan fee sehingga:
   - `estimated_cost(include_fee) <= remaining_capital`
   - `planned_lots` integer >= 1
3. Top Picks tetap murni ranking EOD; recommendations boleh memilih kandidat ranking lebih rendah bila top picks tidak feasible 1 lot.
4. Tidak ada filler. Jika tidak ada yang feasible → recommendations kosong.
5. Audit wajib per ticker:
   - `reasons[]`, `planned_lots`, `estimated_cost`, `plan.entry`.

---

## 6) Tick rounding, lot sizing, fee model (global contract)
- Semua harga output adalah integer dan sesuai tick ladder.
- Rounding:
  - entry (buy trigger): ROUND_UP
  - stop: ROUND_DOWN
  - tp: ROUND_DOWN (konservatif)
- Lot size default BEI: 100 saham (kecuali override).
- Fee model konsisten dan dipakai dalam `estimated_cost`.

---

## 7) Reason codes (governance)
Namespace:
- Global: `GL_*`
- Weekly Swing: `WS_*`
- Dividend Swing: `DS_*`
- Position Trade: `PT_*`
- Intraday Light: `IL_*`
- No Trade: `NT_*`
Rules:
- deterministik (reproducible)
- tidak tergantung urutan iterasi
- minimal 1 reason utama saat DROP / trade_disabled / avoid / no_trade

### Data aktif
- `is_deleted = 0` berarti data aktif (belum dihapus). Nilai selain itu dianggap tidak aktif.

---

## Implementation Notes (non-normative)

### Mismatch audit (opsional tapi sangat dianjurkan)
Jika `ticker_indicators_daily` juga menyimpan OHLC dan nilainya tidak sama dengan `ticker_ohlc_daily` pada `(ticker_id, trade_date)`,
anggap indikator untuk baris itu **invalid** dan pakai OHLC dari `ticker_ohlc_daily` saja. Catat reason `IND_OHLC_MISMATCH`.

---

## Recommendations (PRIMARY: flat array, single strategy implicit)

### Weighting for recommendations (LOCKED)

`weight_pct` **wajib** deterministik dan tidak boleh “opsional”. Engine **selalu** menghitung `weight_pct` dari `score_total` untuk
candidate pool recommendations (setelah cutoff), lalu baru melakukan konversi ke lots.

#### Defaults (LOCKED)

- `W_MIN = 0.10`  (min weight per ticker)
- `W_MAX = 0.60`  (max weight per ticker)
- Jika jumlah ticker `N == 1` → `weight_pct = 1.00`.
- Jika `N > 1`:
  - `w_raw_i = score_total_i`
  - `w_clamped_i = clamp(w_raw_i, W_MIN, W_MAX)`
  - `weight_pct_i = w_clamped_i / sum(w_clamped)`
- Setelah drop ticker karena `< 1 lot`, lakukan **renormalize** dengan rumus yang sama sampai stabil.

> Catatan: Ini **bukan hard cap jumlah ticker**. Jumlah ticker tetap ditentukan oleh cutoff pool + feasibility lots.

### Reason objects (LOCKED, user-facing)
Semua `reasons[]` yang muncul di output (global/policy/confirm) harus berupa **object**, bukan string code saja:
- `code` (stable, machine-readable)
- `message` (1 kalimat, user-facing, audit-friendly)
- `severity` (opsional): `INFO | WARN | BLOCK`

**Sumber kebenaran message ada di kode** (mis. `app/Trade/Explain`). Dokumen ini hanya mengunci bentuk payload dan kewajiban `message` hadir.

**Tujuan DTO:** bentuk output utama tetap sederhana dan stabil.

### Primary schema (dipakai API/DTO sekarang)
`recommendations` adalah array ticker plan hasil seleksi EOD (best default). Ini setara dengan **single strategy implicit** (engine memilih best default).

Setiap item rekomendasi minimal berisi:
- `ticker_code`
- `planned_lots` (nullable jika capital missing)
- `estimated_cost` (nullable jika capital missing; include fee)
- `reasons[]` (audit; array of objects `{ code, message, severity? }`)
- `plan`: `{ entry, stop, tp1, tp2?, rr_est, stop_pct }`

- `eod_bar` (opsional tapi sangat disarankan untuk UI): ringkasan OHLCV EOD untuk konteks (bukan sinyal).
  - `eod_bar = { asof_eod_date, open, high, low, close, prev_close, gap_pct, volume, value_idr, atr14?, atr_pct?, dv20_idr? }`
  - Semua nilai harga dalam IDR (integer) dan `asof_eod_date` = `meta.asof_eod_date`.
- `execution` (opsional tapi disarankan): `{ mode, tranches[] }`
  - `tranches[]` berisi rencana eksekusi per tranche (urutan 1..N)
    - `time` (string HH:MM)
    - `planned_lots` (int | null)
    - `plan_limit_price` (int IDR, **wajib selalu ada**)
    - `plan_price_cap` (int IDR, **wajib selalu ada**)
    - `plan_price_floor` (int IDR | null)
    - `reason` (string)

  - `mode ∈ {ONE_SHOT, 2_TRANCHE, 3_TRANCHE}`
  - `tranches[]` berisi `{ tranche_pct, planned_lots, plan_limit_price, plan_price_cap, plan_price_floor?, when, condition, reasons[] }`
  - Jika capital missing → `planned_lots=null` untuk semua tranche, **tapi** `plan_limit_price` dan `plan_price_cap` tetap wajib ada (harga tetap disarankan).

#### Execution tranche price intent (LOCKED, anti-debat)

Tujuan: setiap tranche selalu punya **harga beli yang disarankan** (PLAN) dan **batas maksimum bayar** (anti chase), walaupun `capital` tidak ada. CONFIRM boleh memberi harga live, tapi **wajib** menghormati pagar PLAN.

Field (PLAN, immutable):
- `plan_limit_price`: harga limit yang disarankan untuk tranche (IDR int).
- `plan_price_cap`: batas maksimum boleh beli (IDR int). Jika live ask > cap → tidak boleh entry (DELAY/REJECT).
- `plan_price_floor` (opsional, default null): batas minimum (jarang dipakai; biasanya null).

Kontrak pembentukan harga PLAN (deterministik, EOD-only):
- **PULLBACK**
  - `plan_limit_price = plan.entry`
  - `plan_price_cap = plan.entry`  (pullback = tidak chase)
- **BREAKOUT**
  - `plan_limit_price = plan.entry`
  - `plan_price_cap = round_up(plan.entry * (1 + CF_MAX_CHASE_PCT_policy))`

Kontrak harga CONFIRM (live, deterministik, bounded by PLAN):
- Ambil `ask_best` dari `ask1` jika depth tersedia, atau dari input `ask_best` jika tanpa depth.
- Jika `ask_best > plan_price_cap` → `CF_CHASE_BLOCK` (DELAY/REJECT sesuai scorecard).
- Jika lolos:
  - **PULLBACK**: `confirm_limit_price = min(plan_limit_price, ask_best, plan_price_cap)`
  - **BREAKOUT**: `confirm_limit_price = min(ask_best, plan_price_cap)`

Catatan:
- CONFIRM **tidak boleh** mengubah `plan.entry/stop/tp1`. CONFIRM hanya memberi `confirm_limit_price` (harga eksekusi live) + reasons.

**Tidak ada** `recommendations.strategies[]` pada output utama agar DTO tidak retak.

### Optional advanced (backward compatible)
Jika kamu butuh menampilkan alternatif strategi (2–3 opsi) tanpa memecah DTO utama, pakai field tambahan:
- `recommendation_strategies[]` (opsional; bisa dikontrol lewat feature flag / query param).
- Jika field ini tidak ada, UI tetap pakai `recommendations[]`.

`recommendation_strategies[]` berisi:
- `strategy_code` (CONCENTRATED/BALANCED/STAGED)
- `tickers[]` (dengan `weight_pct` dan `execution`)
- `cash_remaining`
- `reasons[]`
`recommendations[]` tetap diisi dari **best default strategy** (flattened).

### Preconditions (hard)
- Recommendations hanya boleh diambil dari ticker yang **lolos hard rules policy aktif**.
- Jika eligible candidates = 0 → `recommendations = []` dan `no_trade_reason = NO_QUALIFIED_CANDIDATES`.
- Tidak ada filler/placeholder.

### Canonical EOD not ready (hard)
Jika canonical EOD belum ready pada `trade_date`:
- `recommendations = []` (**wajib**).
- Groups (Top Picks/Secondary/Watch Only/Avoid) tetap dihitung untuk monitoring dengan `flags:["EOD_NOT_READY"]` + reason global `GL_EOD_NOT_READY`.

### Operational guardrails (EOD-first) (LOCKED)

Bagian ini menambah guardrail operasional agar output stabil dan audit-able, tanpa menggeser PLAN dari EOD.

#### 1) Data completeness gate (EOD-only)
- Engine wajib memvalidasi `Input minimum (PLAN)` dari policy aktif.
- Jika ada field minimum yang missing/null → DROP ticker sebelum scoring.
  - Reason code global: `GL_POLICY_INPUT_MISSING`
  - `reasons[]` wajib menyebut `missing_fields=[...]`.

#### 2) Corporate action / event risk (EOD-only, optional)
Jika dataset menyediakan event/corporate-action (mis. `ex_date`, `rights_date`, `suspension_flag`):
- Engine boleh menambahkan `flags += ["CA_EVENT_NEAR"]` (monitoring).
- Guardrail ini tidak boleh membuat ticker lolos hard rules; hanya boleh menaikkan risk label (mis. `Avoid`) dan/atau mencegah masuk `recommendations`.
Jika data event tidak tersedia → treat missing (tidak ada asumsi).

#### 3) Gap-risk proxy (EOD-only, optional)
Tanpa intraday hari ini, gap risk hanya boleh di-approx dari EOD historis jika metrik tersedia.
Jika dataset menyediakan metrik seperti `gap_pct_20_max` / `gap_rate_20` / `gap_open_prevclose_pct`:
- Engine boleh menambah flag `GAP_RISK_HIGH` dan menurunkan ranking atau memindahkan ke `Avoid`.
Jika metrik gap tidak tersedia → treat missing.

#### 4) Fee model / lot size / tick ladder (single source)
- Lot size & tick ladder wajib single source (global config), dan semua modul harus pakai definisi yang sama.
- `estimated_cost` pada recommendations wajib include fee sesuai model.
Jika engine belum include fee, output harus set:
- `meta.fee_included=false` dan reason `GL_FEE_EXCLUDED`.

#### 5) Deterministic ordering (tie-breakers global)
Jika `score_total` sama, urutan final ditentukan:
1) `dv20_idr` lebih tinggi
2) `atr_pct` lebih rendah
3) `tick_pct` lebih rendah
4) `ticker_code` A→Z

### Mini strategy (Top Picks & Secondary) (EOD-only) (LOCKED, dynamic)

Tujuan: setiap ticker di `Top Picks` dan `Secondary` memiliki rencana eksekusi bertahap yang **deterministik** (tanpa intraday) dan bisa **adaptif** berdasarkan risk/reward dari hasil PLAN.

Kontrak:
- Mini strategy hanya ditambahkan pada `groups.top_picks[]` dan `groups.secondary[]`.
- Mini strategy dihitung **setelah** PLAN (entry/stop/tp1/rr_est) valid, sehingga tranching berasal dari hasil proses, bukan asumsi.
- `mini_tranches_pct[]` selalu berbasis persentase (tidak butuh capital).
- Jika `capital` ada dan ticker masuk `recommendations`, maka `mini_tranches_lots[]` **harus** copy dari `recommendations.tranches` (single source), bukan dihitung ulang.
- Jika ticker tidak masuk `recommendations`, maka `mini_tranches_lots=null`.

#### Proses penentuan tranche profile (LOCKED)

Input yang dipakai (EOD-only):
- `rr_est` (dari PLAN)
- `atr_pct`, `tick_pct` (risk metrics)
- `flags` (optional): `GAP_RISK_HIGH`, `CA_EVENT_NEAR` (jika tersedia)

Step 1 — Risk bucket (LOCKED)
- `risk_bucket = HIGH` jika salah satu true:
  - `atr_pct >= 0.12`
  - `tick_pct >= 0.012`
  - `flags` mengandung `GAP_RISK_HIGH` atau `CA_EVENT_NEAR`
- `risk_bucket = LOW` jika semua true:
  - `atr_pct <= 0.07`
  - `tick_pct <= 0.008`
  - tidak ada flag risk di atas
- Selain itu: `risk_bucket = MED`

Step 2 — Reward bucket (LOCKED)
- `reward_bucket = HIGH` jika `rr_est >= 1.8`
- `reward_bucket = LOW` jika `rr_est < 1.3`
- Selain itu: `reward_bucket = MED`

Step 3 — Profile selection per policy (LOCKED)
- Weekly Swing / Dividend Swing / Position Trade:
  - Jika `risk_bucket=HIGH` → profile `CONSERVATIVE`
  - Else jika `reward_bucket=HIGH` dan `risk_bucket=LOW` → profile `AGGRESSIVE`
  - Else → profile `DEFAULT`
- Intraday Light:
  - Jika `risk_bucket=HIGH` → profile `CONSERVATIVE`
  - Else jika `reward_bucket=HIGH` → profile `AGGRESSIVE`
  - Else → profile `DEFAULT`
- NO_TRADE: tidak ada mini strategy

Step 4 — Profile → tranche pct (LOCKED)
- Untuk WS/DS/PT (2 tranche):
  - `CONSERVATIVE`: 50/50
  - `DEFAULT`: 60/40
  - `AGGRESSIVE`: 70/30
- Untuk IL (2 tranche):
  - `CONSERVATIVE`: 60/40
  - `DEFAULT`: 70/30
  - `AGGRESSIVE`: 80/20

Timing hint (LOCKED):
- tranche1: `09:20`
- tranche2: `10:30`

Output tambahan (audit):
- `mini_tranche_profile` (string): `CONSERVATIVE` | `DEFAULT` | `AGGRESSIVE`
- `mini_tranche_rule` (string): contoh `EOD_TRANCHE_RULE_V1`

### Dua mode deterministik: tanpa capital vs dengan capital
**Mode A: capital missing / null / <=0**
- `planned_lots = null`, `estimated_cost = null`
- `execution.tranches[].planned_lots = null`
- `execution.tranches[].plan_limit_price` + `execution.tranches[].plan_price_cap` tetap **wajib ada** (harga tetap disarankan).
- reason global minimal: `RECO_CAPITAL_MISSING_LOTS_NULL`
**Mode B: capital tersedia**
- lots dihitung deterministik (lihat kontrak Allocation → lots).
- ticker yang tidak feasible min 1 lot → drop dari recommendations (reason `RECO_TICKER_DROPPED_INFEASIBLE_MIN_LOT`).
- jika semua drop → `recommendations=[]`.

### Score scale contract (LOCKED)
Agar cutoff seperti `MIN_RECO_SCORE` tidak jadi “semua lolos” / “semua gugur”, skala `score_total` harus dikunci.

**Kontrak:**
- `score_total` **wajib** berada pada range **0.00 .. 1.00** (float), di mana:
  - 0.00 = kandidat terburuk (nyaris tidak layak),
  - 1.00 = kandidat terbaik (setup sangat kuat).
- `score_total` adalah **normalized weighted score** (bukan angka arbitrary, bukan 0..100).
- Untuk **display UI**, boleh render: `score_total_pct = round(score_total * 100, 1)` (0..100). API tetap mengirim `score_total` pada range 0..1 (binding).

**Normalisasi yang wajib dipakai (deterministik):**
1) Hitung sub-score per faktor (masing-masing 0..1, dengan clamp):
   - `s_momentum`, `s_trend`, `s_volume`, `s_pattern`, `s_risk` (contoh; sesuai policy).
2) Setiap sub-score dihitung dari metrik mentah memakai fungsi yang stabil:
   - **Clamp + linear map** untuk metric yang punya batas jelas:
     - `s = clamp((x - lo) / (hi - lo), 0, 1)`
   - **Logistic** untuk metric yang ekstrem/outlier (opsional, tapi jika dipakai harus konsisten):
     - `s = 1 / (1 + exp(-k*(x - x0)))`
3) Gabungkan dengan weighted sum lalu clamp:
   - `score_raw = Σ (w_i * s_i)`
   - `score_total = clamp(score_raw, 0, 1)`
**Catatan penting:**
- Semua `w_i` harus dijelaskan di policy (atau di ranking section) dan jumlahnya idealnya 1.0.
- Jika engine saat ini memakai skor 0..100, maka **wajib** dikonversi: `score_total = clamp(score_0_100 / 100, 0, 1)`.

### Candidate pool & cutoff kualitas (tanpa hard cap)
Input: `EligibleCandidates` (lolos Universe + hard policy, bukan Avoid).

Sorting deterministik:
1) `score_total` desc
2) `plan.rr` desc
3) `plan.stop_pct` asc
4) `dv20_idr` desc
5) `atr_pct` asc
6) `ticker_code` asc

Cutoff kualitas:
- `S0 = score kandidat rank #1`
- masuk pool jika: `score_total >= max(MIN_RECO_SCORE, S0 - RECO_SCORE_GAP)`
Default:
- `MIN_RECO_SCORE = 0.70`  // berlaku karena score_total ter-normalisasi 0..1
- `RECO_SCORE_GAP = 0.05`
### Allocation → lots (LOCKED, binding algorithm)

Bagian ini adalah kontrak langkah-langkah **mengikat** untuk mengubah `capital` menjadi integer lots per ticker dan per tranche.
Jika tidak ada `capital` (Mode A) → lots selalu `null` (lihat rules di atas). Jika `capital` ada (Mode B) → ikuti algoritma ini.

#### A) Urutan iterasi allocation (ranking order)
Urutan iterasi untuk allocation dan leftover **wajib** sama dengan sorting kandidat rekomendasi:
1) `score_total` desc
2) `plan.rr` desc
3) `plan.stop_pct` asc
4) `dv20_idr` desc
5) `atr_pct` asc
6) `ticker_code` asc

#### B) Budget per ticker
- Jika `weight_pct` tersedia (hasil allocation method) → `budget_i = capital * weight_pct_i`
- Jika `weight_pct` tidak tersedia (harusnya jarang) → `budget_i = capital / N` (equal weight)

Catatan: `weight_pct` adalah persentase (0..1). Jika format 0..100, konversi dulu.

#### C) Estimasi biaya per 1 lot (include fee, safe against staging)
Untuk ticker i, sudah ada `execution.tranches[]` dengan `plan_limit_price` + `plan_price_cap` (PLAN, immutable).

Definisi:
- `LOT_SIZE = 100`
- `gross_per_lot(price) = price * LOT_SIZE`
- `fee_per_lot(price) = fee_buy(gross_per_lot(price))` (mengikuti fee model global)
- `cost_per_lot(price) = gross_per_lot(price) + fee_per_lot(price)`
Agar tidak tembus modal akibat tranche harga lebih tinggi:
- `price_worst = max(plan_price_cap untuk semua tranche ticker i)  (safe: worst-case bayar sampai cap)`
- `est_cost_per_lot_i = cost_per_lot(price_worst)`
#### D) Initial lots allocation
Untuk setiap ticker i (urut ranking):
1) `lots_i = floor(budget_i / est_cost_per_lot_i)`
Hard rule:
- Jika `lots_i < 1` → **DROP ticker** dari `recommendations` (reason `RECO_TICKER_DROPPED_INFEASIBLE_MIN_LOT`)
- Setelah drop:
  - lakukan **renormalize weights** pada ticker tersisa (sum weights = 1.0),
  - lalu ulangi langkah B–D sampai stabil (tidak ada drop baru).

Jika setelah drop ticker kosong → `recommendations = []`.

#### E) Split lots ke tranche (rounding rules)
Setelah `lots_i` final, bagi lots ke tranche sesuai `execution.mode`:

**ONE_SHOT (100%)**
- `t1 = lots_i`
**2_TRANCHE (60/40)**
- `t1 = ceil(0.60 * lots_i)`
- `t2 = lots_i - t1`
**3_TRANCHE (50/30/20)**
- `t1 = ceil(0.50 * lots_i)`
- `t2 = ceil(0.30 * lots_i)`
- `t3 = lots_i - t1 - t2`
- Jika `t3 < 0` (lots kecil), set:
  - `t3 = 0`
  - `t2 = lots_i - t1`
Tranche yang mendapat `planned_lots=0` boleh tetap tampil sebagai template, tetapi tidak boleh menyebabkan biaya > capital.

#### F) Hitung estimated_cost dan cash_remaining
Biaya aktual dihitung per tranche (pakai harga tranche, bukan price_worst):
- `estimated_cost_i = Σ_t (planned_lots_it * cost_per_lot(plan_price_cap_t))  (conservative)`
- `total_estimated_cost = Σ_i estimated_cost_i`
- `cash_remaining = capital - total_estimated_cost` (wajib >= 0)

Jika `cash_remaining < 0`, itu bug (kontrak D menggunakan price_worst harus mencegahnya).

#### G) Leftover allocation (deterministik, one-lot-per-iter)
Setelah initial allocation:
- Sisa modal boleh dipakai untuk menambah lots **hanya** pada ticker yang sudah terpilih (tidak revive ticker drop).

Algoritma (ranking-first, deterministic):
1) Ulangi selama masih ada penambahan yang feasible:
2) Iterasi ticker dari ranking tertinggi ke terendah.
3) Untuk ticker i, coba tambah **+1 lot pada tranche-1**.
4) Feasible jika `cash_remaining >= cost_per_lot(plan_price_cap_tranche1)`.
5) Jika feasible:
   - tambah 1 lot tranche-1,
   - update `estimated_cost_i`, `cash_remaining`,
   - **break** (kembali ke langkah 1, mulai lagi dari ranking tertinggi).
6) Stop jika satu putaran penuh tidak ada ticker yang feasible untuk +1 lot.

Catatan:
- Ini menjaga leftover dibagi deterministik dan tidak “lompat” ke ticker lain yang tidak qualified.
- Jika kamu ingin lebih ketat: hanya top K ticker (mis. top 1–2) yang boleh menerima leftover; kalau mau, kunci param `LEFTOVER_TOP_K`.

### Execution mode (policy-aware, default)
- Weekly Swing: BREAKOUT → 2_TRANCHE (60/40), PULLBACK → 3_TRANCHE (50/30/20)
- Position Trade: BREAKOUT → 2_TRANCHE (60/40), PULLBACK → 3_TRANCHE (50/30/20)
- Dividend Swing: ONE_SHOT (default; staging disabled)
- Intraday Light: ONE_SHOT (default; staging disabled)

---

## Micro Strategy (per-ticker execution template)

Selain `recommendations[]` (paket multi-ticker), setiap ticker pada output group
**Top Picks / Secondary / Watch Only** boleh memiliki `micro_strategy` berupa template eksekusi untuk ticker itu saja.

Prinsip:
- `micro_strategy` **bukan universal**; harus mengikuti rule policy aktif.
- Jika policy tidak mendukung atau data tidak cukup → `micro_strategy = null` + reason jelas.
- `micro_strategy` tidak boleh memindahkan ticker antar group. Watch Only tetap Watch Only.

### 1) Kapan `micro_strategy` boleh dibuat (hard preconditions)
`micro_strategy` hanya boleh dibuat jika:
1) ticker punya `setup_type` valid dari policy (minimal `{BREAKOUT, PULLBACK}`),
2) PLAN level valid: `plan.entry`, `plan.stop` ada dan `R = entry-stop` lulus kontrak global (`R > 0` dan `R >= tick`),
3) ticker **bukan Avoid**,
4) policy mengizinkan execution mode tersebut (lihat mapping di bawah).

Jika gagal → `micro_strategy = null` dan tambahkan salah satu reason:
- `MICRO_STRATEGY_UNAVAILABLE_POLICY`
- `MICRO_STRATEGY_DATA_INSUFFICIENT`
- `MICRO_STRATEGY_R_INVALID`
- `MICRO_STRATEGY_AVOID`
### 2) Dua mode: tanpa capital vs dengan capital (sama dengan Recommendations)
**Mode A: capital missing**
- `planned_lots = null`, `estimated_cost = null`
- Tranche % dan plan price tetap ada (tanpa asumsi).
- reason: `MICRO_CAPITAL_MISSING_LOTS_NULL`
**Mode B: capital tersedia**
- Hitung `planned_lots` integer per tranche berdasarkan porsi capital ticker (lihat aturan sizing di bagian Recommendations).
- Jika min 1 lot tidak feasible → `micro_strategy = null` dengan reason `MICRO_INFEASIBLE_MIN_LOT`. Sizing mengikuti kontrak **Allocation → lots (LOCKED)** di bagian Recommendations.
  (Jangan memaksakan lots=0 sebagai filler.)

### 3) Schema ringkas `micro_strategy`
```json
micro_strategy: {
  mode: "ONE_SHOT" | "2_TRANCHE" | "3_TRANCHE",
  eligible_now: boolean,
  trigger_needed?: string,     // wajib untuk Watch Only
  weights_pct: 100,            // selalu 100 karena 1 ticker
  tranches: [
    { tranche_pct, planned_lots, plan_limit_price, plan_price_cap, plan_price_floor?, when, condition, reasons[] }
  ],
  reasons[]                     // reason global micro_strategy
}
```
### 4) Policy-aware execution mapping (default)
Mapping ini dipakai untuk menentukan `mode` dan `tranches` bila policy mengizinkan.

- **Weekly Swing**
  - BREAKOUT → `2_TRANCHE` (60/40)
  - PULLBACK → `3_TRANCHE` (50/30/20)

- **Position Trade**
  - BREAKOUT → `2_TRANCHE` (60/40)
  - PULLBACK → `3_TRANCHE` (50/30/20)

- **Dividend Swing**
  - default → `ONE_SHOT` (100%)
  - staging dinonaktifkan by default (window event sempit + gap risk)

- **Intraday Light**
  - default → `ONE_SHOT` (100%)
  - staging dinonaktifkan by default (stop ketat + confirm ketat)

- **No Trade**
  - tidak punya micro strategy

### 5) Watch Only behavior (wajib ketat)
Untuk ticker di **Watch Only**:
- `eligible_now = false`
- `trigger_needed` wajib menjelaskan hard rule yang belum terpenuhi (mis. “close belum breakout resistance_20d”).
- `micro_strategy` hanya template “jika trigger terjadi”, bukan sinyal beli hari ini.

### 6) CONFIRM separation
Jam dan data intraday tidak boleh masuk PLAN:
- `when` hanya `D0/D1/D2` di PLAN.
- Jika perlu jam/snapshot → taruh di CONFIRM dan jangan mengubah `micro_strategy` PLAN.

### Canonical readiness handling (EOD_NOT_READY)
- recommendations=[]; groups tetap dihitung; flags+reason global wajib.

---

## Risk-based sizing (optional, feature-flag)

Default allocation sekarang berbasis feasibility (capital → lots) dan cukup untuk v1.
Jika kamu ingin sizing lebih “trader-grade” (menghindari stop terlalu lebar), tambahkan opsi **feature flag**:

### Flag
- `SIZING_MODE = FEASIBILITY` (default)
- `SIZING_MODE = RISK_BUDGET` (opsional)

### RISK_BUDGET rule (LOCKED jika diaktifkan)
- `risk_budget = capital * RISK_PCT` (mis. 1%–2%, kamu yang kunci)
- `risk_per_lot = R * LOT_SIZE` (R dalam IDR per share)
- `lots_i = floor(risk_budget / risk_per_lot)`
- Lalu tetap cek feasibility biaya:
  - `max_cost = lots_i * est_cost_per_lot_i`
  - jika `max_cost > budget_i` → turunkan lots sampai feasible
- Jika `lots_i < 1` → drop ticker (seperti kontrak Allocation → lots)

Catatan:
- Ini opsional; jangan aktifkan tanpa menetapkan `RISK_PCT`.
- Mode ini menjaga posisi tidak kebesaran saat stop jauh.

---

## Appendices (binding)
## Appendix A — Output schema (DTO)

### Output schema (DTO) (LOCKED)

### Reasons object (LOCKED)
- Semua field `reasons` di seluruh output (groups, recommendations, confirm) **wajib** berupa array object:
  - `code` (string, machine-stable)
  - `message` (string, 1 kalimat, user-facing)
  - `severity` (optional enum: `INFO|WARN|BLOCK`)
- `code` tetap wajib dikirim untuk audit/log.
- `message` disediakan oleh layer aplikasi (mis. `app/Trade/Explain`), bukan oleh dokumen ini.

Tujuan: satu kontrak output yang sama untuk semua policy. Policy hanya mengubah isi kandidat/plan/score, bukan bentuk JSON.

#### Root object

Wajib ada field berikut:

- `meta` (object)
  - `trade_date` (YYYY-MM-DD): tanggal EOD yang dipakai untuk PLAN (kemarin).
  - `policy` (string): nama policy aktif (`WEEKLY_SWING`, `DIVIDEND_SWING`, `POSITION_TRADE`, `INTRADAY_LIGHT`, `NO_TRADE`).
  - `canonical_ready` (bool)
  - `fee_included` (bool)
  - `flags` (string[]): flag global (mis. `EOD_NOT_READY`)
  - `reasons` (string[]): reason code global (mis. `GL_EOD_NOT_READY`)

- `groups` (object)
  - `top_picks` (TickerPlan[])
  - `secondary` (TickerPlan[])
  - `watch_only` (TickerPlan[])
  - `avoid` (TickerPlan[])
  - `no_trade` (TickerPlan[])  *(boleh kosong untuk policy selain NO_TRADE; atau dipakai untuk menampung ticker yang ditandai “NO_TRADE” oleh classifier)*

- `recommendations` (RecommendationPlan[])
  - Wajib selalu ada, tapi boleh `[]`.

#### TickerPlan (untuk groups.*)

Setiap item `TickerPlan` wajib punya:

- `ticker_code` (string)
- `score_total` (float)
- `setup_type` (string|null): `PULLBACK` / `BREAKOUT` (atau null jika policy tidak memakai)
- `plan` (object)
  - `entry` (int|null)
  - `stop` (int|null)
  - `tp1` (int|null)
  - `tp2` (int|null) *(opsional; boleh null)*
  - `r` (int|null)
  - `rr_est` (float|null)
- `risk` (object)
  - `atr_pct` (float|null)
  - `tick_pct` (float|null)
  - `dv20_idr` (int|null)
- `flags` (string[]) *(boleh kosong)*
- `reasons` (string[]): reason code audit-able (DROP/AVOID/WATCH) dari global + policy

Tambahan untuk Top Picks & Secondary (mini strategy, non-breaking):

- `mini_tranches_pct` (array of object|null)
  - Hanya untuk `groups.top_picks` dan `groups.secondary`.
  - Format: `{ "at": "HH:MM", "pct": float, "reason": string }`
  - `pct` berada di range (0..1], total pct = 1.0.

- `mini_tranches_lots` (array of object|null)
  - Opsional, hanya jika ticker tersebut **ada di `recommendations`** (Mode B / capital ada).
  - Format: `{ "at": "HH:MM", "lots": int, "reason": string }`
  - Nilai lots di sini **harus** disalin dari `recommendations.tranches` (single source), bukan dihitung ulang.

Catatan:
- `groups.*` boleh berisi plan null jika policy tidak menghasilkan plan (mis. NO_TRADE), tapi shape tetap sama.

#### RecommendationPlan (rencana eksekusi beli)

Setiap item `RecommendationPlan` wajib punya:

- `ticker_code` (string)
- `planned_lots` (int|null)
  - Mode A (capital missing): null.
  - Mode B (capital ada): integer >= 1.
- `estimated_cost` (int|null)  *(include fee jika `meta.fee_included=true`)*
- `weight_pct` (float|null)
- `tranches` (array of object) *(boleh kosong jika planned_lots null)*
  - `{ "at": "HH:MM", "lots": int, "reason": string }`
- `reasons` (string[]): minimal 1, audit-able
- `ref_plan` (object|null): snapshot plan yang dipakai (entry/stop/tp1/rr_est) agar rekomendasi bisa diaudit tanpa join.

Backward-compatibility:
- Jika implementasi sekarang belum punya `ref_plan`/`tranches`, field boleh ada tapi null/[]; jangan mengubah tipe field secara breaking.

## Appendix B — Reason code registry

Ini adalah registry otomatis dari reason code yang muncul di dokumen. Deskripsi tetap mengikuti lokasi definisi masing-masing code.

### GL_*
- `GL_DATA_INCOMPLETE`
- `GL_EOD_NOT_READY`
- `GL_FEE_EXCLUDED`
- `GL_LIQ_METRIC_MISSING`
- `GL_LIQ_TOO_LOW`
- `GL_MECHANISM_FCA`
- `GL_POLICY_INPUT_MISSING`
- `GL_PRICE_TOO_LOW`
- `GL_SPECIAL_NOTATION_E`
- `GL_SPECIAL_NOTATION_X`
- `GL_SUSPENDED`
- `GL_VOL_TOO_HIGH`

### WS_*
- `WS_MAX_ATR_PCT`
- `WS_MAX_TICK_PCT`
- `WS_MIN_ATR_PCT`
- `WS_MIN_DV20_IDR`
- `WS_MIN_RR`
- `WS_RR_TOO_LOW`
- `WS_R_INVALID_LT_TICK`
- `WS_R_INVALID_NONPOSITIVE`

### DS_*
- `DS_AVOID_ATR_PCT`
- `DS_CONFIRM_MAX_CHASE_PCT`
- `DS_CONFIRM_MAX_GAP_PCT`
- `DS_EVENT_MISSING`
- `DS_EVENT_RUNUP_RISK`
- `DS_LIQ_STRONG`
- `DS_MAX_CHASE_PCT`
- `DS_MAX_DAYS_TO_EX`
- `DS_MAX_EXTEND_ATR`
- `DS_MAX_GAP_PCT`
- `DS_MAX_STOP_PCT`
- `DS_MIN_DAYS_TO_EX`
- `DS_MIN_RR`
- `DS_NEAR_RESISTANCE`
- `DS_NO_SETUP`
- `DS_OUTSIDE_EVENT_WINDOW`
- `DS_PRICE_EXTENDED`
- `DS_RR_TOO_LOW`
- `DS_R_INVALID_`
- `DS_STABLE`
- `DS_STOP_TOO_WIDE`
- `DS_TOO_LATE_EXDATE`
- `DS_TP1_NOT_ABOVE_ENTRY`
- `DS_TP2_R_MULT`
- `DS_TREND_WEAK`
- `DS_VOL_CHAOS`
- `DS_YIELD_GOOD`

### PT_*
- `PT_ATR_SHOCK`
- `PT_BLOWOFF_RISK`
- `PT_BLOWOFF_RSI`
- `PT_BLOWOFF_VOL_RATIO`
- `PT_CLOSE_STRONG`
- `PT_CONFIRM_MAX_CHASE_PCT`
- `PT_CONFIRM_MAX_GAP_PCT`
- `PT_DATA_INCOMPLETE`
- `PT_MAX_ATR_PCT`
- `PT_MAX_ATR_PCT_SHOCK`
- `PT_MAX_CHASE_PCT`
- `PT_MAX_GAP_PCT`
- `PT_MAX_STOP_PCT`
- `PT_MIN_RR`
- `PT_NEAR_RESISTANCE`
- `PT_NEAR_RESIST_PCT`
- `PT_NO_SETUP`
- `PT_OVERHEAT`
- `PT_RR_TOO_LOW`
- `PT_R_INVALID_`
- `PT_STOP_TOO_WIDE`
- `PT_TP1_NOT_ABOVE_ENTRY`
- `PT_TP2_R_MULT`
- `PT_TREND_NOT_OK`
- `PT_TREND_STRONG`
- `PT_VOL_TOO_HIGH`

### IL_*
- `IL_BLOWOFF_RISK`
- `IL_CHAOS_RISK`
- `IL_CLEAN_CANDLE`
- `IL_DATA_INCOMPLETE`
- `IL_LIQ_TOO_LOW`
- `IL_MAX_ATR_PCT`
- `IL_MAX_CHASE_PCT`
- `IL_MAX_GAP_PCT`
- `IL_MAX_SPREAD_PCT`
- `IL_MAX_STOP_PCT`
- `IL_MAX_TICK_PCT`
- `IL_MIN_ATR_PCT`
- `IL_MIN_DV20_IDR`
- `IL_MIN_RR`
- `IL_NO_SETUP`
- `IL_RR_TOO_LOW`
- `IL_R_INVALID_`
- `IL_STOP_TOO_WIDE`
- `IL_TP1_R_MULT`
- `IL_VOL_BAND_FAIL`
- `IL_VOL_CONFIRM`
- `IL_VOL_STRONG`

## Appendix C — Regression checklist

### Regression checklist (LOCKED)

Tujuan: setiap perubahan engine/threshold harus lolos checklist ini agar output tidak berubah “diam-diam”.

#### A. Global contract

1) **PLAN EOD-only**
- Ubah/hapus snapshot intraday → output PLAN (entry/stop/tp) tidak berubah.

2) **highest_high/lowest_low exclusive**
- Pastikan `highest_high(N)` tidak meng-include trade_date.
- Skenario: breakout level berubah jika include trade_date → harus FAIL test.

3) **RR definition**
- Jika `R <= 0` atau `R < tick` → DROP reason jelas (tidak ada magic number).

4) **Canonical not ready**
- `meta.canonical_ready=false` → `recommendations=[]` wajib, groups tetap dihitung + flag/reason global.

5) **Deterministic tie-breakers**
- Dua ticker score_total sama → urutan harus stabil (dv20_idr desc, atr_pct asc, tick_pct asc, ticker_code A→Z).

6) **Fee inclusion contract**
- Jika fee belum dihitung → `meta.fee_included=false` + `GL_FEE_EXCLUDED`.

#### B. Recommendations allocation

7) **Mode A (capital missing)**
- `planned_lots=null`, `estimated_cost=null`, tapi recommendations tetap muncul (berdasarkan ranking & feasibility non-capital).

8) **Mode B (capital ada) floor lots**
- `lots = floor(target_budget / est_cost_per_lot_inc_fee)`.

9) **Drop < 1 lot**
- Jika `lots < 1` → drop ticker (reason feasible) lalu renormalize weights.

10) **Leftover distribution**
- Sisa modal dibagikan deterministik (urut ranking) untuk tambah 1 lot kalau feasible.

11) **Tranche rounding**
- 2_TRANCHE: t1=ceil(0.6*lots), t2=lots-t1.
- 3_TRANCHE: t1=ceil(0.5*lots), t2=ceil(0.3*lots), t3=lots-t1-t2.

#### C. Policy-specific

12) Weekly Swing: stop formula final konsisten (tidak ada alternatif).
13) Dividend Swing: `exec_trade_date < ex_date` wajib true; event day counting konsisten.
14) Position Trade: RR gate pakai TP1 global; breakout TP1 tidak di-cap ke resistance yang sama.
15) Intraday Light: setup_type deterministik + resistance_20 didefinisikan.
16) NO_TRADE: manual-only; tidak ada triggers kosong; tidak ada recommendations.

Setiap item di atas harus punya minimal 1 fixture test/fixture JSON yang bisa dibandingkan (golden master).

## Grouping semantics → numeric algorithm (LOCKED)

Bagian ini mengunci *cara* membentuk `groups.top_picks`, `groups.secondary`, `groups.watch_only`, `groups.avoid`, `groups.no_trade`
secara deterministik dari hasil PLAN (EOD-only). Ini berlaku lintas policy.

### Definitions (LOCKED)

- `U` = ticker yang lolos **Universe Filter** (lihat `watchlist.md` bagian Universe Filter).
- `Q` = ticker yang lolos **Hard Rules policy** (lihat `policy/<policy>.md`) *dan* tradeability `ENABLED`.
- `score_total` = skor final per policy, **range [0..1]** (lihat section scoring/weights per policy).

> Catatan: `canonical_ready=false` hanya mempengaruhi `recommendations` (wajib kosong). Groups tetap boleh dihitung untuk monitoring,
> tapi set `meta.flags += EOD_NOT_READY` dan `meta.reasons += GL_EOD_NOT_READY`.

### Threshold defaults (LOCKED)

- `TOPPICK_MIN_SCORE = 0.70`
- `TOPPICK_SCORE_GAP = 0.08`
- `SECONDARY_MIN_SCORE = 0.55`
- `WATCH_ONLY_MIN_SCORE = 0.35`

Makna:
- `TOPPICK_MIN_SCORE` = batas minimum mutlak untuk masuk Top Picks.
- `TOPPICK_SCORE_GAP` = band relatif dari skor terbaik (`S0`) agar Top Picks tidak cuma 1 ticker ketika banyak kandidat kualitas mirip.
- `SECONDARY_MIN_SCORE` = batas minimum untuk tetap dianggap kandidat “layak” (Secondary).
- `WATCH_ONLY_MIN_SCORE` = batas minimum untuk tetap dipantau (Watch Only); di bawah ini biasanya kualitas terlalu rendah untuk diprioritaskan.

### Sorting (LOCKED)

Urutan kandidat untuk ranking & tie-break:

1) `score_total` desc
2) `dv20_idr` desc (likuiditas lebih tinggi menang)
3) `atr_pct` asc (volatility lebih rendah menang)
4) `tick_pct` asc (friksi tick lebih rendah menang)
5) `ticker_code` asc (stable deterministic fallback)

> Semua field tie-break harus berasal dari PLAN metrics (EOD-only). Jika sebuah field missing, treat missing sebagai nilai terburuk
> untuk kriteria tersebut (agar tidak “mengangkat” ticker karena data kurang).

### Grouping algorithm (LOCKED)

1) Bangun `U`.
2) Jalankan policy hard rules pada `U` → dapatkan `Q`.
3) Jika `Q` kosong:
   - `groups.top_picks = []`
   - `groups.secondary = []`
   - `groups.no_trade = []` (kecuali policy memang `NO_TRADE`)
   - `groups.watch_only` & `groups.avoid` tetap boleh berisi ticker dari `U` sesuai definisi di bawah.
4) Jika `Q` tidak kosong:
   - Sort `Q` sesuai Sorting (LOCKED).
   - `S0 = score_total(Q[0])`
   - `top_cut = max(TOPPICK_MIN_SCORE, S0 - TOPPICK_SCORE_GAP)`
   - `groups.top_picks = { t ∈ Q | score_total(t) >= top_cut }`
   - `groups.secondary = { t ∈ Q | SECONDARY_MIN_SCORE <= score_total(t) < top_cut }`
   - Sisa ticker di `Q` dengan `score_total < SECONDARY_MIN_SCORE`:
     - jika masih memenuhi Universe dan tidak kena avoid guards → masuk `watch_only`
     - jika kena avoid guards → masuk `avoid`

5) `groups.watch_only` (LOCKED):
   - Ticker yang lolos Universe (`U`) namun tidak masuk `top_picks`/`secondary` karena:
     - gagal hard rules policy (mis. belum trigger) **atau**
     - skor terlalu rendah (`score_total < SECONDARY_MIN_SCORE`) tapi masih >= `WATCH_ONLY_MIN_SCORE`
   - Watch Only **tidak** boleh berisi ticker yang gagal Universe.

6) `groups.avoid` (LOCKED):
   - Ticker di `U` yang terkena **avoid guards** (global atau policy) sehingga entry harus dihindari.
   - Contoh guard: extreme ATR%, tick_pct terlalu tinggi, suspend/notrade flag, liquidity shock, gap risk ekstrem, dll.
   - Avoid **tidak** boleh berisi ticker yang gagal Universe.

7) `groups.no_trade` (LOCKED):
   - Hanya dipakai jika:
     - policy yang dipilih memang `NO_TRADE`, atau
     - implementasi sengaja menandai “regime off” (kalau kamu punya market_regime) **tanpa** mengubah definisi group lain.
   - `canonical_ready=false` **bukan** alasan untuk membuat `no_trade` kosong total; itu ditangani oleh global flag.

### Notes

- Nilai threshold di atas adalah default. Jika kamu ingin beda per policy, lakukan lewat `policy overrides (LOCKED)` di masing-masing policy
  dengan nama yang eksplisit (mis. `WS_TOPPICK_MIN_SCORE`) dan dokumentasikan bahwa override menggantikan default global.
- Groups adalah hasil PLAN (EOD-only). CONFIRM tidak boleh memindahkan ticker antar group; CONFIRM hanya memberi status eksekusi.
