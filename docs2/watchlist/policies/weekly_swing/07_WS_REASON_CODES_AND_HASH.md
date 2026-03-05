# 07 — Reason Codes & Data Batch Hash — Weekly Swing

## Purpose
Dokumen ini mengunci:
1) dictionary reason code `WS_*` yang dipakai Weekly Swing untuk **PLAN**, **CONFIRM**, dan governance **BT**
2) batas namespace antar layer agar `WS_*` tidak bercampur dengan `CF_*`, `PLAN_ABORT_*`, `CONFIRM_ABORT_*`, atau `WS_CT_*`
3) cara menghitung `data_batch_hash` untuk reproducibility (PLAN)

## Prerequisites
### Weekly Swing
06_WS_PARAMSET_VALIDATOR_SPEC.md

## Inputs
- reason dictionary seed: [`db/REASON_CODES_SEED.sql`](db/REASON_CODES_SEED.sql) (policy_code='WS')
- `hash_contract` di params_json

## Namespace Rule (LOCKED)

Untuk menghindari campur aduk code antar layer:

- Prefix `PLAN_ABORT_*`
  - dipakai untuk `fail_code` run-level PLAN yang berstatus `FAILED`.

- Prefix `CONFIRM_ABORT_*`
  - dipakai untuk `fail_code` run-level CONFIRM jika ada hard-fail run-level.

- Prefix `CF_*`
  - dipakai untuk kegagalan **contract/validator/test** lintas dokumen (lihat `../_shared/07_CONTRACT_FAILURE_CODES_LOCKED.md`).

- Prefix `WS_CT_*`
  - dipakai sebagai **ID test case** di [`13_WS_CONTRACT_TEST_CHECKLIST.md`](13_WS_CONTRACT_TEST_CHECKLIST.md); **bukan** reason code runtime.

- Prefix `WS_*`
  - dipakai untuk reason code policy WS pada item-level/runtime/governance yang **resmi** di-seed pada [`db/REASON_CODES_SEED.sql`](db/REASON_CODES_SEED.sql).

Contoh:
- `PLAN_ABORT_DATA_INCOMPLETE` → fail_code run-level PLAN
- `CF_HASH_CONTRACT_VIOLATION` → contract failure code validator/test
- `WS_FW_RR_LOW` → reason code item PLAN
- `WS_NO_TRADE_MIN_ELIGIBLE` → reason code policy-specific pada PLAN
- `WS_CT_021` → ID test OOS proof guard, **bukan** reason code

## A. Canonical WS Reason Dictionary (LOCKED)

Aturan umum:
- Source of truth dictionary `WS_*` adalah [`db/REASON_CODES_SEED.sql`](db/REASON_CODES_SEED.sql).
- Dokumen ini **harus** mencakup seluruh reason code `WS_*` yang ada di seed.
- Jika seed berubah, dokumen ini wajib ikut berubah pada commit yang sama.
- `message` runtime boleh berbeda gaya bahasa, tetapi `code`, `scope`, dan `severity` tidak boleh drift dari seed.

### A1) Rules umum runtime
- PLAN items **wajib** memiliki `reason_codes_json` (array reason_code).
- Minimal 1 reason per ticker.
- `selection_reason_code` adalah ringkasan untuk UI (1 kode) dan harus berasal dari dictionary `WS_*` yang sah.
- Untuk canonical fail reason guardrails, lihat [`15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md).

### A2) Full dictionary from seed (LOCKED)

#### Scope `PLAN`
| Code | Severity | Short ID | Deskripsi singkat |
|---|---|---|---|
| `WS_DATA_MISSING` | `BLOCK` | `data_missing` | Data wajib tidak lengkap untuk ticker ini (field input minimal tidak tersedia). |
| `WS_HIST_SHORT` | `BLOCK` | `hist_short` | Histori data tidak cukup (min_history_days) sehingga ticker ditolak. |
| `WS_MISSING_BARS_60D` | `BLOCK` | `missing_bars_60d` | Data OHLC bolong melebihi batas pada 60 hari trading terakhir. |
| `WS_OUTLIER_RET1D` | `BLOCK` | `outlier_ret1d` | Outlier return 1 hari melebihi batas. |
| `WS_OUTLIER_RANGE1D` | `BLOCK` | `outlier_range1d` | Outlier range high-low 1 hari melebihi batas. |
| `WS_MOM_SOFT_MIN` | `WARN` | `mom_soft_min` | Momentum (ROC20) di bawah ambang soft; skor momentum dinolkan. |
| `WS_DATA_OUTLIER` | `BLOCK` | `data_outlier` | Terdeteksi bar outlier (indikasi data korup/tidak wajar) sehingga ticker ditolak. |
| `WS_EOD_ABORT` | `BLOCK` | `eod_abort` | Batch data EOD dianggap tidak lengkap; PLAN dibatalkan (fail-fast). |
| `WS_LIQ_FAIL` | `BLOCK` | `liq_fail` | Likuiditas gagal: dv20 di bawah batas minimum. |
| `WS_ATR_LOW` | `BLOCK` | `atr_low` | Volatilitas terlalu rendah (ATR% di bawah batas minimum). |
| `WS_ATR_HIGH` | `BLOCK` | `atr_high` | Volatilitas terlalu tinggi (ATR% di atas batas maksimum). |
| `WS_VOLR_FAIL` | `BLOCK` | `volr_fail` | Konfirmasi volume gagal: vol_ratio di bawah batas minimum. |
| `WS_MOM_STRONG` | `INFO` | `mom_strong` | Momentum kuat (ROC20 tinggi). |
| `WS_MOM_WEAK` | `WARN` | `mom_weak` | Momentum lemah (ROC20 rendah). |
| `WS_BO_NEAR` | `INFO` | `bo_near` | Dekat level breakout (masih di bawah HH20 tetapi sudah mendekat). |
| `WS_BO_BREAK` | `INFO` | `bo_break` | Sedang/baru breakout (close di atas HH20 dan belum terlalu extended). |
| `WS_BO_FAR` | `WARN` | `bo_far` | Masih jauh dari level breakout (di bawah HH20 dan belum mendekat). |
| `WS_BO_EXT` | `WARN` | `bo_ext` | Sudah terlalu extended di atas HH20 (risiko chasing). |
| `WS_LIQ_STRONG` | `INFO` | `liq_strong` | Likuiditas kuat (dv20 tinggi). |
| `WS_LIQ_BORDER` | `WARN` | `liq_border` | Likuiditas borderline (dv20 lolos minimum namun tidak kuat). |
| `WS_RISK_IDEAL` | `INFO` | `risk_ideal` | Volatilitas berada di rentang ideal. |
| `WS_RISK_HIGH` | `WARN` | `risk_high` | Volatilitas cenderung tinggi (risiko stopout/drawdown lebih besar). |
| `WS_RISK_LOW` | `WARN` | `risk_low` | Volatilitas cenderung rendah (potensi lambat bergerak). |
| `WS_FW_EXT` | `WARN` | `fw_ext` | Dipaksa WATCH_ONLY: harga terlalu extended, hindari chasing. |
| `WS_FW_RR_LOW` | `WARN` | `fw_rr_low` | Dipaksa WATCH_ONLY: risk-reward tidak memenuhi minimum. |
| `WS_FW_RR_INV` | `WARN` | `fw_rr_inv` | Dipaksa WATCH_ONLY: perhitungan stop/TP/RR tidak valid. |
| `WS_GRP_TOP` | `INFO` | `grp_top` | Masuk grup TOP_PICKS berdasarkan kualitas setup. |
| `WS_GRP_SEC` | `INFO` | `grp_sec` | Masuk grup SECONDARY berdasarkan kualitas setup. |
| `WS_GRP_WATCH` | `INFO` | `grp_watch` | Masuk grup WATCH_ONLY (monitoring / tidak prioritas eksekusi). |
| `WS_GRP_AVOID` | `INFO` | `grp_avoid` | Masuk grup AVOID (ditolak oleh guard/data readiness atau kualitas sangat rendah). |
| `WS_SEL_PCT` | `INFO` | `sel_pct` | Dipilih tampil karena masuk kuota persentil teratas dan lolos ambang skor. |
| `WS_HID_PCT` | `INFO` | `hid_pct` | Lolos ambang skor, tetapi tidak masuk kuota persentil; disembunyikan untuk menjaga fokus. |
| `WS_HID_CAP` | `INFO` | `hid_cap` | Disembunyikan karena batas tampilan (cap) untuk menjaga keterbacaan. |
| `WS_SHOW` | `INFO` | `show` | Masuk daftar tampil (SHOW). |
| `WS_HIDE` | `INFO` | `hide` | Tidak ditampilkan (HIDE), namun tetap tersimpan untuk audit. |
| `WS_RUN_CAPPED` | `WARN` | `run_capped` | Jumlah kandidat melebihi batas tampilan; sebagian disembunyikan. |
| `WS_NO_TRADE_MIN_ELIGIBLE` | `INFO` | `no_trade_min_eligible` | Run valid tetapi tidak menampilkan kandidat karena jumlah eligible lebih kecil dari batas minimum. |
| `WS_PLAN_HASH_MISMATCH` | `BLOCK` | `plan_hash_mismatch` | Hash PLAN berubah setelah CONFIRM atau setelah proses audit; ini melanggar invariant immutability. |
| `WS_PLAN_WRITEBACK_DETECTED` | `BLOCK` | `plan_writeback` | Terdeteksi operasi tulis ke persistence PLAN saat proses CONFIRM; ini melanggar DB write-scope contract. |
| `WS_NO_TRADE_ALL_FILTERED` | `BLOCK` | `no_trade_all_filtered` | Semua kandidat terfilter; tidak ada rencana transaksi. |
| `WS_BT_EVAL_DOWNSIDE_FAIL` | `BLOCK` | `bt_eval_downside_fail` | BT eval downside bound gagal (p25/min di bawah toleransi). |
| `WS_BT_EVAL_METRICS_MISSING` | `BLOCK` | `bt_eval_metrics_missing` | BT eval metrics wajib tidak lengkap, sufficiency guard gagal. |
| `WS_BT_EVAL_MIN_DAYS_FAIL` | `BLOCK` | `bt_eval_min_days_fail` | BT eval days_covered < ws.eval.min_days_covered. |
| `WS_BT_EVAL_MIN_TRADES_FAIL` | `BLOCK` | `bt_eval_min_trades_fail` | BT eval picks_count < ws.eval.min_trades. |
| `WS_BT_EVAL_ROBUST_RETURN_FAIL` | `BLOCK` | `bt_eval_robust_return_fail` | BT eval avg/median return tidak memenuhi syarat robust return. |
| `WS_BT_EVAL_STABILITY_FAIL` | `BLOCK` | `bt_eval_stability_fail` | BT eval stability across periods gagal (month_*_min). |

#### Scope `CONFIRM`
| Code | Severity | Short ID | Deskripsi singkat |
|---|---|---|---|
| `WS_SNAPSHOT_MISSING` | `BLOCK` | `snap_missing` | Snapshot intraday belum diinput; CONFIRM tidak bisa dijalankan. |
| `WS_STALE` | `BLOCK` | `stale` | Data runtime terlalu tua (stale), hasil CONFIRM tidak bisa dipercaya. |
| `WS_INPUT_INCOMPLETE` | `BLOCK` | `input_incomplete` | Field intraday aggregate wajib tidak lengkap (mis. turnover/volume). |
| `WS_NO_PRICE` | `BLOCK` | `no_price` | Harga runtime tidak tersedia, tidak bisa menghitung drift. |
| `WS_CONFIRM_OK` | `INFO` | `confirm_ok` | Snapshot valid dan cukup sehat; hasil CONFIRM boleh diberi label `CONFIRMED`. |
| `WS_CONFIRM_NEUTRAL` | `INFO` | `confirm_neutral` | Snapshot valid tetapi tidak memberi sinyal kuat tambahan; hasil CONFIRM diberi label `NEUTRAL`. |
| `WS_CONFIRM_VOL_WEAK` | `WARN` | `confirm_vol_weak` | Volume runtime relatif lemah; validasi breakout/momentum berkurang. |
| `WS_DRIFT_FAR` | `WARN` | `drift_far` | Harga runtime terlalu jauh dari entry band (indikasi chasing atau sudah lari). |
| `WS_OUT_BAND` | `WARN` | `out_band` | Harga runtime berada di luar entry band. |

#### Scope `BT`
| Code | Severity | Short ID | Deskripsi singkat |
|---|---|---|---|
| `WS_BT_COV_CUTOFFS_MISSING` | `BLOCK` | `bt_cov_cutoffs_missing` | BT coverage gagal: data cutoff tidak tersedia. |
| `WS_BT_COV_GRID_MISSING` | `BLOCK` | `bt_cov_grid_missing` | BT coverage gagal: kolom grid yang wajib tidak ada. |
| `WS_BT_COV_MATRIX_MISSING` | `BLOCK` | `bt_cov_matrix_missing` | BT coverage gagal: parameter BT tidak ada di coverage matrix. |
| `WS_BT_COV_PICK_VIOLATION` | `BLOCK` | `bt_cov_pick_violation` | BT coverage gagal: picks melanggar aturan cutoff. |
| `WS_BT_OOS_DRAWDOWN_FAIL` | `BLOCK` | `bt_oos_drawdown_fail` | BT OOS proof gagal: drawdown/risiko di OOS melewati batas. |
| `WS_BT_OOS_METRICS_FAIL` | `BLOCK` | `bt_oos_metrics_fail` | BT OOS proof gagal: metrik test tidak memenuhi acceptance criteria. |
| `WS_BT_OOS_PROOF_MISSING` | `BLOCK` | `bt_oos_proof_missing` | BT OOS proof tidak tersedia (artifact hilang / kosong). |
| `WS_BT_OOS_STABILITY_FAIL` | `BLOCK` | `bt_oos_stability_fail` | BT OOS proof gagal: stabilitas OOS (across windows) tidak memenuhi syarat. |
| `WS_BT_OOS_WINDOW_INSUFFICIENT` | `BLOCK` | `bt_oos_window_insufficient` | BT OOS proof gagal: ukuran window/holdout tidak memenuhi batas minimal. |
| `WS_BT_ARTIFACT_MISSING` | `BLOCK` | `bt_artifact_missing` | BT artifact reference guard gagal: artefak wajib tidak ditemukan. |
| `WS_BT_ARTIFACT_NOT_ALLOWED` | `BLOCK` | `bt_artifact_not_allowed` | BT artifact reference guard gagal: artefak tidak ada di allowlist. |
| `WS_BT_ARTIFACT_PARAM_ID_MISMATCH` | `BLOCK` | `bt_artifact_param_id_mismatch` | BT artifact reference guard gagal: param_id artifact tidak konsisten. |

### A3) Clarification for governance/test references (LOCKED)
- Reason codes `WS_BT_*` adalah **reason code governance BT** yang sah karena memang ada di seed scope `BT` atau `PLAN` sesuai definisinya.
- `WS_PLAN_HASH_MISMATCH` dan `WS_PLAN_WRITEBACK_DETECTED` adalah reason code audit/governance WS yang sah karena ada di seed.
- `WS_RUNTIME_OUTPUT_SCHEMA` **bukan** reason code; itu nama dokumen referensi.
- `WS_CT_*` **bukan** reason code; itu ID test inventory.

## B. Data Batch Hash (PLAN) — canonical (LOCKED)

Payload hashing dibangun dari ticker yang memenuhi required_fields (required_ok).
Canonical per ticker (ordered by `hash_contract.order_by`):
- `ticker_id`
- `close` (scaled ke `close_price_dp`)
- `hh20` (scaled ke `hh20_dp`)
- `roc20` (scaled ke `roc20_dp`)
- `atr14_pct` (scaled ke `atr14_pct_dp`)
- `dv20_idr` (scaled ke `dv20_idr_dp`)

Canonical string (LOCKED):
- Bentuk canonical payload adalah **compact JSON array** UTF-8 (tanpa whitespace tambahan).
- Urutan row wajib `ticker_id ASC`.
- Urutan key per object wajib tetap: `ticker_id`, `close`, `hh20`, `roc20`, `atr14_pct`, `dv20_idr`.
- Semua angka diskalakan ke fixed decimal places sesuai `hash_contract.scales` lalu diserialisasi sebagai string numerik fixed-width.
- Negative zero wajib dinormalisasi menjadi zero (contoh: `-0.000000` => `0.000000`).

Null handling:
- `EXCLUDE_FROM_HASH_PAYLOAD` (LOCKED): row yang tidak memenuhi required fields/`required_ok=false` dikeluarkan dari payload hash; field optional non-hash tidak boleh ikut masuk.

Hash function:
- `SHA256(canonical_string)` → hex lowercase.

### Test vectors (LOCKED)
Source of truth vector: [`fixtures/hash_contract_vectors.json`](fixtures/hash_contract_vectors.json)
- Vector A expected SHA256: `3e4ca1c57a959a3858e0719b16f9798a197fce3f6b35a87605fdb7d69f0f1c22`
- Vector B expected SHA256: `f6f9a89dbdcb0182a3df1533ba2e7483db9ff0a0a469c8aeb6f84e06da5b741a`

Rule:
- Implementasi wajib menghasilkan hash persis sama dengan fixture di atas.
- Jika format canonical string berubah, fixture vectors + dokumen ini + validator + contract test harus diperbarui pada commit yang sama.

### Reproducibility (LOCKED)
- Hash harus sama jika input sama + paramset sama.
- Hash berubah jika salah satu field payload berubah.

## Failure modes
- Hash mismatch → Contract/Test fail.

## Next
### Weekly Swing
- 08_WS_PLAN_ALGORITHM.md
