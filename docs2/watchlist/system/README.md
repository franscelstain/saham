# Watchlist System Docs

Folder ini adalah source of truth untuk membangun sistem watchlist.

## Scope Lock

System docs watchlist:
- hanya membahas watchlist
- hanya membahas saran / recommendation / confirm sebagai bagian watchlist
- tidak membahas portfolio
- tidak membahas execution nyata
- tidak membahas market-data internals

## Current Active Policy

Policy aktif yang dibahas saat ini hanya:
- `policies/weekly_swing/`

## Read First

1. `policies/weekly_swing/01_WS_OVERVIEW.md`
2. `policies/weekly_swing/02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`
3. `policies/weekly_swing/03_WS_DATA_MODEL_MARIADB.md`
4. `policies/weekly_swing/08_WS_PLAN_ALGORITHM.md`
5. `policies/weekly_swing/09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
6. `policies/weekly_swing/22_WS_RECOMMENDATION_OVERVIEW.md`
7. `policies/weekly_swing/23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`
8. `policies/weekly_swing/24_WS_RECOMMENDATION_ALGORITHM.md`
9. `policies/weekly_swing/10_WS_CONFIRM_OVERLAY.md`
10. `policies/weekly_swing/13_WS_CONTRACT_TEST_CHECKLIST.md`
11. `policies/weekly_swing/21_WS_IMPLEMENTATION_BLUEPRINT.md`


## Implementation Guidance

Panduan implementasi watchlist tersedia di `docs/watchlist/system/implementation/`. Guidance ini tidak menggantikan owner docs policy dan harus tunduk pada baseline `weekly_swing` yang sudah difreeze.
