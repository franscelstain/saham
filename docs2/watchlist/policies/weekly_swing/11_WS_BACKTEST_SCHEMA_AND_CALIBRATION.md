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
` — picks per tanggal untuk audit dan analisis.

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

## Process
### 1) Backtest goals
- Maximize avg_ret_net_top (atau metrik yang kamu tetapkan)
- Maintain win_rate minimum
- Control drawdown / stopout rate
- Track turnover

### 2) Output backtest wajib
- `watchlist_bt_param_grid` (grid parameter)
- `watchlist_bt_eval` (hasil evaluasi per param_id)
- (optional) `watchlist_bt_picks_ws` (top picks per date)
- (optional) dataset caches per policy

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
