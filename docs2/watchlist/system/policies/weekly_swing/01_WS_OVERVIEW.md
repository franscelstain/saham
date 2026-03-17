# 01 — WS Overview

## Purpose

Dokumen ini memberikan gambaran tingkat tinggi mengenai Weekly Swing watchlist, termasuk tujuan strategy, boundary dokumen, urutan baca inti, dan peta owner normatif antar dokumen.

Weekly Swing watchlist memiliki tiga lapisan output yang berbeda:

1. **PLAN**
2. **RECOMMENDATION**
3. **CONFIRM**

Dokumen ini bukan owner formula recommendation atau logic CONFIRM detail, tetapi dokumen ini wajib menetapkan posisi ketiga lapisan tersebut secara tegas dalam arsitektur Weekly Swing.

## Scope

Dokumen ini mencakup:
- gambaran strategy Weekly Swing sebagai domain watchlist;
- boundary antara PLAN, RECOMMENDATION, dan CONFIRM;
- daftar dokumen owner normatif;
- core reading order;
- implementer orientation untuk memahami urutan dokumen.

Dokumen ini tidak mencakup:
- detail formula PLAN;
- detail formula RECOMMENDATION;
- detail logic CONFIRM;
- execution order placement;
- transaksi aktual;
- portfolio lifecycle setelah beli.

## Weekly Swing Architecture (High Level)

Weekly Swing bekerja dalam urutan konseptual berikut:

1. **PLAN** dibentuk dari EOD input yang sah;
2. **RECOMMENDATION** dibentuk dari PLAN immutable;
3. **CONFIRM** dapat dijalankan terhadap ticker yang masih valid sebagai candidate PLAN.

Ketiga lapisan ini memiliki boundary sebagai berikut:
- RECOMMENDATION hanya membaca PLAN;
- RECOMMENDATION tidak membaca CONFIRM;
- CONFIRM membaca PLAN candidate binding;
- CONFIRM tidak membentuk recommendation;
- CONFIRM tidak mengubah recommendation.

## Recommendation in Weekly Swing

RECOMMENDATION adalah lapisan watchlist, bukan lapisan execution.

Peran RECOMMENDATION adalah:
- memilih subset ticker paling layak disarankan dari hasil PLAN;
- memberi ranking recommendation;
- memberi label recommendation;
- secara opsional memberi suggestion lot dalam mode capital-aware.

RECOMMENDATION dapat tersedia walaupun CONFIRM belum ada.

RECOMMENDATION dapat kosong walaupun `TOP_PICKS` dan/atau `SECONDARY` pada PLAN tidak kosong.

## Ownership Map

### Core Strategy
- `01_WS_OVERVIEW.md`

### Canonical Flow
- `02_WS_CANONICAL_RUNTIME_FLOW.md` — owner untuk urutan canonical `PLAN -> RECOMMENDATION -> CONFIRM`

### Runtime / Data Model
- `03_WS_DATA_MODEL_MARIADB.md`

### PLAN
- `08_WS_PLAN_ALGORITHM.md`
- `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`

### RECOMMENDATION
- `22_WS_RECOMMENDATION_OVERVIEW.md`
- `23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`
- `24_WS_RECOMMENDATION_ALGORITHM.md`
- `25_WS_RECOMMENDATION_REASON_CODES_AND_TESTS.md`

### CONFIRM
- `10_WS_CONFIRM_OVERLAY.md`
- `11_WS_INTRADAY_SNAPSHOT_TABLES.md`

### Acceptance / Delivery
- `13_WS_CONTRACT_TEST_CHECKLIST.md`
- `21_WS_IMPLEMENTATION_BLUEPRINT.md`

## Core Reading Order

1. `01_WS_OVERVIEW.md`
2. `02_WS_CANONICAL_RUNTIME_FLOW.md` — urutan canonical runtime flow
3. `03_WS_DATA_MODEL_MARIADB.md`
4. `08_WS_PLAN_ALGORITHM.md`
5. `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
6. `22_WS_RECOMMENDATION_OVERVIEW.md`
7. `23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`
8. `24_WS_RECOMMENDATION_ALGORITHM.md`
9. `10_WS_CONFIRM_OVERLAY.md`
10. `13_WS_CONTRACT_TEST_CHECKLIST.md`
11. `21_WS_IMPLEMENTATION_BLUEPRINT.md`

## Final Rules

1. Weekly Swing memiliki tiga lapisan output utama: PLAN, RECOMMENDATION, dan CONFIRM.
2. RECOMMENDATION adalah lapisan watchlist yang dibentuk dari PLAN immutable.
3. RECOMMENDATION tidak membutuhkan CONFIRM agar dapat tersedia.
4. RECOMMENDATION dapat kosong walaupun group prioritas PLAN tidak kosong.
5. CONFIRM berlaku terhadap candidate PLAN yang sah, bukan hanya terhadap ticker recommended.
6. CONFIRM tidak membentuk dan tidak mengubah recommendation.
