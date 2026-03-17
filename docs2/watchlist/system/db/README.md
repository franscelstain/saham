# Watchlist DB — Index

## Purpose

Dokumen ini adalah entry point untuk artefak database watchlist global.

## Hard Warning

Folder `db/` bukan owner business-rule semantics strategy. Dokumen dan artefak di sini mengatur database global watchlist; perilaku strategy tetap dimiliki oleh policy strategy masing-masing.

## Reading Order

1. `01_DB_OVERVIEW.md`
2. `02_DB_SCHEMA_MARIADB.md`
3. `03_DB_INDEXES_AND_CONSTRAINTS.md`

Artefak executable:
- `04_DB_SEED_GLOBAL.sql`
- `05_DB_DDL_MARIADB.sql`
- `MIGRATIONS.sql`

## Final Rule

Jika ada konflik antara artefak executable dan dokumen normatif DB, dokumen normatif DB selalu menang.

## What This Folder Must Not Do

Folder `db/` tidak boleh menjadi tempat lahirnya business rule baru yang tidak hidup dulu pada dokumen normatif markdown. SQL artifact hanya merealisasikan rule yang sudah dikunci oleh dokumen owner.

