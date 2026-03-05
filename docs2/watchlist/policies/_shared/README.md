# _shared — Index

> **Status:** LOCKED (Normative)
> **Doc Role:** Shared policy contracts index


## Purpose
Entry point kontrak global yang berlaku lintas policy watchlist.

## Scope
Harus dibaca sebelum masuk ke policy spesifik agar aturan global tidak drift.

## Inputs
- Engineer, reviewer, AI, dan auditor lintas policy.

## Outputs
- Daftar kontrak global yang wajib dipatuhi semua policy.

## Policy yang tersedia
- [`../weekly_swing/`](../weekly_swing/README.md)

## Urutan baca global (_shared/01..07)
Baca berurutan dari 01 sampai 07:

- [`01_POLICY_FRAMEWORK_OVERVIEW.md`](01_POLICY_FRAMEWORK_OVERVIEW.md) — gambaran kontrak global policy watchlist dan cara membaca dokumen.
- [`02_PARAMSET_CONTRACT_GLOBAL.md`](02_PARAMSET_CONTRACT_GLOBAL.md) — kontrak global paramset: field wajib, provenance BT/DET/MAN, hash_contract.
- [`03_VALIDATOR_SPEC_GLOBAL.md`](03_VALIDATOR_SPEC_GLOBAL.md) — aturan validator global: konsistensi kontrak, anti-drift, dan minimum failure classes.
- [`04_CONTRACT_TESTS_GLOBAL.md`](04_CONTRACT_TESTS_GLOBAL.md) — test global minimum + checklist artefak wajib per policy.
- [`05_EXECUTION_CANONICAL_GLOBAL.md`](05_EXECUTION_CANONICAL_GLOBAL.md) — aturan eksekusi global: PLAN snapshot EOD vs CONFIRM overlay runtime.
- [`06_SCHEMA_PARITY_RULES.md`](06_SCHEMA_PARITY_RULES.md) — kontrak anti-drift: schema doc ↔ DDL ↔ repository columns.
- [`07_CONTRACT_FAILURE_CODES_LOCKED.md`](07_CONTRACT_FAILURE_CODES_LOCKED.md) — kamus code kegagalan deterministik untuk validator/test layer.

## Start here
Kalau baru masuk ke repo ini:
1. baca `../README.md`
2. baca `../../policy.md`
3. baru masuk ke `_shared/01...07`
4. setelah itu masuk ke policy spesifik

## Aturan referensi dokumen (LOCKED)
- Referensi dokumen **wajib** memakai **nama file real** yang **benar-benar ada** di repo.
- **Dilarang** memakai placeholder atau token contoh, termasuk tetapi tidak terbatas pada: `01_…`, `NN_*`, token contoh berbentuk tiga titik/penanda dummy, `docs … 01 …`, atau variasinya.
- Jika dokumen yang dirujuk belum ada: **hapus referensi** atau **buat file-nya dulu**. Jangan meninggalkan referensi “contoh”.

## Definisi status dokumen & perubahan
### LOCKED / CONTRACT
- Bagian berlabel **LOCKED** atau **CONTRACT** adalah **normatif (mengikat)**.
- Implementasi **wajib** mengikuti bagian LOCKED/CONTRACT. Contoh hanya membantu pemahaman.

### Non-breaking vs Breaking change
- **Non-breaking**: perubahan yang tidak mengubah kontrak/perilaku sistem (mis. perapihan bahasa, menambah contoh non-normatif, menambah catatan tanpa mengubah aturan).
- **Breaking**: perubahan yang mengubah kontrak/perilaku (mis. mengubah pipeline selection, canonical string/hash, reason codes yang dipakai output, rounding/dp hashing, atau schema kolom kontrak).
- Untuk breaking change: wajib update dokumen LOCKED terkait + validator + contract tests, dan sediakan langkah migrasi bila menyentuh schema/data.
