# Shared Policies — Index

## Purpose

Folder `_shared/` berisi baseline policy lintas strategy yang harus diwarisi strategy watchlist bila relevan.

## Hard Warning

Folder ini bukan tempat untuk menyisipkan aturan strategy-specific secara diam-diam. Jika suatu rule hanya berlaku untuk satu strategy, rule itu harus hidup pada folder strategy owner.

## Reading Order

1. `01_POLICY_FRAMEWORK_OVERVIEW.md`
2. `02_PARAMSET_CONTRACT_GLOBAL.md`
3. `03_VALIDATOR_SPEC_GLOBAL.md`
4. `04_CONTRACT_TESTS_GLOBAL.md`
5. `05_EXECUTION_CANONICAL_GLOBAL.md`
6. `06_SCHEMA_PARITY_RULES.md`
7. `07_CONTRACT_FAILURE_CODES_LOCKED.md`

## Final Rule

Jika terjadi konflik antara baseline shared dan dokumen strategy-specific pada area yang memang hanya milik strategy tersebut, dokumen strategy owner menang untuk area strategy-specific; baseline shared tetap menang untuk area global.

## What Must Stay Out of `_shared/`

Hal berikut tidak boleh dipindahkan ke `_shared/` hanya karena dipakai oleh satu implementasi saat ini:
- scoring strategy,
- ranking strategy,
- selection / grouping strategy,
- strategy-specific output labels,
- snapshot semantics yang eksklusif strategy,
- atau acceptance yang hanya berlaku untuk satu strategy.

Jika topik di atas dimasukkan ke `_shared/`, ownership menjadi kabur dan review drift menjadi lebih sulit.

