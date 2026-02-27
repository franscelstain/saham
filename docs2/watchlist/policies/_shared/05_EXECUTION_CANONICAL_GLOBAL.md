# 05 — Execution Canonical (Global)

Aturan eksekusi global:
- **PLAN**: dibentuk dari data EOD hari ini untuk rekomendasi next trading day. PLAN disimpan sebagai snapshot dan **tidak berubah** sepanjang hari.
- **CONFIRM**: overlay runtime (jam saat ini) untuk meningkatkan keyakinan, dan **tidak boleh mengubah** hasil PLAN.

Database watchlist global ada di folder `../../db/` (urut):
- `../../db/01_DB_OVERVIEW.md`
- `../../db/02_DB_SCHEMA_MARIADB.md`
- `../../db/03_DB_INDEXES_AND_CONSTRAINTS.md`
- `../../db/04_DB_SEED_GLOBAL.sql`
- `../../db/05_DB_DDL_MARIADB.sql`
