# 11 — Backtest Schema & Calibration — Weekly Swing (2Y)

## Purpose
## Scope lock (yang dikerjakan)
Backtest Weekly Swing yang akan diimplementasikan dibatasi pada tiga tabel berikut (dan hanya ini):

- `Backtest Weekly Swing memiliki **3 tabel evaluasi inti** dan **1 tabel audit universe (wajib)**:

**Tabel evaluasi inti (LOCKED)**
- `watchlist_bt_param_grid`
- `watchlist_bt_eval`
- `watchlist_bt_picks_ws`

**Tabel audit universe (LOCKED, wajib untuk reproducibility)**
- `watchlist_bt_universe_ws (asof_eod_date, ticker_id, required_ok, reason_code)`

Catatan: `watchlist_bt_universe_ws` bukan “opsional kosmetik”. Ini adalah kontrak audit untuk memastikan backtest bisa direplay dan evaluasi param grid fair ketika coverage data berubah.
`watchlist_bt_picks_ws` — picks per tanggal untuk audit dan analisis.

Artefak DDL ada di: `db/BACKTEST_SCHEMA_DDL.sql`.

Di luar tiga tabel tersebut adalah **out of scope** untuk versi ini dan tidak ditulis sebagai bagian spesifikasi implementasi.

Menetapkan mekanisme kalibrasi parameter WS dari backtest 2 tahun:
- menghasilkan param_set baru (origin=BT) yang dapat dipromosikan menjadi ACTIVE.

## Prerequisites
### Weekly Swing
10_WS_CONFIRM_OVERLAY.md

## Inputs
- Dataset historis 2 tahun (EOD OHLCV + indicators)
- Param grid (seed MAN) untuk eksplorasi

## Backtest execution assumptions (LOCKED)

Bagian ini mengunci cara backtest dihitung agar hasil kalibrasi 2Y reproducible dan tidak berubah karena implementasi berbeda.

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
- Notional per trade (LOCKED): `notional_idr = 10_000_000` (atau nilai lain yang kamu tetapkan).
- Lot size (LOCKED): `lot_size = 100` saham per lot.
- Qty saham:
  - `lots = floor(notional_idr / (entry_price * lot_size))`
  - Jika `lots < 1` → trade di-skip, catat `BT_SKIP_NOT_ENOUGH_NOTIONAL`.
  - `qty = lots * lot_size`

**E2. Fee model (LOCKED)**
Pilih salah satu dan tulis eksplisit (jangan campur).

- Model 1 (fixed fee per side):
  - `fee_buy_idr  = <nilai tetap>`
  - `fee_sell_idr = <nilai tetap>`

- Model 2 (tiered berdasarkan nilai transaksi):
  - `fee_buy_idr  = f_buy(gross_buy_idr)`  (fungsi piecewise/tabel tier ditulis di dok)
  - `fee_sell_idr = f_sell(gross_sell_idr)`

Catatan: jika fee real di Ajaib tersedia sebagai “biaya transaksi” per order, kamu bisa kalibrasi f_buy/f_sell dari sample statement. Yang penting fungsi/tabelnya LOCKED.

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
- `avg_ret_net_top`: rata-rata `ret_net` untuk trades dari picks `group=TOP` di seluruh periode backtest.
- `win_rate_top`: persentase `is_win` untuk trades dari picks `group=TOP` di seluruh periode backtest.
- `picks_count`: jumlah trade yang benar-benar dieksekusi (setelah skip rules).

### G. Risk & Activity Metrics (LOCKED)
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
  - konstanta LOCKED di dok ini (mis. notional_idr, lot_size, slippage default).
- Jika ada perubahan angka/aturan di section ini → dianggap breaking change dan wajib re-run kalibrasi.

## Process
### 1) Backtest goals
- Maximize `avg_ret_net_top`
- Maximize `win_rate_top`
- Minimize `max_drawdown_top` dan `stopout_rate_top`
- Monitor `turnover_top_per_week` dan `picks_count`

### 2) Output backtest wajib
- `watchlist_bt_param_grid` (grid parameter)
- `watchlist_bt_eval` (hasil evaluasi per param_id)
- WAJIB/LOCKED `watchlist_bt_picks_ws` (top picks per date)

### 3) Calibration procedure (ringkas)
1) Generate param grid dari seed MAN (TEMP/bt_target=true).
2) Run backtest 2 tahun untuk semua param_id.
3) Pilih best param_id dengan query canonical:
   SELECT *
   FROM watchlist_bt_param_grid
   WHERE policy_code='WS'
     AND param_id = (
       SELECT param_id
       FROM watchlist_bt_eval
       WHERE policy_code='WS'
       ORDER BY avg_ret_net_top DESC
       LIMIT 1
     );
4) Buat param_set baru (DRAFT):
   - parameter terkalibrasi => origin=BT, status=ACTIVE
   - parameter deterministik => origin=DET, status=ACTIVE
5) Promote param_set BT menjadi ACTIVE (lihat dok 02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md).

## Outputs
- Paramset BT validated + audit trail.

## Failure modes
- Backtest dataset tidak konsisten => tidak boleh promote.

## DDL
Schema backtest (DDL) disimpan sebagai artefak di: `db/BACKTEST_SCHEMA_DDL.sql`.

## Universe rule (DET) (LOCKED)

Universe backtest untuk Weekly Swing harus deterministik dan bisa diulang. Aturan universe (per `asof_eod_date`):
1) Ambil semua ticker aktif dari tabel master (mis. `tickers`) yang memiliki OHLC EOD pada `asof_eod_date`.
2) Exclude ticker yang ada di `liquidity.exclude_tickers` (paramset MAN).
3) Field required minimal untuk masuk universe: `close`, `volume`, dan indikator yang dipakai guards/scoring (`dv20_idr`, `atr14_pct`, `roc20`, `hh20`). Jika field required NULL → ticker tetap tercatat di universe dengan flag `required_ok=FALSE`.

Untuk audit/re-run **wajib** menyimpan universe harian sebagai tabel ringkas (dibuat di `db/BACKTEST_SCHEMA_DDL.sql`):
- `watchlist_bt_universe_ws(asof_eod_date, ticker_id, required_ok, reason_code)`
Reason_code memakai dictionary WS_* (contoh: `WS_DATA_MISSING`).

## Schema: watchlist_bt_universe_ws (AUDIT) (LOCKED)

Tabel ini **wajib** ada untuk membuat backtest reproducible dan audit-friendly.

Lokasi DDL: `db/BACKTEST_SCHEMA_DDL.sql`

Kolom (harus match DDL):
- `asof_eod_date` (DATE, NOT NULL) — tanggal EOD universe
- `ticker_id` (INT, NOT NULL) — id ticker
- `required_ok` (TINYINT(1), NOT NULL) — 1 jika field required tersedia, 0 jika tidak
- `reason_code` (VARCHAR(32), NULL) — reason WS_* (contoh: `WS_DATA_MISSING`) saat `required_ok=0`
Primary key:
- `(asof_eod_date, ticker_id)`
Indexes:
- `idx_bt_univ_ws_req (asof_eod_date, required_ok)`
- `idx_bt_univ_ws_reason (asof_eod_date, reason_code)`

## Next
### Weekly Swing
- 12_WS_CONTRACT_TEST_CHECKLIST.md
