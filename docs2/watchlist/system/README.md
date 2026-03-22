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


## Upstream Intake Read First
Sebelum membaca owner docs Weekly Swing, pembaca yang ingin membangun sistem watchlist harus lebih dulu mengikat intake upstream dari `market-data` ke kontrak producer-facing yang sah.

Minimum anchor:
1. `../../market_data/README.md`
2. `../../market_data/book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
3. `../../market_data/book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
4. `../../market_data/book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
5. `../../market_data/book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`

Setelah jalur intake upstream ini jelas, baru lanjut ke owner docs Weekly Swing di bawah.

## Read First

1. `policies/weekly_swing/01_WS_OVERVIEW.md`
2. `policies/weekly_swing/02_WS_CANONICAL_RUNTIME_FLOW.md` — canonical runtime flow (`PLAN -> RECOMMENDATION -> CONFIRM`)
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


## Layer Guard

Folder `system/` boleh direferensikan oleh implementation guidance, examples, fixtures, SQL support, schema docs, atau sample payload.
Artefak-artefak itu membantu penerjemahan baseline, tetapi **tidak otomatis** mengubah paket dokumen menjadi audit Layer C.
Layer C baru relevan bila ada bukti code/app/runtime nyata yang cukup dan bisa ditelusuri.


## Layer Activation Reference

Gunakan [`LAYER_ACTIVATION_RULE.md`](../LAYER_ACTIVATION_RULE.md) untuk menentukan apakah paket harus dibaca sebagai Layer A, B, atau C.


## Watchlist Position in System Assembly

`watchlist/system/` bukan titik awal sistem. Folder ini harus dibaca sebagai node consumer dalam jalur assembly yang lebih besar:

- `market_data` lebih dulu mengunci producer-facing upstream contract;
- `system_audit` mengunci readiness lintas-domain dan forbidden shortcuts;
- `watchlist` kemudian mengunci consumer behavior yang berjalan di atas intake upstream yang sah.

Artinya, watchlist tidak boleh diperlakukan sebagai subsystem yang bebas menentukan urutan build sendiri.

## Preconditions Before Building Watchlist

Sebelum build watchlist dimulai, pembaca harus sudah memastikan:

- producer-facing intake dari `market_data` sudah jelas;
- current-state readiness lintas-domain sudah tidak membuka kontrak paralel;
- behavior owner docs watchlist sudah dibaca lebih dulu daripada implementation guidance.

## Transition to Implementation Guidance

Masuk ke `watchlist/system/implementation/` hanya boleh dilakukan setelah policy dan intake baseline stabil. Folder implementation bukan pintu awal untuk menebak kontrak sistem, melainkan jalur translation setelah owner docs selesai dikunci.
