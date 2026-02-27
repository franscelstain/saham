# 06 — Schema Parity Rules (Anti-Drift) (LOCKED)

Dokumen ini mengunci aturan **parity** antara:
1) dokumen schema (`../../db/02_DB_SCHEMA_MARIADB.md`),
2) DDL (`../../db/05_DB_DDL_MARIADB.sql`),
3) daftar kolom Repository (anti-drift test di code).

## 1) Contract tables (wajib parity)
Tabel berikut dianggap **contract tables** (perubahan adalah breaking change):
- `watchlist_param_sets`
- `watchlist_plan_runs`
- `watchlist_plan_items`
- `watchlist_confirm_runs`
- `watchlist_confirm_items`
- Dictionary tables (global):
  - `watchlist_reason_codes`
  - `watchlist_fail_codes`

## 2) Model DDL vs Seed (LOCKED)
Aturan pemisahan:
- **DDL** (`../../db/05_DB_DDL_MARIADB.sql`) hanya berisi `CREATE TABLE`, `ALTER TABLE`, `CREATE INDEX` (definisi struktur).
- **Seed** (`../../db/04_DB_SEED_GLOBAL.sql` dan seed policy di folder policy) hanya berisi `INSERT` untuk mengisi dictionary / data awal.
- Dilarang menaruh definisi kolom baru di seed tanpa ada di DDL.
- Dilarang menaruh data seed di DDL.

## 3) Larangan mismatch (LOCKED)
- Dilarang: “kolom disebut di schema doc tapi tidak ada di DDL”.
- Dilarang: “kolom ada di DDL tapi tidak ada di schema doc”, kecuali kolom tersebut eksplisit ditandai `internal_only` dan tidak menjadi contract.
- Dilarang: Repository::COLUMNS berbeda dari schema doc untuk contract tables.

Jika terjadi mismatch:
- perbaiki **dokumen schema** atau **DDL** terlebih dahulu,
- lalu update Repository::COLUMNS + contract tests agar semuanya konsisten.

## 4) Repository columns contract (LOCKED)
Untuk setiap **contract table**, harus ada definisi kolom sumber kebenaran di code (mis. konstanta `COLUMNS` pada `*Repository`).
Aturan:
- Nama class dan lokasi file harus konsisten (konvensi proyek), dan test anti-drift membandingkan `Repository::COLUMNS` vs schema doc.
- Setiap penambahan/penghapusan/rename kolom contract table adalah **breaking change** dan wajib update: schema doc, DDL, Repository::COLUMNS, dan contract tests.
