# Watchlist Owner Matrix

## Purpose

Dokumen ini memetakan file owner system dan file audit untuk domain `watchlist`.

## Folder Role

- [`../system/`](../system/) = source of truth untuk membangun sistem watchlist
- [`./`](./) = guardrail untuk menilai kualitas, sinkronisasi, dan boundary system docs

Audit docs tidak boleh menjadi owner rule bisnis.

## Current Active System Owner Files

### Core Orientation
- `system/README.md`
- `system/policies/weekly_swing/01_WS_OVERVIEW.md`
- `system/policies/weekly_swing/README.md`

### Canonical / Runtime
- `system/policies/weekly_swing/02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`
- `system/policies/weekly_swing/03_WS_DATA_MODEL_MARIADB.md`

### Plan / Parameter / Validation
- `system/policies/weekly_swing/04_WS_PARAMSET_JSON_CONTRACT.md`
- `system/policies/weekly_swing/05_WS_PARAMETER_REGISTRY_COMPLETE.md`
- `system/policies/weekly_swing/06_WS_PARAMSET_VALIDATOR_SPEC.md`
- `system/policies/weekly_swing/07_WS_REASON_CODES_AND_HASH.md`
- `system/policies/weekly_swing/08_WS_PLAN_ALGORITHM.md`
- `system/policies/weekly_swing/09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`

### Confirm
- `system/policies/weekly_swing/10_WS_CONFIRM_OVERLAY.md`
- `system/policies/weekly_swing/11_WS_INTRADAY_SNAPSHOT_TABLES.md`

### Acceptance / Delivery
- `system/policies/weekly_swing/13_WS_CONTRACT_TEST_CHECKLIST.md`
- `system/policies/weekly_swing/21_WS_IMPLEMENTATION_BLUEPRINT.md`

### Recommendation
- `system/policies/weekly_swing/22_WS_RECOMMENDATION_OVERVIEW.md`
- `system/policies/weekly_swing/23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`
- `system/policies/weekly_swing/24_WS_RECOMMENDATION_ALGORITHM.md`
- `system/policies/weekly_swing/25_WS_RECOMMENDATION_REASON_CODES_AND_TESTS.md`

### Support Areas to Cross-Check
- `system/policies/weekly_swing/_refs/*`
- `system/policies/weekly_swing/examples/*`
- `system/policies/weekly_swing/fixtures/*`
- `system/policies/weekly_swing/db/*`

## Audit Files

- `audit/README.md`
- `audit/WATCHLIST_AUDIT_FOUNDATION.md`
- `audit/WATCHLIST_SCOPE_LOCK.md`
- `audit/WATCHLIST_OWNER_MATRIX.md`
- `audit/WATCHLIST_AUDIT_CHECKLIST_FINAL.md`
- `audit/WATCHLIST_AUDIT_SHORT.md`
- `audit/WATCHLIST_AUDIT_PROMPT_STANDARD.md`
- `audit/WATCHLIST_CHANGE_IMPACT_MATRIX.md`

## Owner Rules

1. Jika terjadi konflik, owner file di `system/` lebih tinggi daripada contoh di `_refs`, `examples`, atau `fixtures`.
2. File di `audit/` tidak boleh dipakai menggantikan kontrak system docs.
3. Support docs wajib tunduk pada owner docs.
