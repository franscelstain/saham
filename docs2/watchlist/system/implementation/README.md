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
6. `weekly_swing/06_WS_TEST_IMPLEMENTATION_GUIDANCE.md`
7. `weekly_swing/07_WS_DELIVERY_CHECKLIST.md`


Audit implementasi watchlist berada di [`../../audit/implementation/`](../../audit/implementation/).
