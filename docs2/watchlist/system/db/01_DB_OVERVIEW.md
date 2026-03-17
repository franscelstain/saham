# 01 — Database Overview — Watchlist (Global)

## Purpose

Menetapkan prinsip database Watchlist yang global (lintas policy), sehingga penambahan policy baru tidak memaksa perubahan struktur dokumen atau pemindahan file.

## What This Document Is Not

Dokumen ini bukan owner untuk:
- business-rule semantics per strategy,
- formula scoring,
- selection behavior strategy,
- runtime label strategy,
- atau acceptance strategy.

Kontrak perilaku tetap dimiliki oleh dokumen policy owner; dokumen ini hanya menetapkan prinsip database global.

## Prerequisites

- [`../policy.md`](../policy.md)
- [`../policies/_shared/05_EXECUTION_CANONICAL_GLOBAL.md`](../policies/_shared/05_EXECUTION_CANONICAL_GLOBAL.md)

## Boundary Guard

Dokumen DB global ini hanya mengatur persistence boundary watchlist yang audit-able.
Dokumen ini **bukan** owner untuk execution system, portfolio state, atau market-data internals.
Jika suatu policy memiliki persistence strategy-specific resmi, ownership semantics tetap berada pada dokumen policy owner; DB global hanya menjaga separation, parity, dan source map.

## Inputs

- kebutuhan snapshot/watchlist runtime artifacts yang audit-able
- kebutuhan paramset yang tervalidasi
- kebutuhan dictionary (fail codes, reason codes)
- kebutuhan persistence layer runtime tertentu bila policy secara resmi mengaktifkannya

## Process

### Prinsip desain
1. Database watchlist adalah platform lintas policy.
2. Semua row snapshot/runtime mengikat:
   - `policy_code`
   - `policy_version`
   - `param_set_id`
   - `data_batch_hash` bila layer itu memang dikontrakkan memakai hash tersebut
3. Policy-specific details disimpan sebagai payload / field strategy-level yang memang kontraktual, bukan dengan memaksa perubahan DDL global untuk setiap policy kecil.
4. Tabel dictionary bersifat global dan dibedakan dengan `policy_code` bila perlu.
5. Shared DB layer mengakui bahwa policy aktif dapat memiliki lebih dari satu layer runtime watchlist, misalnya `PLAN`, layer turunan dari PLAN seperti `RECOMMENDATION`, dan `CONFIRM`.
6. Pengakuan adanya layer runtime tambahan **tidak** memindahkan ownership semantics ke DB global; semantics tetap dimiliki policy owner.

### Scope tabel global
- Param sets: satu tempat untuk semua policy.
- PLAN runs/items: satu tempat untuk semua policy yang memakai PLAN.
- CONFIRM checks/items: satu tempat untuk semua policy yang memakai CONFIRM.
- Dictionary: fail codes global, reason codes global dengan `policy_code` bila perlu.

### Scope extension rule
- Jika policy memiliki layer runtime tambahan yang resmi dipersist, policy boleh mendefinisikan persistence owner di folder policy-nya sendiri.
- Persistence strategy-specific seperti itu tetap harus tunduk pada parity discipline shared/global.
- Weekly Swing aktif saat ini memakai contoh resmi itu untuk `RECOMMENDATION` melalui dokumen owner strategy di folder policy.

### Authoritative Source Map
- shape dan semantics tabel global: `02_DB_SCHEMA_MARIADB.md`
- index dan constraints global: `03_DB_INDEXES_AND_CONSTRAINTS.md`
- executable DDL: `05_DB_DDL_MARIADB.sql`
- seed global: `04_DB_SEED_GLOBAL.sql`
- parity discipline: `../policies/_shared/06_SCHEMA_PARITY_RULES.md`

## Outputs

- kontrak scope database watchlist global
- peta ownership antara dokumen normatif DB, artefak SQL, dan extension policy-specific resmi

## Failure Modes

- menaruh tabel global ke folder policy sehingga drift saat policy bertambah
- membaca SQL artifact sebagai owner semantik business rule
- menambah kolom global hanya untuk menampung kebutuhan strategy-specific kecil
- mengakui layer runtime policy pada dokumen policy, tetapi membiarkan persistence resminya tidak masuk parity discipline

## Related policy-owned official extension
Contoh extension resmi yang saat ini aktif:
- Weekly Swing recommendation persistence: [`../policies/weekly_swing/db/RECOMMENDATION_RUNTIME_SCHEMA.md`](../policies/weekly_swing/db/RECOMMENDATION_RUNTIME_SCHEMA.md)

## Next
- `02_DB_SCHEMA_MARIADB.md`
