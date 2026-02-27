# 03 — Data Model (MariaDB 10.4) — Weekly Swing

## Purpose
Menetapkan mapping kebutuhan WS terhadap schema global watchlist (tabel global ada di `../../db/`). Dokumen ini tidak mendefinisikan DDL tabel global.

## Prerequisites
### Weekly Swing
02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md

## Inputs
- MariaDB 10.4
- Universe tickers (table master)

## Process
### Prinsip schema (WS)
- PLAN snapshot tables bersifat **append-only** untuk items.
- Supersede dilakukan dengan flip `plan_runs.is_active` saja.
- Semua ticker di universe memiliki row di `watchlist_plan_items` (audit).

### Mapping ke tabel global (konseptual)
1) `watchlist_param_sets`
- param_set_id (PK)
- policy_code, policy_version
- status (DRAFT/ACTIVE/DEPRECATED)
- params_json (LONGTEXT/JSON stored as text in MariaDB 10.4)
- created_at, updated_at

2) `watchlist_plan_runs`
- plan_run_id (PK)
- policy_code, policy_version
- asof_eod_date, plan_trade_date
- param_set_id (FK)
- run_status (OK/NO_TRADE/FAILED)
- data_batch_hash, hash_count, missing_required_count
- processed_count, eligible_count
- supersedes_plan_run_id (nullable)
- is_active (Yes/No)
- created_at

3) `watchlist_plan_items`
- plan_item_id (PK)
- plan_run_id (FK)
- trade_date (plan_trade_date)
- ticker_id, ticker_code (optional cache)
- group_semantic (TOP_PICKS/SECONDARY/WATCH_ONLY/AVOID)
- display_bucket (SHOW/HIDE)
- selection_reason_code (ringkas)
- score_total + component scores
- inputs: close, hh20, roc20, atr14_pct, dv20_idr
- plan_levels: entry_ref, entry_low, entry_high, stop_price, tp1_price, rr
- reason_codes_json (array reason_code)
- created_at

4) CONFIRM:
- `watchlist_confirm_checks` (header)
- `watchlist_confirm_items` (detail per ticker)

5) Dictionaries:
- `watchlist_reason_codes` (WS-specific dictionary)
- `watchlist_fail_codes` (global dictionary; folder `_shared`)

## Outputs
- Daftar table & invariants yang harus diimplementasikan.

## Failure modes
- Update plan_items (melanggar append-only) => tidak boleh.

## Next
### Weekly Swing
- 04_WS_PARAMSET_JSON_CONTRACT.md
