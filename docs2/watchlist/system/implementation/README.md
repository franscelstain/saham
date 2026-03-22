# Watchlist Implementation Guidance

Folder ini berisi panduan implementasi untuk menerjemahkan baseline [`../`](../) menjadi aplikasi watchlist.

Boundary utama:
- implementation guidance **tidak** menggantikan source of truth pada `system/policies/weekly_swing/`
- implementation guidance **harus** tunduk pada baseline `weekly_swing` yang sudah difreeze
- guidance ini tetap berada di domain **watchlist**, bukan portfolio, bukan execution, dan bukan market-data internals

Urutan baca:
1. `weekly_swing/01_WS_IMPLEMENTATION_SCOPE_AND_BOUNDARY.md`
2. `weekly_swing/02_WS_MODULE_MAPPING.md`
3. `weekly_swing/03_WS_RUNTIME_ARTIFACT_FLOW.md`
4. `weekly_swing/04_WS_API_GUIDANCE.md`
5. `weekly_swing/05_WS_PERSISTENCE_GUIDANCE.md`
6. `weekly_swing/05A_WS_CANONICAL_FIELD_MATRIX.md`
7. `weekly_swing/06_WS_TEST_IMPLEMENTATION_GUIDANCE.md`
8. `weekly_swing/07_WS_DELIVERY_CHECKLIST.md`


Audit implementasi watchlist berada di [`../../audit/implementation/`](../../audit/implementation/).


Guidance di folder ini default-nya dibaca sebagai **Layer B**. Ia membantu translasi build, tetapi tidak mengubah owner rule pada folder policy.


## When to Enter This Folder

Folder ini baru boleh dipakai setelah pembaca menyelesaikan jalur berikut:

- root ownership dan system assembly baseline;
- producer-facing intake dari `market_data`;
- consumer behavior owner docs pada `watchlist/system/policies/`;
- baseline lintas-domain pada `docs/system_audit/`.

Folder ini bukan entry point utama untuk memahami sistem aktif.

## Preconditions for Implementation Translation

Sebelum implementation translation dimulai, hal berikut harus sudah locked secara meaning:

- input upstream yang sah;
- posisi watchlist sebagai consumer, bukan producer;
- forbidden shortcuts lintas-domain;
- build order global dan phase transition ke architecture guidance.

## What Must Already Be Locked Before Implementation Starts

Implementer dilarang memulai coding dari folder ini bila masih ada ketidakjelasan pada:

- producer contract;
- consumer behavior contract;
- system assembly order;
- placement `api_architecture` sebagai translation guardrail.
