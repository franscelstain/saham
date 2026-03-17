# 04 — Contract Tests (Global)

## Purpose
Dokumen ini menetapkan **test global minimum** yang wajib ada untuk semua policy watchlist.
Tujuannya: mencegah drift antar dokumen, schema, validator, output runtime, dan artefak SQL.

## Prerequisites
- [`01_POLICY_FRAMEWORK_OVERVIEW.md`](01_POLICY_FRAMEWORK_OVERVIEW.md)
- [`02_PARAMSET_CONTRACT_GLOBAL.md`](02_PARAMSET_CONTRACT_GLOBAL.md)
- [`03_VALIDATOR_SPEC_GLOBAL.md`](03_VALIDATOR_SPEC_GLOBAL.md)
- [`05_EXECUTION_CANONICAL_GLOBAL.md`](05_EXECUTION_CANONICAL_GLOBAL.md)

## Global tests yang wajib ada (LOCKED)

### 1) Schema parity / anti-drift
- Kolom tabel watchlist harus sesuai [`../../db/02_DB_SCHEMA_MARIADB.md`](../../db/02_DB_SCHEMA_MARIADB.md) dan [`../../db/05_DB_DDL_MARIADB.sql`](../../db/05_DB_DDL_MARIADB.sql).
- Dictionary tables, snapshot tables, runtime tables, dan paramset tables tidak boleh punya kolom kontrak yang hilang/berubah makna tanpa update dokumen.
- Jika policy mendefinisikan tabel resmi tambahan pada manifest policy, tabel itu juga wajib masuk discipline parity yang sama.

### 2) Paramset validator
- Semua param wajib ada.
- Semua type sesuai registry/contract.
- Semua parameter punya provenance lengkap.
- `hash_contract` valid dan versi kontraknya dikenali.

### 3) Code dictionary parity
- Setiap `reason_code` yang keluar di runtime output harus ada di dictionary resmi.
- Setiap `fail_code` run-level harus ada di dictionary resmi.
- Namespace tidak boleh campur: `WS_*`, `CF_*`, `PLAN_ABORT_*`, `REC_ABORT_*`, `CONFIRM_ABORT_*` punya layer masing-masing bila policy memang memakai layer tersebut.

### 4) Hash reproducibility
- Input yang sama harus menghasilkan hash yang sama.
- Canonical field order, formatting, rounding, dan string policy harus deterministic.

### 5) Runtime artifact flow contract
- `PLAN` adalah snapshot EOD dan harus immutable.
- Layer turunan dari PLAN seperti `RECOMMENDATION`, bila policy memilikinya, harus dibentuk dari PLAN yang immutable dan tidak boleh menulis ulang PLAN.
- `CONFIRM`, bila policy memilikinya, adalah overlay runtime/intraday dan tidak boleh mengubah `PLAN` maupun layer runtime yang immutable di atasnya.
- Write scope antar layer runtime harus terpisah dan bisa dites.
- Jika sebuah policy tidak memakai salah satu layer runtime, policy itu tetap wajib jelas mendefinisikan lifecycle layer yang dipakainya.

## Minimum required artifacts per policy (LOCKED)
Setiap policy baru **minimal** harus punya artefak berikut sebelum dianggap complete:

### Baseline wajib semua policy
1. overview policy
2. canonical runtime artifact flow policy
3. data model / storage mapping
4. paramset JSON contract
5. parameter registry lengkap
6. validator spec
7. reason/failure code mapping yang relevan
8. contract test checklist
9. minimal example / fixture yang bisa dipakai golden test

### Tambahan wajib bila policy punya PLAN
10. plan algorithm / selection contract

### Tambahan wajib bila policy punya layer runtime turunan dari PLAN
11. input/output contract layer tersebut
12. algorithm / derivation contract layer tersebut
13. reason codes / tests layer tersebut

### Tambahan wajib bila policy punya CONFIRM
14. confirm overlay contract

Jika salah satu artefak yang diwajibkan oleh lifecycle policy belum ada, policy belum layak disebut **audit-ready**.

## Output test yang diharapkan
Setiap contract test sebaiknya menghasilkan:
- status PASS/FAIL yang deterministik
- code kegagalan yang eksplisit
- referensi dokumen/source-of-truth yang diuji
- payload context minimum untuk debugging

## Policy-specific tests
Test policy-spesifik tetap berada di folder policy masing-masing.
Contoh Weekly Swing:
- [`../weekly_swing/13_WS_CONTRACT_TEST_CHECKLIST.md`](../weekly_swing/13_WS_CONTRACT_TEST_CHECKLIST.md)
- [`../weekly_swing/22_WS_RECOMMENDATION_OVERVIEW.md`](../weekly_swing/22_WS_RECOMMENDATION_OVERVIEW.md)
- [`../weekly_swing/23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`](../weekly_swing/23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md)
- [`../weekly_swing/24_WS_RECOMMENDATION_ALGORITHM.md`](../weekly_swing/24_WS_RECOMMENDATION_ALGORITHM.md)
- [`../weekly_swing/25_WS_RECOMMENDATION_REASON_CODES_AND_TESTS.md`](../weekly_swing/25_WS_RECOMMENDATION_REASON_CODES_AND_TESTS.md)
- referensi pendukung di [`../weekly_swing/_refs/`](../weekly_swing/_refs/README.md)

## Anti-Misread Rule
Dokumen ini menetapkan baseline test watchlist runtime artifacts.
Dokumen ini **bukan** contract untuk order execution, broker routing, atau domain execution engine.
