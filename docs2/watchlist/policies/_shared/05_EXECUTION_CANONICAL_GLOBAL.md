# 05 — Execution Canonical (Global)

## Purpose
Mengunci alur eksekusi global semua policy watchlist agar PLAN dan CONFIRM tidak bercampur.

## Prerequisites
- [`01_POLICY_FRAMEWORK_OVERVIEW.md`](01_POLICY_FRAMEWORK_OVERVIEW.md)
- [`04_CONTRACT_TESTS_GLOBAL.md`](04_CONTRACT_TESTS_GLOBAL.md)

## Aturan eksekusi global (LOCKED)
- **PLAN** dibentuk dari data EOD hari ini untuk rekomendasi next trading day.
- PLAN disimpan sebagai snapshot yang bisa diaudit dan **tidak berubah** sepanjang hari.
- **CONFIRM** adalah overlay runtime/intraday untuk membantu keyakinan eksekusi.
- CONFIRM **tidak boleh mengubah** hasil PLAN.
- Jika sebuah policy tidak punya CONFIRM, policy itu tetap wajib jelas mendefinisikan bahwa output runtime-nya tidak menulis ulang PLAN.

## Implikasi storage (LOCKED)
- Write scope PLAN dan CONFIRM harus terpisah.
- Header/item PLAN yang sudah final tidak boleh di-update diam-diam oleh runner CONFIRM.
- Audit replay harus bisa menunjukkan snapshot PLAN asli dan hasil CONFIRM tanpa mencampur payload.

## Source of truth database watchlist
Database watchlist global ada di folder [`../../db/`](../../db/README.md):
- `../../db/01_DB_OVERVIEW.md`
- `../../db/02_DB_SCHEMA_MARIADB.md`
- `../../db/03_DB_INDEXES_AND_CONSTRAINTS.md`
- `../../db/04_DB_SEED_GLOBAL.sql`
- `../../db/05_DB_DDL_MARIADB.sql`

## Policy-specific detail
Detail implementasi per policy tetap di folder policy masing-masing.
Contoh Weekly Swing:
- `../weekly_swing/02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`
