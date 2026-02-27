# 03 — Indexes, Constraints, Invariants — Watchlist (Global)

## Purpose
Mengunci constraint dan invariants agar snapshot immutable, deterministik, dan dapat diaudit.

## Prerequisites
02_DB_SCHEMA_MARIADB.md

## Inputs
- Workload query PLAN/CONFIRM
- Rule is_active (soft-supersede)

## Process

## Invariants (wajib)
1) PLAN items append-only:
   - Tidak ada UPDATE pada rows `watchlist_plan_items`.
   - Supersede dilakukan dengan flip `watchlist_plan_runs.is_active`.

2) Satu active plan per policy_code + trade_date:
   - (policy_code, plan_trade_date) hanya boleh punya satu plan_run is_active='Yes'.
   - Enforcement: lock + transactional supersede.

3) CONFIRM isolation:
   - CONFIRM tables tidak boleh mengubah tabel PLAN.
   - Foreign key dari confirm_check ke plan_run adalah read-only relationship.

4) Paramset lifecycle:
   - Satu ACTIVE per policy_code.
   - Promotion atomic dengan lock.

## Index recommendations (minimum viable)
- watchlist_plan_runs:
  - IDX_plan_active (policy_code, plan_trade_date, is_active)
  - IDX_plan_asof   (policy_code, asof_eod_date)
- watchlist_plan_items:
  - IDX_items_run_ticker (plan_run_id, ticker_id)
  - IDX_items_policy_date_bucket (policy_code, trade_date, display_bucket)
- watchlist_confirm_checks:
  - IDX_confirm_plan_run (plan_run_id, checked_at)
- watchlist_confirm_items:
  - IDX_confirm_items_check_ticker (confirm_check_id, ticker_id)
- watchlist_param_sets:
  - IDX_param_policy_status (policy_code, status, updated_at)

## Outputs
- Invariants yang menjadi acuan implementasi DDL dan test.

## Failure modes
- Tanpa index, query dashboard akan lambat.
- Tanpa invariants, PLAN bisa “tergeser” oleh CONFIRM (bug fatal).

## Next
04_DB_SEED_GLOBAL.sql
