# Weekly Swing Watchlist

Weekly Swing adalah domain watchlist yang menghasilkan tiga lapisan output yang berbeda:

1. **PLAN**
2. **RECOMMENDATION**
3. **CONFIRM**

Dokumen di folder ini adalah source-of-truth untuk boundary, algoritma, contract, dan acceptance Weekly Swing.

## Scope

Weekly Swing tetap berada dalam domain **watchlist**.

Folder ini mencakup:
- pembentukan PLAN;
- penentuan RECOMMENDATION;
- overlay CONFIRM;
- acceptance dan implementation blueprint.

Folder ini tidak mencakup:
- order execution;
- transaksi beli/jual aktual;
- portfolio lifecycle setelah beli.

## Canonical Flow

Urutan canonical Weekly Swing adalah:

`PLAN -> RECOMMENDATION -> CONFIRM`

Boundary utamanya adalah:
- RECOMMENDATION dibentuk hanya dari PLAN immutable;
- RECOMMENDATION tetap dapat tersedia walaupun CONFIRM belum ada;
- RECOMMENDATION dapat kosong walaupun `TOP_PICKS` / `SECONDARY` tidak kosong;
- CONFIRM dapat dijalankan pada ticker yang masih valid sebagai candidate PLAN;
- CONFIRM tidak membentuk dan tidak mengubah recommendation.

## Quick Owner Map

- `01_WS_OVERVIEW.md`
- `02_WS_CANONICAL_RUNTIME_FLOW.md` — canonical runtime flow (`PLAN -> RECOMMENDATION -> CONFIRM`)
- `03_WS_DATA_MODEL_MARIADB.md`
- `08_WS_PLAN_ALGORITHM.md`
- `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
- `22_WS_RECOMMENDATION_OVERVIEW.md`
- `23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`
- `24_WS_RECOMMENDATION_ALGORITHM.md`
- `10_WS_CONFIRM_OVERLAY.md`
- `13_WS_CONTRACT_TEST_CHECKLIST.md`
- `21_WS_IMPLEMENTATION_BLUEPRINT.md` — bridge note; detail translation implementasi tetap di `../../implementation/weekly_swing/`

## Recommended Reading Order

1. `01_WS_OVERVIEW.md`
2. `02_WS_CANONICAL_RUNTIME_FLOW.md` — canonical runtime flow (`PLAN -> RECOMMENDATION -> CONFIRM`)
3. `03_WS_DATA_MODEL_MARIADB.md`
4. `08_WS_PLAN_ALGORITHM.md`
5. `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
6. `22_WS_RECOMMENDATION_OVERVIEW.md`
7. `23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`
8. `24_WS_RECOMMENDATION_ALGORITHM.md`
9. `10_WS_CONFIRM_OVERLAY.md`
10. `13_WS_CONTRACT_TEST_CHECKLIST.md`
11. `21_WS_IMPLEMENTATION_BLUEPRINT.md` — bridge note only; lanjutkan detail teknis ke `../../implementation/weekly_swing/`

## Implementation Note

Implementer **MUST NOT**:
- membaca CONFIRM untuk membentuk RECOMMENDATION;
- menyamakan `TOP_PICKS` / `SECONDARY` dengan final recommendation set;
- menjadikan recommendation sebagai syarat agar CONFIRM dapat berlaku pada candidate PLAN.

## Final Note

Jika terjadi konflik, utamakan dokumen owner normatif yang lebih spesifik.
