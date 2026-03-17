# 05 — Canonical Runtime Artifact Flow (Global)

## Purpose
Mengunci alur runtime artifact global semua policy watchlist agar layer output watchlist tidak bercampur dan tidak saling menulis ulang diam-diam.

## Naming Guard
Nama file ini dipertahankan hanya untuk kompatibilitas referensi lama.
**Jangan** memakai nama file ini sebagai dasar penamaan domain baru.

**Preferred normative term** untuk isi dokumen ini adalah **canonical runtime artifact flow watchlist global**.
Kata `execution` pada nama file adalah **legacy filename only**, bukan istilah domain normatif.
Secara normatif, dokumen ini harus dibaca sebagai **canonical runtime artifact flow watchlist global**, **bukan** broker execution, order execution, atau execution engine.
Jika ada pembaca yang menangkap kata `execution` sebagai domain order placement, pembacaan itu salah dan harus diabaikan.

## Rename Guidance
Jika membuat dokumen/pointer baru, gunakan istilah berikut:
- `canonical runtime artifact flow`
- `watchlist runtime artifact lifecycle`

Hindari membuat dokumen/pointer baru yang memakai kata `execution` untuk domain watchlist.

## Prerequisites
- [`01_POLICY_FRAMEWORK_OVERVIEW.md`](01_POLICY_FRAMEWORK_OVERVIEW.md)
- [`04_CONTRACT_TESTS_GLOBAL.md`](04_CONTRACT_TESTS_GLOBAL.md)

## Aturan runtime artifact global (LOCKED)
- `PLAN`, bila dipakai oleh policy, dibentuk dari snapshot EOD untuk kebutuhan watchlist trading day berikutnya.
- `PLAN` disimpan sebagai snapshot yang bisa diaudit dan **tidak berubah** setelah final.
- Layer runtime turunan dari PLAN, seperti `RECOMMENDATION` bila policy memilikinya, harus dibentuk dari PLAN yang immutable.
- Layer runtime turunan dari PLAN **tidak boleh** menulis ulang `PLAN`.
- `CONFIRM`, bila policy memilikinya, adalah overlay runtime/intraday untuk membantu evaluasi watchlist pada hari trading.
- `CONFIRM` **tidak boleh mengubah** `PLAN`.
- `CONFIRM` juga **tidak boleh mengubah** layer runtime immutable lain yang sudah dibentuk lebih dulu, kecuali policy owner secara eksplisit menetapkan layer itu memang mutable dan aturan mutabilitasnya terdokumentasi.
- Jika sebuah policy tidak punya salah satu layer di atas, policy itu tetap wajib jelas mendefinisikan lifecycle output runtime yang dipakainya.

## Lifecycle baseline lintas policy
Shared layer hanya mengunci baseline global berikut:
1. output watchlist boleh memiliki `PLAN`;
2. policy boleh menurunkan layer runtime dari `PLAN` seperti `RECOMMENDATION`;
3. policy boleh menambahkan `CONFIRM` sebagai overlay runtime/intraday;
4. detail semantics, field, ranking, labels, dan acceptance tiap layer tetap dimiliki oleh owner strategy.

## Implikasi storage (LOCKED)
- Write scope antar layer runtime harus terpisah.
- Header/item `PLAN` yang sudah final tidak boleh di-update diam-diam oleh runner layer sesudahnya.
- Jika policy memiliki `RECOMMENDATION` persistence resmi, write scope `RECOMMENDATION` harus terpisah dari `PLAN`.
- Jika policy memiliki `CONFIRM`, write scope `CONFIRM` harus terpisah dari `PLAN` dan dari layer runtime immutable lain yang relevan.
- Audit replay harus bisa menunjukkan snapshot per layer tanpa mencampur payload antarlayer.

## Source of truth database watchlist
Database watchlist global ada di folder [`../../db/`](../../db/README.md):
- [`../../db/01_DB_OVERVIEW.md`](../../db/01_DB_OVERVIEW.md)
- [`../../db/02_DB_SCHEMA_MARIADB.md`](../../db/02_DB_SCHEMA_MARIADB.md)
- [`../../db/03_DB_INDEXES_AND_CONSTRAINTS.md`](../../db/03_DB_INDEXES_AND_CONSTRAINTS.md)
- [`../../db/04_DB_SEED_GLOBAL.sql`](../../db/04_DB_SEED_GLOBAL.sql)
- [`../../db/05_DB_DDL_MARIADB.sql`](../../db/05_DB_DDL_MARIADB.sql)

## Policy-specific detail
Detail implementasi per policy tetap di folder policy masing-masing.
Contoh Weekly Swing:
- [`../weekly_swing/02_WS_CANONICAL_RUNTIME_FLOW.md`](../weekly_swing/02_WS_CANONICAL_RUNTIME_FLOW.md) — canonical runtime artifact flow Weekly Swing
- [`../weekly_swing/22_WS_RECOMMENDATION_OVERVIEW.md`](../weekly_swing/22_WS_RECOMMENDATION_OVERVIEW.md)
- [`../weekly_swing/23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`](../weekly_swing/23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md)
