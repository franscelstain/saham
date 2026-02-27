# 02 — Execution Canonical — PLAN & CONFIRM (WS_EOD_PLAN_CONFIRM)

## Purpose
Mendefinisikan eksekusi end-to-end WS secara deterministik:
- preflight tanpa write
- lock
- atomic snapshot write
- supersede run lama (rerun)
- confirm overlay isolation

## Prerequisites
### Weekly Swing
01_WS_OVERVIEW.md

## Inputs
PLAN:
- asof_eod_date D (EOD hari ini)
- mode SAFE/RERUN

CONFIRM:
- plan_trade_date T
- checked_at
- runtime prices + optional bid/ask

## PLAN immutability invariant (LOCKED)

Kontrak inti: **CONFIRM tidak boleh mengubah PLAN.**

Aturan:
- CONFIRM overlay hanya menghasilkan output **terpisah** (mis. `confirm_result` / `confirm_reasons` / `confirm_label`).
- Dilarang mengubah record PLAN yang sudah tersimpan:
  - dilarang update field PLAN apa pun (termasuk `score_total`, `group_semantic`, `rank`, `entry_ref`, `stop_price`, `tp1_price`, reasons PLAN),
  - dilarang reorder ranking/urutannya,
  - dilarang “promote/demote” group berdasarkan hasil runtime.

Testable check:
- Hitung `plan_hash_before` dari record PLAN tersimpan.
- Jalankan CONFIRM overlay.
- Hitung `plan_hash_after` dari record PLAN yang sama.
- Wajib: `plan_hash_before == plan_hash_after`.

## Process

## A) PLAN
### A1. Preflight (no write)
1) Load ACTIVE param_set WS
2) Validate params_json (dok 06_WS_PARAMSET_VALIDATOR_SPEC.md)
3) Tentukan T = next_trading_day(D) via market_calendar
4) Coverage check:
   - jika reject_if_eod_incomplete=true dan coverage < `data_readiness.min_coverage_ratio` => abort
5) SAFE mode:
   - jika sudah ada active plan_run untuk T => abort (PLAN_ABORT_ALREADY_EXISTS)

Abort codes global lihat:
- `../../db/04_DB_SEED_GLOBAL.sql`

### A2. Transaction (atomic)
1) GET_LOCK('WS:PLAN:T', 10)
2) Re-check existing active run (anti race)
3) Compute PLAN (dok 08_WS_PLAN_ALGORITHM.md + 09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md)
4) Compute data_batch_hash (dok 07_WS_REASON_CODES_AND_HASH.md)
5) Determine run_status:
   - eligible_count < no_trade.min_eligible_count => NO_TRADE
6) Insert plan_run (is_active=1)
7) Bulk insert plan_items (untuk seluruh universe)
8) Jika RERUN: mark old plan_run is_active=0 (append-only items)
9) COMMIT + RELEASE_LOCK

## B) CONFIRM
### B1. Preflight (no write)
1) Load active plan_run untuk T (run_status=OK)
2) Load param_set dari plan_run + validate (dok 06_WS_PARAMSET_VALIDATOR_SPEC.md)

### B2. Transaction
1) Insert confirm_check
2) Insert confirm_items untuk ticker SHOW
3) COMMIT

Guarantee:
- Tidak ada update ke plan tables.

## Outputs
- PLAN snapshot WS
- CONFIRM overlay WS

## Failure modes
- Plan missing => CONFIRM_ABORT_NO_ACTIVE_PLAN

## Reference
- `_refs/WS_WORKED_EXAMPLE_E2E.md`
- `_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md`
- `_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`

## Next
### Weekly Swing
- 03_WS_DATA_MODEL_MARIADB.md
### Action
- `db/PROMOTE_PARAMSET.sql`
