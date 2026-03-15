# 02 — Schema (MariaDB 10.4) — Watchlist (Global)

## Purpose
Mendefinisikan daftar tabel global watchlist beserta kolom minimum yang stabil lintas policy.

## Prerequisites
01_DB_OVERVIEW.md

## Inputs
- MariaDB 10.4 (JSON disimpan sebagai LONGTEXT untuk kompatibilitas)
- Kebijakan supersede PLAN memakai `is_active` satu arah (`Yes -> No`)

## A) Dictionary tables

### 1) watchlist_fail_codes (global)
Untuk abort/failure di level run.
- fail_code (PK)
- scope (PLAN/CONFIRM/BOTH)
- severity (INFO/WARN/ERROR)
- description_id
- created_at

Catatan: bukan milik policy tertentu.

### 2) watchlist_reason_codes (global with policy_code)
Untuk dictionary reason code policy-level/item-level watchlist, termasuk reason runtime `PLAN`, `CONFIRM`, dan governance `BT` bila policy memang menguncinya.
- policy_code (part of PK)
- reason_code (part of PK)
- scope (PLAN/CONFIRM/BT)
- severity (INFO/WARN/BLOCK)
- short_id
- description_id
- description_en
- created_at

## B) Param sets

### 3) watchlist_param_sets
Menyimpan paramset semua policy.
- param_set_id (PK)
- policy_code
- policy_version
- status (DRAFT/ACTIVE/DEPRECATED)
- params_json (LONGTEXT)
- created_at, updated_at

Constraint:
- per `policy_code`, maksimum 1 row `status='ACTIVE'` pada waktu tertentu (enforced by procedure + lock).

## C) PLAN snapshot

### 4) watchlist_plan_runs
Header run PLAN.
- plan_run_id (PK)
- policy_code, policy_version
- asof_eod_date
- plan_trade_date
- param_set_id (FK)
- run_status (OK/NO_TRADE/FAILED)
- data_batch_hash (CHAR(64))
- hash_count (INT)
- missing_required_count (INT)
- processed_count (INT)
- eligible_count (INT)
- run_metrics_json (LONGTEXT; JSON)
- supersedes_plan_run_id (nullable; self-reference logical, optional)
- is_active (Yes/No)
- fail_code (nullable; FK to `watchlist_fail_codes.fail_code`)
- created_at

Constraints / lifecycle (LOCKED):
- Header row tidak boleh DELETE.
- UPDATE hanya boleh untuk deaktivasi supersede satu arah pada `is_active` (`Yes -> No`).
- Semua kolom selain `is_active` immutable setelah insert.

Index minimal:
- `(policy_code, plan_trade_date, is_active)`
- `(policy_code, asof_eod_date)`

### 5) watchlist_plan_items
Detail item per ticker untuk PLAN (audit row untuk semua ticker di universe).
- plan_item_id (PK)
- plan_run_id (FK)
- policy_code (redundant for query convenience)
- trade_date (`plan_trade_date`)
- ticker_id
- ticker_code (cache optional)
- group_semantic (TOP_PICKS/SECONDARY/WATCH_ONLY/AVOID)
- display_bucket (SHOW/HIDE)
- selection_reason_code
- score_total (DECIMAL)
- scores_json (LONGTEXT)
- inputs_json (LONGTEXT)
- plan_levels_json (LONGTEXT)
- reason_codes_json (LONGTEXT)
- created_at

Constraints / lifecycle (LOCKED):
- append-only; UPDATE/DELETE dilarang.

Index minimal:
- `(plan_run_id, ticker_id)`
- `(policy_code, trade_date, display_bucket)`

## D) CONFIRM overlay

### 6) watchlist_confirm_checks
Header confirm.
- confirm_check_id (PK)
- plan_run_id (FK)
- policy_code, policy_version
- checked_at (DATETIME)
- snapshot_age_sec (INT)
- run_status (OK/FAILED)
- fail_code (nullable; FK to `watchlist_fail_codes.fail_code`)
- run_metrics_json (LONGTEXT) — metrik run-level (LOCKED keys per policy)
- created_at

Lifecycle:
- append-only; UPDATE/DELETE dilarang.

### 7) watchlist_confirm_items
Detail confirm untuk ticker yang dievaluasi.
- confirm_item_id (PK)
- confirm_check_id (FK)
- ticker_id
- label (CONFIRMED/NEUTRAL/CAUTION/DELAY)
- runtime_json (LONGTEXT) — payload runtime policy-scoped.
  - Daftar key yang diizinkan tidak dimiliki oleh schema global ini.
  - Allowed keys, semantics, dan strictness untuk payload ini harus mengikuti dokumen owner strategy yang relevan.
  - Untuk Weekly Swing aktif saat ini, lihat `../policies/weekly_swing/03_WS_DATA_MODEL_MARIADB.md` dan `../policies/weekly_swing/10_WS_CONFIRM_OVERLAY.md`.
- reason_codes_json (LONGTEXT)
- created_at

Lifecycle:
- append-only; UPDATE/DELETE dilarang.

Index minimal:
- `(confirm_check_id, ticker_id)`

## DDL
DDL lengkap tabel global watchlist ada di: [`05_DB_DDL_MARIADB.sql`](05_DB_DDL_MARIADB.sql).

## E) Intraday snapshot (manual input for CONFIRM)

### 8) watchlist_confirm_snapshots
Header snapshot intraday untuk CONFIRM.
- snapshot_id (PK)
- policy_code
- trade_date
- captured_at
- inserted_at
- source
- note
- snapshot_hash
- created_at

Lifecycle:
- append-only; UPDATE/DELETE dilarang.

### 9) watchlist_confirm_snapshot_items
Detail per ticker untuk snapshot.
- snapshot_item_id (PK)
- snapshot_id (FK)
- ticker_code
- ticker_id (nullable)
- last_price
- chg_pct
- volume_shares
- turnover_idr
- item_hash
- created_at

Lifecycle:
- append-only; UPDATE/DELETE dilarang.

Lihat juga: `../policies/weekly_swing/11_WS_INTRADAY_SNAPSHOT_TABLES.md`.

## Outputs
- Daftar tabel global dan kolom minimum yang stabil lintas policy.

## Failure modes
- Menambah kolom per policy tanpa payload JSON akan membuat schema cepat drift saat policy bertambah.
- Menyatakan FK di dokumen tetapi tidak ada di DDL dianggap parity failure.

## Next
03_DB_INDEXES_AND_CONSTRAINTS.md
