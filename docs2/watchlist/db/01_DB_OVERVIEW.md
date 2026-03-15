# 01 — Database Overview — Watchlist (Global)

## Purpose

Menetapkan prinsip database Watchlist yang global (lintas policy), sehingga penambahan policy baru tidak memaksa perubahan struktur dokumen atau pemindahan file.

## What This Document Is Not

Dokumen ini bukan owner untuk:
- business-rule semantics per strategy,
- formula scoring,
- selection behavior strategy,
- atau runtime label strategy.

Kontrak perilaku tetap dimiliki oleh dokumen policy owner; dokumen ini hanya menetapkan prinsip database global.

## Prerequisites

- `../policy.md`

## Inputs

- kebutuhan snapshot PLAN/CONFIRM yang audit-able
- kebutuhan paramset yang tervalidasi
- kebutuhan dictionary (fail codes, reason codes)

## Process

### Prinsip desain
1. Database watchlist adalah platform lintas policy.
2. Semua row snapshot mengikat:
   - `policy_code`
   - `policy_version`
   - `param_set_id`
   - `data_batch_hash`
3. Policy-specific details disimpan sebagai payload / field strategy-level yang memang kontraktual, bukan dengan memaksa perubahan DDL global untuk setiap policy kecil.
4. Tabel dictionary bersifat global dan dibedakan dengan `policy_code` jika perlu.

### Scope tabel global
- Param sets: satu tempat untuk semua policy.
- Snapshot runs/items: satu tempat untuk semua policy.
- Confirm runs/items: satu tempat untuk semua policy.
- Dictionary: fail codes global, reason codes global dengan `policy_code` bila perlu.

### Authoritative Source Map
- shape dan semantics tabel global: `02_DB_SCHEMA_MARIADB.md`
- index dan constraints global: `03_DB_INDEXES_AND_CONSTRAINTS.md`
- executable DDL: `05_DB_DDL_MARIADB.sql`
- seed global: `04_DB_SEED_GLOBAL.sql`

## Outputs

- kontrak scope database watchlist global
- peta ownership antara dokumen normatif DB dan artefak SQL

## Failure Modes

- menaruh tabel global ke folder policy sehingga drift saat policy bertambah
- membaca SQL artifact sebagai owner semantik business rule
- menambah kolom global hanya untuk menutup kebutuhan satu strategy yang seharusnya hidup di layer strategy

## Next

`02_DB_SCHEMA_MARIADB.md`

Jika schema sudah terbuat dari versi lama, gunakan artefak executable: [`05_DB_DDL_MARIADB.sql`](05_DB_DDL_MARIADB.sql). Jika ada konflik semantik, dokumen normatif DB selalu menang dan artefak SQL harus disesuaikan.

## Global vs Strategy-Specific Tables

### Global watchlist tables
Contoh area global:
- paramset storage / metadata,
- run header / run item storage,
- dictionary tables untuk fail codes atau reason codes lintas policy,
- dan tabel pendukung audit yang tidak eksklusif untuk satu strategy.

### Strategy-specific tables
Tabel strategy-specific hidup pada folder strategy owner jika semantics-nya hanya berlaku untuk satu strategy, misalnya snapshot atau artifact backtest khusus Weekly Swing.

## Fast Owner Rule

- Ingin tahu **apa arti kolom / row secara bisnis** → lihat dokumen policy owner.
- Ingin tahu **bagaimana tabel direalisasikan** → lihat DDL / migrations.
- Ingin tahu **siapa menang saat konflik** → schema parity rules dan dokumen normatif markdown menang atas artifact executable.

## Suggested Reading for DB Engineers

1. `README.md`
2. dokumen ini
3. `02_DB_SCHEMA_MARIADB.md`
4. `03_DB_INDEXES_AND_CONSTRAINTS.md`
5. dokumen strategy-level pada folder strategy yang memakai tabel atau artifact terkait

