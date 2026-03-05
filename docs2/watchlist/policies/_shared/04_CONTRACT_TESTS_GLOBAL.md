# 04 — Contract Tests (Global)

## Purpose
Dokumen ini menetapkan **test global minimum** yang wajib ada untuk semua policy watchlist.
Tujuannya: mencegah drift antar dokumen, schema, validator, output runtime, dan artefak SQL.

## Prerequisites
- [`01_POLICY_FRAMEWORK_OVERVIEW.md`](01_POLICY_FRAMEWORK_OVERVIEW.md)
- [`02_PARAMSET_CONTRACT_GLOBAL.md`](02_PARAMSET_CONTRACT_GLOBAL.md)
- [`03_VALIDATOR_SPEC_GLOBAL.md`](03_VALIDATOR_SPEC_GLOBAL.md)

## Global tests yang wajib ada (LOCKED)

### 1) Schema parity / anti-drift
- Kolom tabel watchlist harus sesuai `../../db/02_DB_SCHEMA_MARIADB.md` dan `../../db/05_DB_DDL_MARIADB.sql`.
- Dictionary tables, snapshot tables, dan paramset tables tidak boleh punya kolom kontrak yang hilang/berubah makna tanpa update dokumen.

### 2) Paramset validator
- Semua param wajib ada.
- Semua type sesuai registry/contract.
- Semua parameter punya provenance lengkap.
- `hash_contract` valid dan versi kontraknya dikenali.

### 3) Code dictionary parity
- Setiap `reason_code` yang keluar di runtime output harus ada di dictionary resmi.
- Setiap `fail_code` run-level harus ada di dictionary resmi.
- Namespace tidak boleh campur: `WS_*`, `CF_*`, `PLAN_ABORT_*`, `CONFIRM_ABORT_*` punya layer masing-masing.

### 4) Hash reproducibility
- Input yang sama harus menghasilkan hash yang sama.
- Canonical field order, formatting, rounding, dan string policy harus deterministic.

### 5) PLAN/CONFIRM execution contract
- PLAN adalah snapshot EOD dan harus immutable.
- CONFIRM tidak boleh mengubah PLAN.
- Write scope PLAN vs CONFIRM harus terpisah dan bisa dites.

## Minimum required artifacts per policy (LOCKED)
Setiap policy baru **minimal** harus punya artefak berikut sebelum dianggap complete:
1. overview policy
2. execution canonical PLAN/CONFIRM
3. data model / storage mapping
4. paramset JSON contract
5. parameter registry lengkap
6. validator spec
7. reason/failure code mapping yang relevan
8. plan algorithm / selection contract
9. confirm overlay contract
10. contract test checklist
11. minimal example / fixture yang bisa dipakai golden test

Jika salah satu belum ada, policy belum layak disebut **audit-ready**.

## Output test yang diharapkan
Setiap contract test sebaiknya menghasilkan:
- status PASS/FAIL yang deterministik
- code kegagalan yang eksplisit
- referensi dokumen/source-of-truth yang diuji
- payload context minimum untuk debugging

## Policy-specific tests
Test policy-spesifik tetap berada di folder policy masing-masing.
Contoh Weekly Swing:
- `../weekly_swing/13_WS_CONTRACT_TEST_CHECKLIST.md`
- referensi pendukung di [`../weekly_swing/_refs/`](../weekly_swing/_refs/README.md)
