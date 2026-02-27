# _shared — Index

## Policy yang tersedia
- `weekly_swing/`

## Urutan baca global (_shared/01..05)
Baca berurutan dari 01 sampai 06:

- `01_POLICY_FRAMEWORK_OVERVIEW.md` — Gambaran kontrak global policy watchlist dan cara membaca dokumen.
- `02_PARAMSET_CONTRACT_GLOBAL.md` — Kontrak global paramset: field wajib, provenance BT/DET/MAN, hash_contract.
- `03_VALIDATOR_SPEC_GLOBAL.md` — Aturan validator global: konsistensi kontrak, anti-placeholder, anti-drift.
- `04_CONTRACT_TESTS_GLOBAL.md` — Daftar test global: anti-drift schema, dictionary codes, hash reproducibility.
- `05_EXECUTION_CANONICAL_GLOBAL.md` — Aturan eksekusi global: PLAN snapshot EOD vs CONFIRM overlay runtime.
- `06_SCHEMA_PARITY_RULES.md` — Kontrak anti-drift: schema doc ↔ DDL ↔ repository columns.

## Aturan referensi dokumen (LOCKED)
- Referensi dokumen **wajib** memakai **nama file real** yang **benar-benar ada** di repo.
- **Dilarang** memakai placeholder atau token contoh, termasuk tetapi tidak terbatas pada: `01_...`, `NN_*`, token contoh berbentuk "<...>", `docs/.../01_...`, atau variasinya.
- Jika dokumen yang dirujuk belum ada: **hapus referensi** atau **buat file-nya dulu**. Jangan meninggalkan referensi “contoh”.

## Definisi status dokumen & perubahan
### LOCKED / CONTRACT
- Bagian berlabel **LOCKED** atau **CONTRACT** adalah **normatif (mengikat)**.
- Implementasi **wajib** mengikuti bagian LOCKED/CONTRACT. Contoh hanya membantu pemahaman.

### Non-breaking vs Breaking change
- **Non-breaking**: perubahan yang tidak mengubah kontrak/perilaku sistem (mis. perapihan bahasa, menambah contoh non-normatif, menambah catatan tanpa mengubah aturan).
- **Breaking**: perubahan yang mengubah kontrak/perilaku (mis. mengubah pipeline selection, canonical string/hash, reason codes yang dipakai output, rounding/dp hashing, atau schema kolom kontrak).
- Untuk breaking change: wajib update dokumen LOCKED terkait + validator + contract tests, dan sediakan langkah migrasi bila menyentuh schema/data.
