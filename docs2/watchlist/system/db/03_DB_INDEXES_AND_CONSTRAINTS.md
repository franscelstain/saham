# 03 — Indexes, Constraints, Invariants — Watchlist (Global)

## Purpose
Mengunci constraint dan invariants agar snapshot deterministik, dapat diaudit, dan konsisten dengan DDL MariaDB.

## Prerequisites
02_DB_SCHEMA_MARIADB.md

## Inputs
- Workload query watchlist global untuk `PLAN`, layer turunan resmi dari `PLAN` bila dipersist oleh policy, dan `CONFIRM`
- Rule supersede `PLAN` aktif
- Guard separation antar layer runtime watchlist

## Invariants (wajib)
1) PLAN item rows append-only:
   - `watchlist_plan_items` tidak boleh UPDATE/DELETE.
   - Enforcement: trigger `trg_wpi_*` (lihat DDL).

2) PLAN header rows hampir immutable:
   - `watchlist_plan_runs` tidak boleh DELETE.
   - UPDATE **dilarang** kecuali **deaktivasi satu arah** pada kolom `is_active` (`Yes -> No`) untuk row lama yang tersupersede.
   - Semua kolom selain `is_active` wajib immutable setelah insert.
   - Enforcement: trigger `trg_wpr_guard_update` + transactional supersede.

3) Satu active plan per `policy_code + plan_trade_date`:
   - Untuk kombinasi yang sama hanya boleh ada satu `watchlist_plan_runs.is_active='Yes'`.
   - Supersede dilakukan dengan transaksi terkontrol: insert row baru aktif, lalu deaktifkan row aktif lama secara atomik.

4) Runtime layer isolation:
   - `CONFIRM` tables tidak boleh mengubah tabel `PLAN`.
   - Jika policy memiliki persistence resmi untuk layer turunan dari `PLAN`, layer itu juga tidak boleh menulis ulang `PLAN`.
   - Foreign key / reference antar layer runtime adalah relationship baca/audit, bukan izin mutasi.

5) Paramset lifecycle:
   - Satu ACTIVE per `policy_code`.
   - Promotion atomic dengan lock aplikasi/DB.

6) Failure dictionary integrity:
   - `watchlist_plan_runs.fail_code` dan `watchlist_confirm_checks.fail_code` wajib mereferensikan `watchlist_fail_codes.fail_code`.
   - Unknown fail code tidak boleh ditulis ke run tables.

## Index recommendations (minimum viable)
- `watchlist_plan_runs`
  - `IDX_plan_active (policy_code, plan_trade_date, is_active)`
  - `IDX_plan_asof (policy_code, asof_eod_date)`
- `watchlist_plan_items`
  - `IDX_items_run_ticker (plan_run_id, ticker_id)`
  - `IDX_items_policy_date_bucket (policy_code, trade_date, display_bucket)`
- `watchlist_confirm_checks`
  - `IDX_confirm_plan_run (plan_run_id, checked_at)`
- `watchlist_confirm_items`
  - `IDX_confirm_items_check_ticker (confirm_check_id, ticker_id)`
- `watchlist_param_sets`
  - `IDX_param_policy_status (policy_code, status, updated_at)`

## Intraday snapshot (CONFIRM manual)
- `watchlist_confirm_snapshots.idx_snap_policy_trade_captured (policy_code, trade_date, captured_at)`
- `watchlist_confirm_snapshots.idx_snap_trade_inserted (trade_date, inserted_at)`
- `watchlist_confirm_snapshot_items.uq_snap_ticker (snapshot_id, ticker_code)`
- `watchlist_confirm_snapshot_items.idx_item_snap (snapshot_id)`
- `watchlist_confirm_snapshot_items.idx_item_ticker (ticker_code)`
- FK: `watchlist_confirm_snapshot_items.snapshot_id -> watchlist_confirm_snapshots.snapshot_id`

## Outputs
- Invariants yang menjadi acuan implementasi DDL, trigger, dan contract tests.

## Failure modes
- Tanpa index, query dashboard/audit untuk `PLAN`, layer turunan resmi, atau `CONFIRM` akan lambat.
- Tanpa guard update yang ketat, supersede dapat merusak histori `PLAN`.
- Tanpa isolasi layer yang jelas, persistence layer tambahan policy dapat salah dibaca sebagai izin mutasi ke `PLAN`.
- Tanpa FK fail_code, abort reason bisa drift dari dictionary resmi.

## Next
04_DB_SEED_GLOBAL.sql
