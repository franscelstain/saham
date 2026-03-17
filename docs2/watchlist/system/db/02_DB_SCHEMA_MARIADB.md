# 02 — Database Schema — MariaDB (Global)

## Purpose
Menjadi owner normatif untuk shape tabel global watchlist lintas policy.

## What This Document Is Not
Dokumen ini bukan owner untuk:
- formula score strategy,
- logic ranking strategy,
- label semantics strategy-specific,
- atau payload rule strategy-specific di luar kolom global yang dikontrakkan.

## Prerequisites
- [`01_DB_OVERVIEW.md`](01_DB_OVERVIEW.md)
- [`../policies/_shared/06_SCHEMA_PARITY_RULES.md`](../policies/_shared/06_SCHEMA_PARITY_RULES.md)

## Inputs
- kebutuhan global watchlist:
  - paramset persistence,
  - PLAN persistence,
  - CONFIRM persistence,
  - dictionary persistence,
  - snapshot manual untuk runtime overlay.
- extension resmi policy bila policy tersebut mengaktifkan persistence tambahan yang owner-nya berada di folder policy.

## A) Global dictionary

### 1) watchlist_fail_codes
Kode kegagalan run-level global.
- fail_code (PK)
- scope_layer (`PLAN` / `RECOMMENDATION` / `CONFIRM` / `SHARED`)
- severity (`INFO` / `WARN` / `ERROR`)
- description_id
- created_at

Catatan:
- `SHARED` dipakai hanya untuk fail code global yang sah berlaku lintas lebih dari satu layer runtime watchlist.
- Layer `RECOMMENDATION` dipakai hanya bila policy memang mempunyai layer runtime tersebut.
- Dictionary fail code global ini adalah run-level/runtime-level; kebutuhan validator detail boleh memakai namespace fail code yang sama selama tetap tunduk pada dictionary resmi yang di-seed.

### 2) watchlist_reason_codes
Dictionary reason codes lintas policy.
- policy_code + reason_code (PK komposit pada artifact SQL aktif)
- scope_layer (`PLAN` / `RECOMMENDATION` / `CONFIRM` / `BT`)
- severity (`INFO` / `WARN` / `BLOCK`)
- short_id
- description_id
- description_en
- created_at

Catatan:
- `BT` diizinkan untuk reason code evaluasi/backtest policy-scoped bila policy memang mempunyai discipline evaluasi resmi.
- Layer runtime watchlist tetap memakai `PLAN` / `RECOMMENDATION` / `CONFIRM`; `BT` bukan runtime layer watchlist, tetapi tetap boleh hidup di dictionary reason code policy-scoped agar tidak perlu membuat tabel dictionary terpisah.

## B) Paramset

### 3) watchlist_param_sets
Paramset resmi untuk semua policy.
- param_set_id (PK)
- policy_code
- policy_version
- schema_version
- hash_contract (LONGTEXT)
- provenance_json (LONGTEXT)
- status (DRAFT/ACTIVE/DEPRECATED)
- params_json (LONGTEXT)
- created_at, updated_at

Constraint:
- per `policy_code`, maksimum 1 row `status='ACTIVE'` pada waktu tertentu (enforced by procedure + lock).

Catatan parity (LOCKED):
- `schema_version` adalah kolom identitas schema paramset yang wajib tersimpan eksplisit, bukan hanya diasumsikan ada di payload JSON.
- `hash_contract` menyimpan canonical serialized hash-contract artifact yang diambil dari payload paramset tervalidasi.
- `provenance_json` menyimpan projection canonical provenance untuk audit/query convenience; kolom ini bukan izin untuk memperkenalkan top-level `provenance` map baru di kontrak JSON.

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
  - Untuk Weekly Swing aktif saat ini, lihat [`../policies/weekly_swing/03_WS_DATA_MODEL_MARIADB.md`](../policies/weekly_swing/03_WS_DATA_MODEL_MARIADB.md) dan [`../policies/weekly_swing/10_WS_CONFIRM_OVERLAY.md`](../policies/weekly_swing/10_WS_CONFIRM_OVERLAY.md).
- reason_codes_json (LONGTEXT)
- created_at

Lifecycle:
- append-only; UPDATE/DELETE dilarang.

Index minimal:
- `(confirm_check_id, ticker_id)`

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

Lihat juga: [`../policies/weekly_swing/11_WS_INTRADAY_SNAPSHOT_TABLES.md`](../policies/weekly_swing/11_WS_INTRADAY_SNAPSHOT_TABLES.md).

## F) Policy-owned official persistence extension

Schema global ini tidak memaksa semua layer runtime policy-specific menjadi tabel global.
Jika sebuah policy secara resmi memiliki persistence layer tambahan, owner normatif tabel tersebut boleh berada di folder policy selama:
- extension itu dinyatakan resmi,
- tunduk pada parity discipline shared/global,
- dan tidak menyamar sebagai field liar di luar kontrak.

Contoh aktif:
- Weekly Swing recommendation persistence: [`../policies/weekly_swing/db/RECOMMENDATION_RUNTIME_SCHEMA.md`](../policies/weekly_swing/db/RECOMMENDATION_RUNTIME_SCHEMA.md)

## DDL
DDL lengkap tabel global watchlist ada di: [`05_DB_DDL_MARIADB.sql`](05_DB_DDL_MARIADB.sql).

## Outputs
- Daftar tabel global dan kolom minimum yang stabil lintas policy.
- Guard bahwa extension persistence policy-specific tetap bisa resmi tanpa memaksa global schema drift.

## Failure modes
- Menambah kolom per policy tanpa payload JSON akan membuat schema cepat drift saat policy bertambah.
- Menyatakan FK di dokumen tetapi tidak ada di DDL dianggap parity failure.
- Menganggap absence tabel global untuk layer policy-specific sebagai alasan untuk melewati parity discipline resmi.

## Next
03_DB_INDEXES_AND_CONSTRAINTS.md
