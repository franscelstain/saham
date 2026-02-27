# 07 — Reason Codes & Data Batch Hash — Weekly Swing

## Purpose
Menetapkan:
1) Reason codes yang dipakai WS (scope PLAN/CONFIRM)
2) Cara menghitung `data_batch_hash` untuk reproducibility WS

## Prerequisites
### Weekly Swing
06_WS_PARAMSET_VALIDATOR_SPEC.md

## Inputs
- reason dictionary seed: db/REASON_CODES_SEED.sql (policy_code='WS')
- hash_contract di params_json

## Process
### 1) Reason code rules
- PLAN items selalu memiliki `reason_codes_json` (array reason_code).
- Minimal 1 reason per ticker.
- selection_reason_code ringkas untuk quick UI.

### 2) Hash payload (WS)
Payload hashing dibangun dari tickers yang memenuhi required_fields (required_ok).
Canonical per ticker (ordered by hash_contract.order_by):
- ticker_id
- close (scaled)
- hh20 (scaled)
- roc20 (scaled)
- atr14_pct (scaled)
- dv20_idr (scaled)

Null handling:
- EXCLUDE_FROM_HASH_PAYLOAD (locked)

Hash function:
- SHA256(canonical_string)

### 3) Hash reproducibility
- Hash harus sama jika input sama + paramset same.
- Hash berubah jika salah satu field payload berubah.

## Outputs
- Data batch hash spec untuk WS.

## Failure modes
- Hash mismatch => HashContractTest fail.

## Data Batch Hash (WS) — canonical (LOCKED)

Tujuan hash: memastikan PLAN bisa direproduksi (input sama → hash sama). Implementasi berbeda tetap menghasilkan hash yang sama.

### 1) Canonical payload scope
Hash dihitung untuk PLAN run, dan harus memasukkan scope berikut:
- `policy_code = WS`
- `policy_version` (dari paramset)
- `asof_eod_date` (tanggal EOD yang dipakai PLAN)
- `hash_contract_version` (dari paramset)

### 2) Canonical payload fields per ticker
Hanya ticker dengan `required_ok=TRUE` (sesuai `hash_contract`) yang ikut hashing.

Field per ticker (urutan fixed; tidak boleh berubah tanpa bump `hash_contract_version`):
1) `ticker_id`
2) `close`
3) `hh20`
4) `roc20`
5) `atr14_pct`
6) `dv20_idr`

Ticker diurutkan: `ticker_id ASC`.



## Reason Code Dictionary (Human Index)

**Human index only.** Source-of-truth tetap: `db/REASON_CODES_SEED.sql`.

- `seed_sha256`: `ac919a05cf7a1b567a9029bf67963b6996b3c588014f470dd57a0c1fc493f269`
- `last_updated`: `2026-02-22`

## Reference
- `_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md`
- `_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`

| code | scope | severity | message_id / default message | when emitted | fields to include |
|---|---|---|---|---|---|
| `WS_DATA_MISSING` | PLAN | BLOCK | data_missing / Data wajib tidak lengkap untuk ticker ini (field input minimal tidak tersedia). | Saat field input minimal untuk ticker tidak tersedia pada batch EOD. |  |
| `WS_HIST_SHORT` | PLAN | BLOCK | hist_short / Histori data tidak cukup (min_history_days) sehingga ticker ditolak. | Saat jumlah bar historis ticker < data_readiness.min_history_days. | min_history_days, history_days |
| `WS_MISSING_BARS_60D` | PLAN | BLOCK | missing_bars_60d / Data OHLC bolong melebihi batas pada 60 hari trading terakhir. | Saat missing bars pada 60 trading days terakhir > `data_readiness.max_missing_bar_days_60d`. | `max_missing_bar_days_60d`, `missing_bars_60d` |
| `WS_OUTLIER_RET1D` | PLAN | BLOCK | outlier_ret1d / Outlier return 1 hari melebihi batas. | Saat enabled=true dan abs(ret_1d) > `max_abs_return_1d_pct`. | `max_abs_return_1d_pct`, `ret_1d_abs` |
| `WS_OUTLIER_RANGE1D` | PLAN | BLOCK | outlier_range1d / Outlier range high-low 1 hari melebihi batas. | Saat enabled=true dan (high/low - 1) > `max_high_low_range_1d_pct`. | `max_high_low_range_1d_pct`, `range_1d` |
| `WS_MOM_SOFT_MIN` | PLAN | WARN | mom_soft_min / Momentum (ROC20) di bawah ambang soft; skor momentum dinolkan. | Saat roc20 < `setup.mom_roc20_soft_min`. | `mom_roc20_soft_min`, `roc20` |
| `WS_DATA_OUTLIER` | PLAN | BLOCK | data_outlier / Terdeteksi bar outlier (indikasi data korup/tidak wajar) sehingga ticker ditolak. | Saat bar/harga/indikator terdeteksi outlier (indikasi data korup/tidak wajar). |  |
| `WS_EOD_ABORT` | PLAN | BLOCK | eod_abort / Batch data EOD dianggap tidak lengkap; PLAN dibatalkan (fail-fast). | Saat batch EOD dianggap tidak lengkap dan run PLAN dibatalkan (fail-fast). |  |
| `WS_LIQ_FAIL` | PLAN | BLOCK | liq_fail / Likuiditas gagal: dv20 di bawah batas minimum. | Saat ticker gagal guard likuiditas (dv20_idr di bawah minimum). | `dv20_idr`, `min_dv20_idr` |
| `WS_ATR_LOW` | PLAN | BLOCK | atr_low / Volatilitas terlalu rendah (ATR% di bawah batas minimum). | Saat ticker gagal guard volatilitas (ATR% di luar rentang). | `atr14_pct`, `min_atr14_pct`, `max_atr14_pct` |
| `WS_ATR_HIGH` | PLAN | BLOCK | atr_high / Volatilitas terlalu tinggi (ATR% di atas batas maksimum). | Saat ticker gagal guard volatilitas (ATR% di luar rentang). | `atr14_pct`, `min_atr14_pct`, `max_atr14_pct` |
| `WS_MOM_STRONG` | PLAN | INFO | mom_strong / Momentum kuat (ROC20 tinggi). | Saat label momentum ditentukan dari ROC20. | `roc20` |
| `WS_MOM_WEAK` | PLAN | WARN | mom_weak / Momentum lemah (ROC20 rendah). | Saat label momentum ditentukan dari ROC20. | `roc20` |
| `WS_BO_NEAR` | PLAN | INFO | bo_near / Dekat level breakout (masih di bawah HH20 tetapi sudah mendekat). | Saat label kondisi breakout ditentukan relatif terhadap HH20. | `close`, `hh20` |
| `WS_BO_BREAK` | PLAN | INFO | bo_break / Sedang/baru breakout (close di atas HH20 dan belum terlalu extended). | Saat label kondisi breakout ditentukan relatif terhadap HH20. | `close`, `hh20` |
| `WS_BO_FAR` | PLAN | WARN | bo_far / Masih jauh dari level breakout (di bawah HH20 dan belum mendekat). | Saat label kondisi breakout ditentukan relatif terhadap HH20. | `close`, `hh20` |
| `WS_BO_EXT` | PLAN | WARN | bo_ext / Sudah terlalu extended di atas HH20 (risiko chasing). | Saat label kondisi breakout ditentukan relatif terhadap HH20. | `close`, `hh20` |
| `WS_LIQ_STRONG` | PLAN | INFO | liq_strong / Likuiditas kuat (dv20 tinggi). | Saat label kekuatan likuiditas ditentukan dari dv20_idr. | `dv20_idr`, `min_dv20_idr` |
| `WS_LIQ_BORDER` | PLAN | WARN | liq_border / Likuiditas borderline (dv20 lolos minimum namun tidak kuat). | Saat label kekuatan likuiditas ditentukan dari dv20_idr. | `dv20_idr`, `min_dv20_idr` |
| `WS_RISK_IDEAL` | PLAN | INFO | risk_ideal / Volatilitas berada di rentang ideal. | Saat label risiko volatilitas ditentukan dari ATR%. | `atr14_pct`, `min_atr14_pct`, `max_atr14_pct` |
| `WS_RISK_HIGH` | PLAN | WARN | risk_high / Volatilitas cenderung tinggi (risiko stopout/drawdown lebih besar). | Saat label risiko volatilitas ditentukan dari ATR%. | `atr14_pct`, `min_atr14_pct`, `max_atr14_pct` |
| `WS_RISK_LOW` | PLAN | WARN | risk_low / Volatilitas cenderung rendah (potensi lambat bergerak). | Saat label risiko volatilitas ditentukan dari ATR%. | `atr14_pct`, `min_atr14_pct`, `max_atr14_pct` |
| `WS_FW_EXT` | PLAN | WARN | fw_ext / Dipaksa WATCH_ONLY: harga terlalu extended, hindari chasing. | Saat ticker dipaksa WATCH_ONLY oleh aturan forced-watch (extended/RR/invalid). |  |
| `WS_FW_RR_LOW` | PLAN | WARN | fw_rr_low / Dipaksa WATCH_ONLY: risk-reward tidak memenuhi minimum. | Saat ticker dipaksa WATCH_ONLY oleh aturan forced-watch (extended/RR/invalid). |  |
| `WS_FW_RR_INV` | PLAN | WARN | fw_rr_inv / Dipaksa WATCH_ONLY: perhitungan stop/TP/RR tidak valid. | Saat ticker dipaksa WATCH_ONLY oleh aturan forced-watch (extended/RR/invalid). |  |
| `WS_GRP_TOP` | PLAN | INFO | grp_top / Masuk grup TOP_PICKS berdasarkan kualitas setup. | Saat group_semantic final ditetapkan untuk ticker. |  |
| `WS_GRP_SEC` | PLAN | INFO | grp_sec / Masuk grup SECONDARY berdasarkan kualitas setup. | Saat group_semantic final ditetapkan untuk ticker. |  |
| `WS_GRP_WATCH` | PLAN | INFO | grp_watch / Masuk grup WATCH_ONLY (monitoring / tidak prioritas eksekusi). | Saat group_semantic final ditetapkan untuk ticker. |  |
| `WS_GRP_AVOID` | PLAN | INFO | grp_avoid / Masuk grup AVOID (ditolak oleh guard/data readiness atau kualitas sangat rendah). | Saat group_semantic final ditetapkan untuk ticker. |  |
| `WS_SEL_PCT` | PLAN | INFO | sel_pct / Dipilih tampil karena masuk kuota persentil teratas dan lolos ambang skor. | Saat ticker dipilih/disembunyikan oleh mekanisme selection dinamis (cutoff/target/cap). | `score_total`, `top_cutoff_today`, `secondary_cutoff_today`, `*_target_dynamic` |
| `WS_HID_PCT` | PLAN | INFO | hid_pct / Lolos ambang skor, tetapi tidak masuk kuota persentil; disembunyikan untuk menjaga fokus. | Saat ticker dipilih/disembunyikan oleh mekanisme selection dinamis (cutoff/target/cap). | `score_total`, `top_cutoff_today`, `secondary_cutoff_today`, `*_target_dynamic` |
| `WS_HID_CAP` | PLAN | INFO | hid_cap / Disembunyikan karena batas tampilan (cap) untuk menjaga keterbacaan. | Saat ticker dipilih/disembunyikan oleh mekanisme selection dinamis (cutoff/target/cap). | `display_cap`, `rank` |
| `WS_SHOW` | PLAN | INFO | show / Masuk daftar tampil (SHOW). | Saat sistem menentukan apakah ticker ditampilkan atau disembunyikan untuk keterbacaan. |  |
| `WS_HIDE` | PLAN | INFO | hide / Tidak ditampilkan (HIDE), namun tetap tersimpan untuk audit. | Saat sistem menentukan apakah ticker ditampilkan atau disembunyikan untuk keterbacaan. |  |
| `WS_RUN_CAPPED` | PLAN | WARN | run_capped / Jumlah kandidat melebihi batas tampilan; sebagian disembunyikan. | Saat sistem menentukan apakah ticker ditampilkan atau disembunyikan untuk keterbacaan. | `display_cap`, `rank` |
| `WS_STALE` | CONFIRM | BLOCK | stale / Data runtime terlalu tua (stale), hasil CONFIRM tidak bisa dipercaya. | Saat data runtime CONFIRM tidak valid (stale/missing) sehingga confirm tidak bisa dipercaya. | `runtime_ts`, `confirm_overlay.snapshot_max_age_sec` |
| `WS_NO_PRICE` | CONFIRM | BLOCK | no_price / Harga runtime tidak tersedia, tidak bisa menghitung drift. | Saat data runtime CONFIRM tidak valid (stale/missing) sehingga confirm tidak bisa dipercaya. |  |
| `WS_DRIFT_FAR` | CONFIRM | WARN | drift_far / Harga runtime terlalu jauh dari entry band (indikasi chasing atau sudah lari). | Saat CONFIRM mengevaluasi drift/band/spread (atau data bid/ask tidak tersedia). | `runtime_price`, `entry_band_low`, `entry_band_high` |
| `WS_OUT_BAND` | CONFIRM | WARN | out_band / Harga runtime berada di luar entry band. | Saat CONFIRM mengevaluasi drift/band/spread (atau data bid/ask tidak tersedia). | `runtime_price`, `entry_band_low`, `entry_band_high` |
| `WS_SPR_WIDE` | CONFIRM | WARN | spr_wide / Spread terlalu lebar (eksekusi berisiko jelek). | Saat CONFIRM mengevaluasi drift/band/spread (atau data bid/ask tidak tersedia). | `bid`, `ask`, `spread_pct`, `confirm_overlay.spread_max_pct` |
| `WS_SPR_NA` | CONFIRM | INFO | spr_na / Data bid/ask tidak diinput; spread tidak dievaluasi. | Saat CONFIRM mengevaluasi drift/band/spread (atau data bid/ask tidak tersedia). | (none) |
| `WS_LBL_OK` | CONFIRM | INFO | lbl_ok / CONFIRMED: kondisi runtime mendukung eksekusi (sekadar tambahan keyakinan). | Saat label hasil CONFIRM ditetapkan (OK/NEU/WARN/DELAY). |  |
| `WS_LBL_NEU` | CONFIRM | INFO | lbl_neu / NEUTRAL: tidak ada sinyal negatif kuat, tetapi data runtime terbatas. | Saat label hasil CONFIRM ditetapkan (OK/NEU/WARN/DELAY). |  |
| `WS_LBL_WARN` | CONFIRM | INFO | lbl_warn / CAUTION: ada warning (drift/spread/band), pertimbangkan tunda/lebih hati-hati. | Saat label hasil CONFIRM ditetapkan (OK/NEU/WARN/DELAY). |  |
| `WS_LBL_DELAY` | CONFIRM | INFO | lbl_delay / DELAY: hasil confirm tidak valid (stale/missing data). | Saat label hasil CONFIRM ditetapkan (OK/NEU/WARN/DELAY). |  |

### 3) Canonical formatting rules (LOCKED)

Bagian ini bersifat **normatif (mengikat)**; contoh bersifat **non-normatif (ilustrasi)**. Jika terjadi konflik, yang dipakai adalah aturan normatif.

#### A) Null handling (LOCKED)
- Jika field bernilai **NULL** untuk ticker yang `required_ok=TRUE`: representasikan sebagai **string kosong** pada posisi field tersebut (tetap mempertahankan posisi kolom).
- Jika `required_ok=FALSE`: ticker **dikeluarkan** dari payload (tidak ikut di-hash).

#### B) Number formatting & rounding (LOCKED)
Aturan umum:
- Gunakan **dot-decimal** (`.`), **tanpa pemisah ribuan**, **tanpa scientific notation**.
- Terapkan rounding **HALF_UP** pada jumlah decimal places (dp) yang ditentukan per field.
- Setelah rounding, jika hasilnya adalah “negative zero” (mis. `-0.000000`), **render sebagai nol positif** (`0.000000`) untuk menghindari drift lintas implementasi.

DP per field (render selalu fixed dp sesuai field):
- `ticker_id`: integer desimal **tanpa padding**.
- `close`: fixed **dp=4**
- `hh20`: fixed **dp=4**
- `roc20`: fixed **dp=6**
- `atr14_pct`: fixed **dp=4**
- `dv20_idr`: fixed **dp=0**

### 4) Canonical string format (LOCKED)
Delimiter:
- field delimiter: `,`
- ticker delimiter: `|`
Header:
`WS|policy_version=WS_EOD_PLAN_CONFIRM|asof_eod_date=YYYY-MM-DD|hash_contract_version=N|payload=`

Record per ticker:
`ticker_id,close,hh20,roc20,atr14_pct,dv20_idr`

Full canonical string:
`HEADER` + `record1|record2|...` (records sudah diurutkan ticker_id ASC)

### 5) Hash function
- `SHA-256(UTF-8 canonical string)`
- output: hex lowercase.

### 6) Example (LOCKED)

#### Test vector A (2 ticker, normal values)
Misal:
- `policy_version = WS_EOD_PLAN_CONFIRM`
- `asof_eod_date = 2026-02-21`
- `hash_contract_version = 1`
- Payload ticker (setelah sort ticker_id ASC):
  - ticker 101: close=1525.0000, hh20=1600.0000, roc20=0.012345, atr14_pct=0.0345, dv20_idr=250000000
  - ticker 205: close=980.0000,  hh20=1005.0000, roc20=-0.004000, atr14_pct=0.0450, dv20_idr=120000000

Canonical string:
`WS|policy_version=WS_EOD_PLAN_CONFIRM|asof_eod_date=2026-02-21|hash_contract_version=1|payload=101,1525.0000,1600.0000,0.012345,0.0345,250000000|205,980.0000,1005.0000,-0.004000,0.0450,120000000`

SHA-256 hex (lowercase):
`124aafa835cd14b992b30621f949be1a146051d84746614f85650fd164cd16bd`

#### Test vector B (2 ticker, edge-case NULL/0/negative-zero)
Edge-case yang dikunci:
- NULL → string kosong pada posisi field (tetap ada comma).
- 0 → tetap ditulis fixed dp sesuai field.
- “negative zero” setelah rounding → dipaksa menjadi nol positif.

Misal:
- `policy_version = WS_EOD_PLAN_CONFIRM`
- `asof_eod_date = 2026-02-21`
- `hash_contract_version = 1`
- Payload ticker (setelah sort ticker_id ASC):
  - ticker 7: close=100.1000, hh20=0.0000, roc20=NULL, atr14_pct=0.0000, dv20_idr=0
  - ticker 9: close=1.005→1.0050, hh20=1.004→1.0040, roc20=-0.0000004→0.000000, atr14_pct=NULL, dv20_idr=1234.6→1235

Canonical string:
`WS|policy_version=WS_EOD_PLAN_CONFIRM|asof_eod_date=2026-02-21|hash_contract_version=1|payload=7,100.1000,0.0000,,0.0000,0|9,1.0050,1.0040,0.000000,,1235`

SHA-256 hex (lowercase):
`0fc4e56daf36bd32bf26317f1bcfea092f30723c5e4312985feef0add84d3eaa`

## Next
### Weekly Swing
- 08_WS_PLAN_ALGORITHM.md