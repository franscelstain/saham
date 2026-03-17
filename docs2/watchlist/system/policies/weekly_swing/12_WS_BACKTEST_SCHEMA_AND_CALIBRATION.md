# 12 — Backtest Schema & Calibration — Weekly Swing

Default backtest window: 2 tahun (configurable).

## Purpose

Dokumen ini adalah owner schema backtest WS dan flow kalibrasi parameter; evaluasi metrik minimum, OOS proof, dan artifact manifest dirujuk ke file khusus masing-masing.

Dokumen ini mengunci schema backtest dan aturan perhitungan evaluasi untuk Weekly Swing agar kalibrasi reproducible, audit-able, dan konsisten dengan kontrak runtime canonical Weekly Swing (PLAN / RECOMMENDATION / CONFIRM).
Dokumen ini juga menetapkan syarat validasi: BT coverage, universe equivalence, metric sufficiency, dan OOS proof.

## Scope lock (yang dikerjakan)
Backtest Weekly Swing mengikuti artefak resmi yang didefinisikan pada:
[`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md).

**Schema backtest (LOCKED)**
Schema tabel backtest mengikuti: [`db/BACKTEST_SCHEMA_DDL.sql`](db/BACKTEST_SCHEMA_DDL.sql).

Dokumen ini tidak menduplikasi daftar tabel; semua daftar artefak wajib merujuk ke Manifest.

Catatan audit: universe harian dan picks harian wajib tersedia untuk replay dan audit; lihat Manifest (18) untuk daftar artefak resminya.

Artefak yang tidak tercantum pada Manifest dianggap tidak digunakan dan tidak boleh diasumsikan ada.

Menetapkan mekanisme kalibrasi parameter WS dari backtest 2 tahun:
- menghasilkan param_set baru (origin=BT) yang dapat dipromosikan menjadi ACTIVE.

## Proof of BT coverage (LOCKED)
Semua parameter dengan origin=BT wajib tercakup di:
[`14_WS_BT_COVERAGE_MATRIX_LOCKED.md`](14_WS_BT_COVERAGE_MATRIX_LOCKED.md).

Kalibrasi backtest dianggap tidak valid jika ada parameter origin=BT yang:
- tidak punya mapping ke kolom `watchlist_bt_param_grid`, atau
- tidak punya bukti audit (universe/picks/eval), atau
- tidak lolos test `BT_COVERAGE_GUARD`.

## Universe equivalence (LOCKED)
Backtest universe (`watchlist_bt_universe_ws`) wajib setara dengan production PLAN universe
untuk tanggal EOD yang sama sesuai:
[`15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md).

Bukti equivalence WAJIB menggunakan snapshot dari production PLAN yang mengikuti schema resmi:
[`db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`](db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md).

Kalibrasi dianggap tidak valid jika tidak ada bukti equivalence (pass/fail + canonical reason).

## Prerequisites
### Weekly Swing
11_WS_INTRADAY_SNAPSHOT_TABLES

## Inputs
- Dataset historis 2 tahun (EOD OHLCV + indicators)
- Param grid (seed MAN) untuk eksplorasi

## Backtest execution assumptions (LOCKED)

Bagian ini mengunci cara backtest dihitung agar hasil kalibrasi 2 tahun reproducible dan tidak berubah karena implementasi berbeda.

### A. Data & Kalender
- Universe: hanya ticker yang lolos gate WS pada `asof_eod_date`.
- Trading day: gunakan kalender bursa (skip weekend/holiday).
- Sumber harga: OHLC harian resmi (EOD). Tidak memakai intraday.

### B. Entry Model (PLAN → eksekusi)
- PLAN dibuat pada `asof_eod_date = D` (harga penilaian = close(D)).
- Entry dieksekusi pada trading day berikutnya `D+1`.
- Harga entry default: **open(D+1)**.
- Jika open(D+1) tidak tersedia: fallback **close(D+1)** dan catat `BT_FALLBACK_ENTRY_PRICE`.

### C. Exit Model (horizon Weekly Swing)
- Horizon maksimum: **5 trading day** sejak entry (D+1 s/d D+5).
- Exit utama (ambil yang pertama terpenuhi):
  1) Stop loss: jika **low** hari t <= stop_price → exit di **stop_price**.
  2) Take profit: jika **high** hari t >= target_price → exit di **target_price**.
  3) Time exit: jika sampai akhir horizon belum kena stop/target → exit di **close(D+5)**.
- Jika dalam 1 hari terjadi kondisi stop dan target sekaligus (low <= stop dan high >= target):
  - Prioritas hit: **STOP dulu** (konservatif), catat `BT_AMBIGUOUS_HIT_STOP_PRIOR`.

### D. Level Stop/Target (deterministik)
- Jika policy menyimpan level di PLAN:
  - gunakan langsung `stop_price` dan `target_price` dari PLAN.
- Jika policy tidak menyimpan level:
  - stop berbasis ATR: `stop = entry_price * (1 - stop_atr_mult * atr14_pct)`
  - target berbasis RR: `target = entry_price + rr * (entry_price - stop)`
  - `stop_atr_mult` dan `rr` harus berasal dari paramset/backtest grid dan tercatat.

### E. Notional, Qty, Fee, Slippage (tanpa persen)
Untuk menghindari ketergantungan pada fee persen broker, backtest memakai model fee **IDR** dan notional deterministik.

**E1. Notional & qty**
- Notional per trade (LOCKED): `notional_idr = 10_000_000`.
  - Jika notional ingin diubah untuk eksperimen, itu **bukan** bagian dari kontrak LOCKED: wajib dicatat sebagai metadata run (input kalibrasi) dan tidak boleh dibandingkan apple-to-apple dengan run yang notional-nya berbeda.
- Lot size (LOCKED): `lot_size = 100` saham per lot.
- Qty saham:
  - `lots = floor(notional_idr / (entry_price * lot_size))`
  - Jika `lots < 1` → trade di-skip, catat `BT_SKIP_NOT_ENOUGH_NOTIONAL`.
  - `qty = lots * lot_size`

**E2. Fee model (LOCKED)**
Pilih **satu** model dan tulis eksplisit di metadata evaluasi; model tidak boleh campur dalam satu seri evaluasi.

- Model 1 (fixed fee per side, canonical default):
  - `fee_model = IDR_FIXED`
  - `fee_buy_idr  = 2500`
  - `fee_sell_idr = 2500`

- Model 2 (tiered berdasarkan nilai transaksi):
  - `fee_model = IDR_TIERED`
  - `fee_buy_idr  = f_buy(gross_buy_idr)`
  - `fee_sell_idr = f_sell(gross_sell_idr)`
  - fungsi `f_buy/f_sell` wajib dikunci di code/table terpisah dan versioned.

Rule (LOCKED):
- Jika tidak ada model fee real yang sudah dibakukan, default kontrak adalah `IDR_FIXED` dengan `2500/2500` per sisi agar semua evaluasi tetap reproduksibel.
- Bila nanti berpindah ke `IDR_TIERED`, hasil run lama tidak boleh dibandingkan apple-to-apple tanpa label `eval_model` yang berbeda.

Catatan: jika fee real di Ajaib tersedia sebagai biaya transaksi per order, fungsi/tabel tiered boleh dikalibrasi dari sample statement, tetapi begitu dipakai harus LOCKED.

**E3. Slippage (LOCKED)**
- Default: `slippage_entry_pct = 0` dan `slippage_exit_pct = 0` (ditulis eksplisit).
- Jika dipakai:
  - `entry_eff = entry_price * (1 + slippage_entry_pct)`
  - `exit_eff  = exit_price  * (1 - slippage_exit_pct)`

### F. Return & Metric Definitions (LOCKED)
- Gross amounts:
  - `gross_buy_idr  = entry_eff * qty`
  - `gross_sell_idr = exit_eff  * qty`
- Net PnL IDR:
  - `net_pnl_idr = gross_sell_idr - gross_buy_idr - fee_buy_idr - fee_sell_idr`
- Return net:
  - `ret_net = net_pnl_idr / (gross_buy_idr + fee_buy_idr)`
- Win flag:
  - `is_win = (ret_net > 0)`

**Aggregasi metrik (LOCKED):**
- Definisi group: `group=TOP` merujuk pada picks dengan `bucket_code='TOP_PICKS'`.
- `avg_ret_net_top`: rata-rata `ret_net` untuk trades dari picks `group=TOP` di seluruh periode backtest.
- `win_rate_top`: persentase `is_win` untuk trades dari picks `group=TOP` di seluruh periode backtest.
- `picks_count`: jumlah trade yang benar-benar dieksekusi (setelah skip rules).

### G. Risk & Activity Metrics (LOCKED)

Rule (LOCKED):
Metrik di section G bersifat **computed at query/report time** untuk analisis risiko/aktivitas,
dan **bukan** kolom wajib pada `watchlist_bt_eval`, kecuali schema `watchlist_bt_eval` secara eksplisit ditambah.

- `stopout_rate_top`:
  - Definisi: `(# trade group=TOP yang exit karena STOP) / (# trade group=TOP yang dieksekusi)`
  - Exit karena STOP mengikuti aturan Assumptions → C.
- `max_drawdown_top`:
  - Definisi: maximum peak-to-trough drawdown dari equity curve `group=TOP`.
  - Equity curve dihitung dari akumulasi `net_pnl_idr` per trade (urut kronologis by exit date).
- `turnover_top_per_week`: 
  - Definisi: rata-rata jumlah trade group=TOP yang dieksekusi per minggu selama window backtest.
  - Rumus: `turnover_top_per_week` = total_executed_trades_top / total_weeks_in_window.
  - Catatan: yang dihitung hanya trade yang lolos (tidak termasuk trade yang di-skip oleh Assumptions → H).

### H. Missing Data Handling (LOCKED)
- Jika OHLC untuk hari entry tidak lengkap → skip trade, catat `BT_SKIP_MISSING_OHLC_ENTRY`.
- Jika OHLC untuk salah satu hari evaluasi (D+1..D+5) tidak lengkap:
  - Hari itu tidak dipakai untuk hit stop/target.
  - Jika sampai D+5 tidak ada close yang valid untuk time-exit → skip trade, catat `BT_SKIP_MISSING_OHLC_EXIT`.
- Semua skip harus tercatat (count) agar evaluasi tidak menipu.

### I. Ranking & Picks (LOCKED)
- Picks dibuat dari PLAN score pada D:
  - Top picks = N tertinggi yang lolos guard.
  - Secondary/Watch/Avoid mengikuti group semantics policy.
- CONFIRM tidak digunakan dalam backtest (backtest EOD-only).

### J. Determinism & Audit (LOCKED)
- Semua parameter yang mempengaruhi hasil backtest wajib berasal dari:
  - paramset/backtest grid (wajib tercatat), atau
  - konstanta LOCKED di file ini (mis. notional_idr, lot_size, slippage default).
- Jika ada perubahan angka/aturan di section ini → dianggap breaking change dan wajib re-run kalibrasi.

## Process
### 1) Backtest goals
- Maximize `avg_ret_net_top`
- Maximize `win_rate_top`
- Minimize `max_drawdown_top` dan `stopout_rate_top`
- Monitor `turnover_top_per_week` dan `picks_count`

### 2) Output backtest wajib
- `watchlist_bt_param_grid`
- `watchlist_bt_eval`
- `watchlist_bt_picks_ws`
- `watchlist_bt_universe_ws`
- `watchlist_bt_cutoffs_ws`

Tanpa `watchlist_bt_oos_eval_ws` (OOS proof), kalibrasi tidak boleh dipromote menjadi ACTIVE.

### 3) Calibration procedure (ringkas)

Dokumen ini tidak mengambil alih acceptance threshold OOS dan evaluation sufficiency; ownership threshold tetap pada file 16 dan 17.

1) Generate param grid dari seed MAN (TEMP/bt_target=true).
2) Run backtest 2 tahun untuk semua param_id.
3) Pilih best param_id dengan query canonical:

   Rule (LOCKED):
   Nilai threshold `:min_*` WAJIB berasal dari Parameter Registry (05) / paramset evaluasi.
   Tidak boleh hardcode angka di implementasi.

   Mapping parameter (LOCKED):
   - `:min_trades` -> `ws.eval.min_trades`
   - `:min_days_covered` -> `ws.eval.min_days_covered`
   - `:min_p25_ret_net_top` -> `ws.eval.min_p25_ret_net_top`
   - `:min_month_win_rate_min` -> `ws.eval.min_month_win_rate_min`
   - `:min_month_avg_ret_net_min` -> `ws.eval.min_month_avg_ret_net_min`

   SQL (canonical):
   ```sql
   SELECT pg.*
   FROM watchlist_bt_param_grid pg
   JOIN watchlist_bt_eval ev
     ON ev.policy_code = pg.policy_code
    AND ev.param_id    = pg.param_id
   WHERE pg.policy_code = 'WS'
     AND ev.picks_count >= :min_trades
     AND ev.days_covered >= :min_days_covered
     AND ev.avg_ret_net_top > 0
     AND ev.median_ret_net_top >= 0
     AND ev.p25_ret_net_top >= :min_p25_ret_net_top
     AND ev.month_win_rate_min >= :min_month_win_rate_min
     AND ev.month_avg_ret_net_min >= :min_month_avg_ret_net_min
   ORDER BY
     ev.avg_ret_net_top DESC,
     ev.median_ret_net_top DESC,
     ev.month_win_rate_min DESC,
     ev.p25_ret_net_top DESC,
     ev.win_rate_top DESC
   LIMIT 1;
   ```
4) Buat param_set baru (DRAFT):
   - parameter terkalibrasi => origin=BT, status=ACTIVE
   - parameter deterministik => origin=DET, status=ACTIVE
5) Promote param_set BT menjadi ACTIVE (lihat [`02_WS_CANONICAL_RUNTIME_FLOW.md`](02_WS_CANONICAL_RUNTIME_FLOW.md)).

## Evaluation metrics sufficiency (LOCKED)
Metrik pada `watchlist_bt_eval` wajib memenuhi spesifikasi:
[`16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md`](16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md).

Kalibrasi param_id dianggap tidak valid jika metrik minimum tidak tersedia atau gagal gating rules.

## Walk-forward / OOS proof (LOCKED)
Kalibrasi WS wajib memiliki bukti out-of-sample sesuai:
[`17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`](17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md).

Ringkasan OOS wajib tersimpan di:
`watchlist_bt_oos_eval_ws`.

## Outputs

Artefak resmi yang boleh dipakai sebagai proof atau promote harus tetap mengikuti manifest pada file 18.

- Paramset BT validated + audit trail.

## Failure modes
- Backtest dataset tidak konsisten => tidak boleh promote.

## DDL
Schema backtest (DDL) disimpan sebagai artefak di: [`db/BACKTEST_SCHEMA_DDL.sql`](db/BACKTEST_SCHEMA_DDL.sql).

## Universe rule (DET) (LOCKED)

Universe backtest untuk Weekly Swing harus deterministik dan bisa diulang.

### 1) Sumber ticker
Per `asof_eod_date`:
1) Ambil semua ticker aktif dari tabel master (mis. `tickers`) yang memiliki data OHLC EOD pada `asof_eod_date`.
2) Exclude ticker yang ada di `liquidity.exclude_tickers` (paramset MAN).

### 2) Data-quality vs eligibility (LOCKED)
Dokumen ini membedakan dua hal:
- **required_ok** = data-quality untuk kebutuhan guardrails & snapshot metrics.
- **eligible_ok** = hasil akhir eligibility untuk backtest universe, yaitu:
  `eligible_ok = required_ok AND guard_ok`.

### 3) Required fields untuk guardrails (LOCKED)
Field required minimal agar `required_ok=TRUE`:
- OHLC: `close` (dan `high/low/open` untuk evaluasi trade setelah pick)
- Volume: `volume`
- Guard/metrics snapshot: `dv20_idr`, `atr14_pct`, `vol_ratio`

Jika salah satu field di atas NULL/invalid:
- ticker tetap dicatat di `watchlist_bt_universe_ws` dengan `required_ok=FALSE`,
- `missing_fields` wajib diisi,
- `eligible_ok=FALSE`,
- `reason_code` mengikuti prioritas canonical reason pada [`15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md) (contoh: `WS_DATA_MISSING`).

### 4) Field untuk scoring (LOCKED)
Indikator yang dipakai untuk menghitung `score_total` (contoh: `roc20`, `hh20`) **tidak termasuk** daftar required fields guardrails kecuali policy WS secara eksplisit menetapkannya sebagai requirement eligibility.

Jika indikator scoring missing (LOCKED):
- ticker tetap boleh eligible selama guardrails terpenuhi,
- skor komponen yang membutuhkan indikator tersebut = 0,
- `missing_fields` tetap mencatat indikator yang missing (untuk audit), namun tidak menjatuhkan `required_ok`.
  Aturan ini harus konsisten dengan production PLAN.

Rule (LOCKED):
`missing_fields` boleh berisi gabungan field guardrails dan field scoring; namun `required_ok` hanya dipengaruhi oleh required fields guardrails (lihat Section 3).

### 5) Audit storage (LOCKED)
Untuk audit/re-run **wajib** menyimpan universe harian di `watchlist_bt_universe_ws` (lihat [`db/BACKTEST_SCHEMA_DDL.sql`](db/BACKTEST_SCHEMA_DDL.sql)) minimal berisi:
- `required_ok, missing_fields, guard_ok, eligible_ok, dv20_idr, atr14_pct, vol_ratio, reason_code`

Reason_code memakai dictionary WS_* yang **resmi di-seed** (contoh: `WS_DATA_MISSING`, `WS_LIQ_FAIL`) sesuai prioritas canonical reason di [`15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md). Alias nama lain tidak boleh dipakai.

## Schema: watchlist_bt_universe_ws (AUDIT) (LOCKED)

Tabel ini **wajib** ada untuk membuat backtest reproducible dan audit-friendly.

Lokasi DDL: [`db/BACKTEST_SCHEMA_DDL.sql`](db/BACKTEST_SCHEMA_DDL.sql)

Kolom (harus match DDL):
- `asof_eod_date` (DATE, NOT NULL) — tanggal EOD universe
- `ticker_id` (INT, NOT NULL) — id ticker
- `required_ok` (TINYINT(1), NOT NULL) — data-quality only (bukan eligibility final)
- `reason_code` (VARCHAR(32), NULL) — reason WS_* (contoh: `WS_DATA_MISSING`) saat `required_ok=0`
- `missing_fields` — daftar field required yang missing/invalid (CSV string)
- `guard_ok` — 1 jika lolos semua guardrail, 0 jika tidak
- `eligible_ok` — 1 jika required_ok=1 dan guard_ok=1
- `dv20_idr`, `atr14_pct`, `vol_ratio` — metric snapshot untuk debug equivalence

Primary key:
- `(asof_eod_date, ticker_id)`

Indexes:
- `idx_bt_univ_ws_req (asof_eod_date, required_ok)`
- `idx_bt_univ_ws_reason (asof_eod_date, reason_code)`
- `idx_bt_univ_ws_elig (asof_eod_date, eligible_ok)`

## Next
### Weekly Swing
- 13_WS_CONTRACT_TEST_CHECKLIST.md
