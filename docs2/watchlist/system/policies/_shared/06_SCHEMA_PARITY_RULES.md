# 06 — Schema Parity Rules (Anti-Drift) (LOCKED)

## Purpose
Mengunci aturan parity antara:
1. schema documentation,
2. SQL DDL,
3. dan representasi kolom/table contract di codebase.

Tujuan dokumen ini adalah mencegah drift antara apa yang ditulis di dokumen, apa yang dibuat di database, dan apa yang diasumsikan oleh repository/service/test.

## Scope
Dokumen ini berlaku untuk seluruh contract tables watchlist yang dipakai oleh runtime, persistence, contract tests, dan audit trail.

Dokumen ini tidak mengatur detail business logic score/selection.
Dokumen ini hanya mengatur **parity struktur** dan **breaking-change discipline**.

## Inputs
- [`../../db/02_DB_SCHEMA_MARIADB.md`](../../db/02_DB_SCHEMA_MARIADB.md)
- [`../../db/05_DB_DDL_MARIADB.sql`](../../db/05_DB_DDL_MARIADB.sql)
- definisi kolom/table di codebase (mis. `Repository::COLUMNS`, DTO contract, anti-drift tests)
- schema owner strategy untuk persistence extension resmi bila ada

## Outputs
- daftar contract tables,
- aturan parity schema-vs-DDL-vs-code,
- aturan breaking change,
- aturan DDL-vs-seed separation.

## 1) Contract Tables (LOCKED)
Tabel berikut dianggap **contract tables**.
Perubahan pada nama tabel, nama kolom, nullability, tipe, atau semantics kolom pada tabel ini dianggap **breaking change** kecuali dinyatakan sebaliknya secara eksplisit.

### A) Core runtime tables
- `watchlist_param_sets`
- `watchlist_plan_runs`
- `watchlist_plan_items`
- `watchlist_confirm_checks`
- `watchlist_confirm_items`

### B) Global dictionary tables
- `watchlist_reason_codes`
- `watchlist_fail_codes`

### C) Policy-specific official persistence extensions
Jika sebuah policy mendefinisikan tabel persistence resmi pada manifest policy atau schema owner policy-nya, tabel tersebut juga dianggap contract table untuk scope policy tersebut.
Contoh Weekly Swing dirujuk dari:
- [`../weekly_swing/db/RECOMMENDATION_RUNTIME_SCHEMA.md`](../weekly_swing/db/RECOMMENDATION_RUNTIME_SCHEMA.md)

### D) Policy-specific official backtest tables
Jika sebuah policy mendefinisikan tabel backtest resmi pada manifest policy-nya, tabel tersebut juga dianggap contract table untuk scope policy tersebut.
Contoh Weekly Swing dirujuk dari:
- [`../weekly_swing/18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](../weekly_swing/18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)

## 2) Canonical Sources (LOCKED)
Untuk contract tables, source of truth dibagi sebagai berikut:

### A) Human-readable schema
- [`../../db/02_DB_SCHEMA_MARIADB.md`](../../db/02_DB_SCHEMA_MARIADB.md)
- atau schema owner policy bila tabel itu adalah official persistence extension milik policy tertentu.

### B) Executable DDL
- [`../../db/05_DB_DDL_MARIADB.sql`](../../db/05_DB_DDL_MARIADB.sql)
- atau DDL owner policy bila tabel itu adalah official persistence extension milik policy tertentu.

### C) Code-level contract representation
- konstanta daftar kolom di repository / data access layer,
- DTO contract,
- anti-drift tests yang memverifikasi daftar kolom.

Aturan:
- schema doc menjelaskan kontrak,
- DDL membentuk struktur aktual,
- code-level contract memastikan implementasi tidak diam-diam memakai kolom liar atau kolom yang belum dikontrakkan.

## 3) DDL vs Seed Separation (LOCKED)
Pemisahan berikut wajib dijaga:

### DDL
File DDL hanya boleh berisi hal-hal struktural seperti:
- `CREATE TABLE`
- `ALTER TABLE`
- `CREATE INDEX`
- trigger / constraint / foreign key / check constraint

### Seed
File seed hanya boleh berisi data awal seperti:
- dictionary rows,
- reason codes,
- fail codes,
- policy seed resmi yang memang diizinkan.

### Forbidden
- Dilarang menaruh kolom baru di seed tanpa ada di DDL.
- Dilarang menaruh dictionary seed di file DDL.
- Dilarang memakai seed untuk “diam-diam” memperkenalkan contract baru.

## 4) Parity Rules (LOCKED)
Untuk setiap contract table, hal berikut wajib identik secara meaning antara schema doc, DDL, dan code:
- nama tabel,
- nama kolom contract,
- tipe data logis,
- nullable vs non-nullable,
- primary/unique identity yang relevan,
- semantics inti yang memengaruhi pembacaan/penulisan data.

### Forbidden mismatches
1. Kolom disebut di schema doc tetapi tidak ada di DDL.
2. Kolom ada di DDL tetapi tidak ada di schema doc, kecuali benar-benar `internal_only` dan ditandai demikian secara eksplisit.
3. Repository/DTO/test memakai kolom yang tidak dikontrakkan.
4. Nama kolom sama tetapi arti/semantics bergeser tanpa update dokumen.

Jika salah satu kondisi di atas terjadi, statusnya adalah:
- `SCHEMA_PARITY_FAILURE`

## 5) Internal-only Fields
Kolom boleh tidak muncul sebagai contract field hanya jika memenuhi semua syarat berikut:
- kolom itu tidak memengaruhi API contract atau audit contract,
- kolom itu tidak dibaca sebagai bagian dari runtime canonical logic,
- kolom itu tidak digunakan untuk reason/fail/ranking/hash semantics,
- dan kolom itu ditandai jelas sebagai `internal_only` pada schema doc.

Jika syarat ini tidak terpenuhi, kolom tersebut harus diperlakukan sebagai contract field.

## 6) Repository Columns Contract (LOCKED)
Untuk setiap contract table, codebase wajib punya representasi kolom resmi, misalnya:
- `Repository::COLUMNS`
- atau bentuk lain yang setara secara auditability.

Aturan:
- anti-drift tests wajib membandingkan representasi code-level ini terhadap schema contract,
- daftar kolom code-level tidak boleh lebih longgar dari kontrak,
- penambahan, penghapusan, rename, atau perubahan semantics wajib dilakukan serentak di dokumen, DDL, code, dan test.

## 7) Breaking Change Workflow (LOCKED)
Setiap perubahan pada contract table wajib mengikuti urutan berikut:
1. update schema documentation,
2. update DDL,
3. update code-level contract representation,
4. update seed bila perlu,
5. update contract tests / anti-drift tests,
6. update policy docs yang terdampak.

Perubahan dianggap belum sah jika salah satu langkah di atas tertinggal.

Untuk official persistence extension milik policy tertentu, urutan yang sama tetap berlaku, hanya saja owner schema/DDL dapat berada di folder policy.

## 8) Policy-level Extension Rule
Policy boleh punya tabel tambahan sendiri, tetapi jika tabel itu:
- dipakai oleh calibration,
- dipakai oleh proof/gating,
- dipakai oleh persistence runtime resmi,
- atau disebut sebagai artefak resmi pada manifest policy,

maka tabel itu wajib masuk ke parity discipline yang sama.

## 9) Acceptance Rule (LOCKED)
Sebuah patch dianggap lolos parity hanya jika:
- schema doc, DDL, dan code-level contract sudah sinkron,
- tidak ada kolom/tabel “hantu”,
- official persistence extension yang aktif juga sinkron,
- dan semua anti-drift tests terkait lolos.

## Next
- [`07_CONTRACT_FAILURE_CODES_LOCKED.md`](07_CONTRACT_FAILURE_CODES_LOCKED.md)
