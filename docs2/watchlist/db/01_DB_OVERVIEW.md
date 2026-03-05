# 01 — Database Overview — Watchlist (Global)

## Purpose
Menetapkan prinsip database Watchlist yang **global** (lintas policy), sehingga penambahan policy baru tidak memaksa perubahan struktur dokumen atau pemindahan file.

## Prerequisites
- `../policy.md`

## Inputs
- Kebutuhan snapshot PLAN/CONFIRM yang audit-able
- Kebutuhan paramset yang tervalidasi
- Kebutuhan dictionary (fail codes, reason codes)

## Process
### Prinsip desain
1) Database watchlist adalah platform lintas policy.
2) Semua row data snapshot mengikat:
   - `policy_code`
   - `policy_version`
   - `param_set_id`
   - `data_batch_hash`
3) Policy-specific details disimpan sebagai payload (JSON) pada item-level, bukan menambah kolom/DDL per policy.
4) Tabel dictionary bersifat global dan dibedakan dengan `policy_code` jika perlu.

### Scope tabel global
- Param sets: satu tempat untuk semua policy.
- Snapshot runs/items: satu tempat untuk semua policy.
- Confirm runs/items: satu tempat untuk semua policy.
- Dictionary: fail codes (global), reason codes (global dengan policy_code).

## Outputs
- Kontrak scope database watchlist global.

## Failure modes
- Menaruh tabel global ke folder policy (membuat drift saat policy bertambah).

## Next
02_DB_SCHEMA_MARIADB.md

Jika schema sudah terbuat dari versi lama, gunakan artefak: [`05_DB_DDL_MARIADB.sql`](05_DB_DDL_MARIADB.sql).
