# _shared — Index

> **Status:** LOCKED (Normative)
> **Doc Role:** Shared policy contracts index

## Purpose

Dokumen ini adalah entry point untuk kontrak global yang berlaku lintas strategy pada domain watchlist.

## Scope

Folder `_shared/` memuat baseline policy yang dipakai bersama oleh strategy watchlist. Dokumen pada folder ini harus dibaca sebelum masuk ke kontrak strategy-specific agar aturan global tidak drift.

Aturan yang hanya berlaku untuk satu strategy tidak boleh ditetapkan di folder ini dan harus hidup pada folder strategy-specific yang relevan.

## Reading Guidance

Kalau baru masuk ke repo ini, urutan baca yang dianjurkan adalah:

1. `../../policy.md`
2. `../../README.md`
3. `../README.md`
4. dokumen `_shared/01...07`
5. README strategy yang relevan
6. file normatif bernomor pada strategy tersebut

## Shared Contract Areas

Baca dokumen `_shared/` berurutan dari `01` sampai `07`:

- [`01_POLICY_FRAMEWORK_OVERVIEW.md`](01_POLICY_FRAMEWORK_OVERVIEW.md) — overview framework lintas strategy.
- [`02_PARAMSET_CONTRACT_GLOBAL.md`](02_PARAMSET_CONTRACT_GLOBAL.md) — kontrak global paramset.
- [`03_VALIDATOR_SPEC_GLOBAL.md`](03_VALIDATOR_SPEC_GLOBAL.md) — baseline validator global.
- [`04_CONTRACT_TESTS_GLOBAL.md`](04_CONTRACT_TESTS_GLOBAL.md) — baseline contract tests global.
- [`05_EXECUTION_CANONICAL_GLOBAL.md`](05_EXECUTION_CANONICAL_GLOBAL.md) — canonical execution baseline lintas strategy.
- [`06_SCHEMA_PARITY_RULES.md`](06_SCHEMA_PARITY_RULES.md) — aturan parity schema dan implementation artifacts.
- [`07_CONTRACT_FAILURE_CODES_LOCKED.md`](07_CONTRACT_FAILURE_CODES_LOCKED.md) — kontrak failure codes lintas strategy.

## Relationship to Strategy Policies

Strategy-specific policies dapat merujuk atau mewarisi baseline shared yang relevan. Namun, aturan, semantics, atau procedures yang hanya berlaku untuk satu strategy tetap harus hidup di folder strategy-specific yang relevan.

## Conflict Note

Dokumen `_shared/` berlaku lintas strategy, tetapi tidak boleh dipakai untuk menciptakan kontrak strategy-specific paralel. Jika aturan yang memang khusus strategy sudah dikunci oleh file bernomor strategy, owner utamanya tetap file bernomor strategy tersebut.

## Reference Integrity Rule

- Referensi dokumen wajib memakai nama file nyata yang benar-benar ada di repo.
- Placeholder, token contoh, atau nama file dummy tidak boleh dipakai sebagai referensi normatif.
- Jika dokumen yang dirujuk belum ada, referensi harus dihapus atau file-nya harus dibuat lebih dahulu.

## Change Classification

### LOCKED / CONTRACT
- Bagian berlabel **LOCKED** atau **CONTRACT** bersifat normatif.
- Implementasi wajib mengikuti bagian normatif tersebut.

### Non-breaking vs Breaking Change
- **Non-breaking**: perubahan yang tidak mengubah kontrak atau perilaku sistem.
- **Breaking**: perubahan yang mengubah kontrak, perilaku, canonical logic, reason codes, hash semantics, atau schema kontraktual.

Untuk perubahan breaking, dokumen normatif terkait, validator, contract tests, dan migration/parity artifacts yang relevan harus ikut diperbarui.
