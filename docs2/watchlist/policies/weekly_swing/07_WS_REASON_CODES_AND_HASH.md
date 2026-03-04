# 07 — Reason Codes & Data Batch Hash — Weekly Swing

## Purpose
Dokumen ini mengunci:
1) Reason codes yang dipakai Weekly Swing untuk **PLAN** dan **CONFIRM**
2) Cara menghitung `data_batch_hash` untuk reproducibility (PLAN)

## Prerequisites
### Weekly Swing
06_WS_PARAMSET_VALIDATOR_SPEC.md

## Inputs
- reason dictionary seed: `db/REASON_CODES_SEED.sql` (policy_code='WS')
- `hash_contract` di params_json

## Namespace Rule (LOCKED)

Untuk menghindari campur aduk code antar layer:

- Prefix `PLAN_ABORT_*`
  - dipakai untuk `fail_code` run-level PLAN yang berstatus `FAILED`.

- Prefix `CONFIRM_ABORT_*`
  - dipakai untuk `fail_code` run-level CONFIRM jika ada hard-fail run-level.

- Prefix `WS_*`
  - dipakai untuk reason code policy WS pada item-level atau status policy-specific seperti:
    - scoring
    - grouping
    - forced watch-only
    - no-trade policy-specific reason

Contoh:
- `PLAN_ABORT_DATA_INCOMPLETE` → fail_code run-level PLAN
- `WS_FW_RR_LOW` → reason code item PLAN
- `WS_NO_TRADE_MIN_ELIGIBLE` → reason code / fail_code policy-specific untuk NO_TRADE yang valid

---

## A. Reason Codes (LOCKED)

### A1) Rules umum
- PLAN items **wajib** memiliki `reason_codes_json` (array reason_code).
- Minimal 1 reason per ticker.
- `selection_reason_code` adalah ringkasan untuk UI (1 kode).

### A2) Reason codes khusus CONFIRM (LOCKED)

CONFIRM memakai intraday snapshot manual (intraday aggregate dari Ajaib). Failure yang harus eksplisit:

- `WS_SNAPSHOT_MISSING` (BLOCK, CONFIRM)
- `WS_STALE` (BLOCK, CONFIRM)
- `WS_INPUT_INCOMPLETE` (BLOCK, CONFIRM)
  Salah satu dari field wajib intraday aggregate tidak ada (mis. turnover/volume).

- `WS_CONFIRM_OK` (INFO, CONFIRM)
  Snapshot valid; tidak ada red-flag intraday.
- `WS_CONFIRM_NEUTRAL` (INFO, CONFIRM)
  Tidak ada sinyal tambahan; PLAN tetap berlaku.
- `WS_CONFIRM_VOL_WEAK` (WARN, CONFIRM)
  Volume/turnover intraday lemah dibanding ekspektasi; jangan agresif.

Catatan:
- Order book ladder (bid/ask/spread/imbalance) bukan input keputusan CONFIRM dan tidak boleh jadi reason wajib.

### A3) Reason codes BT coverage guard (LOCKED)

Reason codes berikut dipakai untuk governance backtest (BT) agar parameter origin=BT tidak bisa dipakai tanpa bukti artefak.
Semua code di bawah ini ber-scope `BT` dan severity `BLOCK` (lihat seed: `db/REASON_CODES_SEED.sql`).

- `WS_BT_COV_MATRIX_MISSING`
  Parameter origin=BT tidak tercantum pada matrix (14).
- `WS_BT_COV_GRID_MISSING`
  Kolom grid yang dipetakan tidak ada pada artefak `watchlist_bt_param_grid`.
- `WS_BT_COV_CUTOFFS_MISSING`
  Row cutoff untuk `(param_id, asof_eod_date)` tidak tersedia.
- `WS_BT_COV_PICK_VIOLATION`
  Picks melanggar cutoff yang dipersist (mis. TOP_PICKS tetapi `score_total < top_cutoff_score`).

### A4) Reason codes untuk Contract/Test Audit (LOCKED)
Kode berikut dipakai untuk kegagalan **kontrak** pada test suite/audit, bukan untuk keputusan runtime UI:

- `WS_PLAN_WRITEBACK_DETECTED` (HARD, AUDIT)  
  Terdeteksi operasi tulis ke persistence PLAN saat proses CONFIRM. Ini melanggar “DB write scope (CONFIRM)” dan wajib membuat test **FAIL**.

Catatan:
- `WS_PLAN_HASH_MISMATCH` tetap dipakai jika hash PLAN berubah.
- Definisi `plan_hash` (LOCKED) mengikuti `_refs/WS_RUNTIME_OUTPUT_SCHEMA.md` bagian **3.2.2 Canonicalization for `meta.plan_hash`**.

- `WS_PLAN_WRITEBACK_DETECTED` dipakai jika audit mendeteksi write ke PLAN walau hash kebetulan tidak berubah.

---

## A5) BT OOS proof guard (LOCKED)

Dipakai oleh `WS_CT_021` untuk memastikan parameter/policy berbasis hasil backtest **tidak boleh dipromosikan** tanpa bukti *out-of-sample* (OOS).

Kontrak (LOCKED):
- Jika artifact OOS tidak ada / kosong → FAIL dengan:
  - `cf_code = CF_OOS_PROOF_FAILED`
  - reason: `WS_BT_OOS_PROOF_MISSING`
- Jika konfigurasi window/holdout tidak memenuhi batas minimal (mis. test terlalu kecil) → FAIL:
  - `cf_code = CF_OOS_PROOF_FAILED`
  - reason: `WS_BT_OOS_WINDOW_INSUFFICIENT`
- Jika metrik OOS (test) tidak memenuhi acceptance criteria → FAIL:
  - `cf_code = CF_OOS_PROOF_FAILED`
  - reason: `WS_BT_OOS_METRICS_FAIL`
- Jika drawdown/risiko OOS melampaui batas → FAIL:
  - `cf_code = CF_OOS_PROOF_FAILED`
  - reason: `WS_BT_OOS_DRAWDOWN_FAIL`
- Jika stabilitas OOS across windows gagal → FAIL:
  - `cf_code = CF_OOS_PROOF_FAILED`
  - reason: `WS_BT_OOS_STABILITY_FAIL`

Catatan (LOCKED):
- Reason codes di atas bersifat **BLOCK** dan harus memblok promosi paramset origin=BT.

## B. Data Batch Hash (PLAN) — canonical (LOCKED)

Payload hashing dibangun dari ticker yang memenuhi required_fields (required_ok).
Canonical per ticker (ordered by hash_contract.order_by):
- ticker_id
- close (scaled)
- hh20 (scaled)
- roc20 (scaled)
- atr14_pct (scaled)
- dv20_idr (scaled)

Null handling:
- `EXCLUDE_FROM_HASH_PAYLOAD` (locked)

Hash function:
- `SHA256(canonical_string)`

### Reproducibility (LOCKED)
- Hash harus sama jika input sama + paramset sama.
- Hash berubah jika salah satu field payload berubah.

---

## Failure modes
- Hash mismatch → Contract/Test fail.

## A4) BT eval metrics sufficiency guard (LOCKED)

Reason codes berikut dipakai saat guard metrik evaluasi backtest gagal (WS_CT_020):

- `WS_BT_EVAL_METRICS_MISSING` — metrik wajib pada `watchlist_bt_eval` tidak lengkap.
- `WS_BT_EVAL_MIN_TRADES_FAIL` — `picks_count` di bawah `ws.eval.min_trades`.
- `WS_BT_EVAL_MIN_DAYS_FAIL` — `days_covered` di bawah `ws.eval.min_days_covered`.
- `WS_BT_EVAL_ROBUST_RETURN_FAIL` — `avg_ret_net_top` / `median_ret_net_top` tidak memenuhi syarat robust return.
- `WS_BT_EVAL_DOWNSIDE_FAIL` — downside bound gagal (`p25_ret_net_top` atau `min_ret_net_top` di bawah toleransi).
- `WS_BT_EVAL_STABILITY_FAIL` — stabilitas antar periode gagal (`month_win_rate_min` / `month_avg_ret_net_min`).

Catatan (LOCKED):
- `cf_code` yang dipakai untuk WS_CT_020 adalah `CF_EVAL_METRICS_INSUFFICIENT`.
- Reason codes di atas adalah pelengkap untuk output PLAN/guardrails; `cf_code` tetap canonical untuk contract test.

## A6) BT artifact reference guard (LOCKED)

Dipakai untuk WS_CT_022. Guard ini memastikan referensi artefak backtest **nyata dan audit-able**.

- `WS_BT_ARTIFACT_MISSING` — artefak wajib tidak ada / tidak bisa ditemukan.
- `WS_BT_ARTIFACT_NOT_ALLOWED` — artefak direferensikan tetapi tidak ada di allowlist.
- `WS_BT_ARTIFACT_PARAM_ID_MISMATCH` — artefak punya `param_id` tetapi tidak match dengan `param_id` yang diklaim.

Catatan (LOCKED):
- `cf_code` yang dipakai untuk WS_CT_022 adalah `CF_ARTIFACT_REFERENCE_VIOLATION`.
- Semua reason di atas bersifat BLOCK.

## Next
### Weekly Swing
- 08_WS_PLAN_ALGORITHM.md