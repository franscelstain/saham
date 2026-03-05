# Database Watchlist (Global) — Index

> **Status:** LOCKED (Normative)
> **Doc Role:** Watchlist DB contract index


## Purpose
Entry point dokumentasi database aplikasi watchlist.

## Scope
Menjelaskan urutan baca schema, index, seed, dan DDL watchlist app.

## Inputs
- Pembaca yang mengerjakan schema, migration, audit DB, atau persistence layer.

## Outputs
- Peta baca database watchlist aplikasi.

Folder ini berisi schema database **aplikasi watchlist**, bukan schema market data sumber.

## Start here
Baca berurutan:
1. [`01_DB_OVERVIEW.md`](01_DB_OVERVIEW.md)
2. [`02_DB_SCHEMA_MARIADB.md`](02_DB_SCHEMA_MARIADB.md)
3. [`03_DB_INDEXES_AND_CONSTRAINTS.md`](03_DB_INDEXES_AND_CONSTRAINTS.md)
4. [`04_DB_SEED_GLOBAL.sql`](04_DB_SEED_GLOBAL.sql)
5. [`05_DB_DDL_MARIADB.sql`](05_DB_DDL_MARIADB.sql)

## Kapan pakai file mana
- `01..03` — source of truth kontrak schema
- [`04_DB_SEED_GLOBAL.sql`](04_DB_SEED_GLOBAL.sql) — seed dictionary global
- [`05_DB_DDL_MARIADB.sql`](05_DB_DDL_MARIADB.sql) — bootstrap schema baru
- [`MIGRATIONS.sql`](MIGRATIONS.sql) — patch schema lama yang sudah telanjur dibuat dari versi DDL sebelumnya

## Rule
Jika ada konflik antara DDL/migration dan dokumen kontrak, dokumen kontrak menang lalu SQL harus diperbaiki.
