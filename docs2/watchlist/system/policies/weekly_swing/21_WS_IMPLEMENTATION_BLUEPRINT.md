# 21 — WS Implementation Bridge Note

## Purpose

Dokumen ini adalah **bridge note** antara owner docs Weekly Swing dan implementation guidance di `docs/watchlist/system/implementation/weekly_swing/`.
Tujuannya hanya menjaga agar penerjemahan implementasi tidak melanggar boundary antara `PLAN`, `RECOMMENDATION`, dan `CONFIRM`.

Dokumen ini **bukan owner rule bisnis baru** dan **bukan** tempat utama untuk detail build/module/API/persistence/testing.
Detail translation implementasi harus dibaca di folder implementation guidance.

## Blueprint Authority Boundary (LOCKED)

Jika terjadi konflik:
1. `docs/watchlist/system/policy.md` menang
2. owner docs Weekly Swing yang relevan menang
3. dokumen ini wajib tunduk

Dokumen ini hanya menjelaskan bentuk translasi implementasi yang kompatibel dengan policy.

## Placement Guard (LOCKED)

Dokumen ini sengaja dipertahankan di folder policy hanya sebagai **jembatan baca** agar implementer tidak loncat langsung ke guidance tanpa membaca owner rules.
Namun isi dokumen ini harus tetap tipis, boundary-first, dan tidak boleh berkembang menjadi owner normatif baru atau blueprint implementasi rinci.

Jika butuh detail penerjemahan implementasi, rujuk ke:
- `../../implementation/weekly_swing/01_WS_IMPLEMENTATION_SCOPE_AND_BOUNDARY.md`
- `../../implementation/weekly_swing/02_WS_MODULE_MAPPING.md`
- `../../implementation/weekly_swing/03_WS_RUNTIME_ARTIFACT_FLOW.md`
- `../../implementation/weekly_swing/04_WS_API_GUIDANCE.md`
- `../../implementation/weekly_swing/05_WS_PERSISTENCE_GUIDANCE.md`
- `../../implementation/weekly_swing/06_WS_TEST_IMPLEMENTATION_GUIDANCE.md`
- `../../implementation/weekly_swing/07_WS_DELIVERY_CHECKLIST.md`

## Scope Lock

- watchlist only
- weekly_swing only
- bukan portfolio
- bukan execution
- bukan market-data internals

## Mandatory Invariants

1. `RECOMMENDATION` derived from `PLAN` only
2. `CONFIRM` authority tied to `PLAN` candidate eligibility
3. `CONFIRM` does not mutate `RECOMMENDATION`
4. watchlist artifacts remain separate from execution concerns
5. watchlist artifacts remain separate from portfolio concerns
6. persistence/API shape must preserve artifact separation
7. empty recommendation is valid
8. non-recommended candidate may still receive valid confirm if still eligible as PLAN candidate

## Forbidden Blueprint Interpretations

1. “confirm boleh meng-upgrade recommendation”
2. “recommendation boleh memakai data di luar PLAN asal masih relevan”
3. “confirm cukup ditempel sebagai status di recommendation row”
4. “watchlist API boleh sekalian membawa order/execution intent”
5. “portfolio enrichment boleh dimasukkan agar output lebih praktis”
6. “recommendation membership boleh direvisi setelah confirm”
7. “eligibility confirm boleh memakai recommendation membership sebagai syarat”

## Reading Order Pointer

Urutan baca implementer yang benar:
1. pahami owner docs Weekly Swing yang relevan
2. kunci invariant artifact dan contract acceptance
3. baru baca implementation guidance untuk module mapping, runtime flow, API, persistence, tests, dan delivery gate

Detail yang **tidak boleh** diperluas di dokumen ini dan harus hidup di implementation guidance:
- module mapping
- build order per fase
- runtime delivery flow rinci
- delivery / merge checklist
- traceability implementasi yang detail

## Final Rules

1. urutan translasi Weekly Swing adalah: pahami `PLAN`, lalu `RECOMMENDATION`, lalu `CONFIRM`
2. `RECOMMENDATION` dibangun sebagai lapisan terpisah dari `PLAN`
3. `CONFIRM` dibangun sebagai overlay terhadap candidate `PLAN`
4. empty recommendation adalah hasil valid dan harus ditangani eksplisit
5. candidate non-recommended tetap dapat memiliki `CONFIRM` yang valid
6. artifact separation tidak boleh dikorbankan demi convenience view/API

## Final Boundary Reminder

Dokumen ini hanya merangkum boundary translasi yang kompatibel dengan owner docs.
Jika ada kebutuhan detail implementasi yang lebih teknis, tambahkan atau revisi di folder `system/implementation/weekly_swing/`, bukan dengan memperluas authority dokumen ini.
