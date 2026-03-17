# 21 — WS Implementation Blueprint

## Purpose

Dokumen ini menetapkan urutan implementasi Weekly Swing secara praktis agar boundary antara PLAN, RECOMMENDATION, dan CONFIRM tetap terjaga saat development berlangsung.

Dokumen ini adalah blueprint implementasi yang **wajib tunduk** pada governance root dan owner docs Weekly Swing.  
Dokumen ini **tidak** boleh dipakai untuk mengubah rule normatif yang sudah dikunci.

## Blueprint Authority Boundary (LOCKED)

Jika terjadi konflik:
1. `docs/watchlist/system/policy.md` menang
2. owner docs Weekly Swing yang relevan menang
3. dokumen ini wajib tunduk

Dokumen ini hanya menjelaskan bentuk translasi implementasi yang kompatibel dengan policy.

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

## Implementation Principles

Implementasi Weekly Swing **MUST** mengikuti prinsip berikut:
1. bangun `PLAN` terlebih dahulu
2. bangun `RECOMMENDATION` hanya setelah `PLAN` immutable tersedia
3. bangun `CONFIRM` sebagai overlay yang membaca candidate `PLAN`
4. jangan membaca `CONFIRM` untuk membentuk `RECOMMENDATION`
5. jangan mencampur payload normatif `PLAN`, `RECOMMENDATION`, dan `CONFIRM` walaupun consumer/view akhirnya menggabungkannya

## Suggested Module Map

### PLAN
- `PlanEngine`
- `PlanAssembler`
- `PlanSerializer`
- `PlanPublisher`

### RECOMMENDATION
- `RecommendationEngine`
- `RecommendationPolicyReader`
- `RecommendationAssembler`
- `RecommendationSerializer`
- `RecommendationPublisher`

### CONFIRM
- `ConfirmBinder`
- `ConfirmOverlayEngine`
- `ConfirmSerializer`
- `ConfirmPublisher`

### Shared / Support
- `PolicyResolver`
- `ReasonCodeResolver`
- `RuntimeArtifactRepository`
- `ContractTestSuite`
- `ArtifactHashResolver`

## Build Order

### Phase 1 — Lock Core Contracts
Baca dan kunci owner utama:
- governance root
- owner docs Weekly Swing
- recommendation owner docs
- confirm owner docs
- contract test owner docs

### Phase 2 — Build PLAN Foundation
1. bind input yang sah untuk PLAN
2. build candidate pool
3. compute PLAN score
4. resolve ranking
5. resolve group semantics
6. assemble PLAN runtime output
7. freeze PLAN as immutable artifact

### Phase 3 — Build PLAN Runtime Persistence / Delivery
Serialize, persist, dan expose PLAN artifact untuk downstream recommendation dan confirm.

### Phase 4 — Build RECOMMENDATION
1. bind immutable PLAN artifact
2. form recommendation source universe from PLAN only
3. compute recommendation logic
4. resolve dynamic recommendation count
5. resolve capital-free mode
6. resolve capital-aware mode jika diaktifkan
7. assemble RECOMMENDATION runtime output
8. persist/publish RECOMMENDATION without waiting for CONFIRM

### Phase 5 — Build CONFIRM Overlay
1. bind valid PLAN candidate
2. validate confirm input / snapshot yang sah
3. evaluate confirm per item
4. assemble CONFIRM runtime output
5. persist/publish CONFIRM without mutating RECOMMENDATION

### Phase 6 — Build Composite Consumer / View Layer
Gabungkan `PLAN`, `RECOMMENDATION`, dan `CONFIRM` dalam consumer/view jika dibutuhkan **tanpa mencampur source semantics**.

### Phase 7 — Contract Tests and Anti-Drift
Jalankan contract checklist, immutability tests, API conformance tests, dan anti-drift checks.

### Phase 8 — Promotion Readiness
Freeze owner contracts, fixtures, examples, dan verify implementation against owner map.

## Minimum Implementation Outputs

Implementasi yang sah minimal menghasilkan:
- `PLAN` artifact
- `RECOMMENDATION` artifact
- `CONFIRM` artifact
- source references antar artifact
- reason-code / hash integrity yang relevan
- test evidence untuk core rules

## Recommendation Implementation Notes

Implementer **MUST** menjaga hal berikut saat membangun `RECOMMENDATION`:
- source recommendation berasal dari `PLAN` immutable
- recommendation tidak membaca `CONFIRM`
- recommendation tidak mengubah `PLAN`
- recommendation dapat menghasilkan empty set
- capital-aware recommendation hanya boleh bergantung pada `PLAN` dan capital input yang sah

## Confirm Implementation Notes

Implementer **MUST** menjaga hal berikut saat membangun `CONFIRM`:
- eligibility berasal dari candidate `PLAN` binding
- ticker non-recommended tetap dapat di-confirm jika masih valid sebagai candidate `PLAN`
- `CONFIRM` tidak mengubah recommendation output
- hasil confirm pada ticker recommended hanya berfungsi sebagai confirm signal, bukan source perubahan recommendation

## Traceability Table

| Concern | Owner policy doc | Implementation concern |
|---|---|---|
| PLAN generation | `08_WS_PLAN_ALGORITHM.md` | plan engine / plan serializer / plan publisher |
| CONFIRM rules | `10_WS_CONFIRM_OVERLAY.md` | confirm binder / confirm overlay engine |
| RECOMMENDATION overview | `22_WS_RECOMMENDATION_OVERVIEW.md` | recommendation service boundary |
| RECOMMENDATION IO contract | `23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md` | API / serializer contract |
| RECOMMENDATION logic | `24_WS_RECOMMENDATION_ALGORITHM.md` | recommendation engine |
| RECOMMENDATION tests/reason codes | `25_WS_RECOMMENDATION_REASON_CODES_AND_TESTS.md` | test suite / reason-code resolver |
| contract acceptance | `13_WS_CONTRACT_TEST_CHECKLIST.md` | implementation test suite |

## Merge Readiness Checklist (LOCKED)

- [ ] PLAN artifact sudah immutable sebelum RECOMMENDATION dibentuk
- [ ] RECOMMENDATION hanya membaca PLAN immutable
- [ ] RECOMMENDATION dapat tersedia tanpa CONFIRM
- [ ] RECOMMENDATION dapat kosong
- [ ] CONFIRM hanya berlaku untuk candidate PLAN yang sah
- [ ] candidate non-recommended dapat di-confirm
- [ ] recommendation kosong tidak memblokir CONFIRM pada candidate PLAN
- [ ] CONFIRM tidak mengubah recommendation membership/rank/score/label
- [ ] tidak ada code path yang membaca CONFIRM untuk membentuk RECOMMENDATION
- [ ] tidak ada code path yang memakai recommendation sebagai syarat eligibility CONFIRM pada candidate PLAN
- [ ] tidak ada leakage ke portfolio
- [ ] tidak ada leakage ke execution

## Final Rules

1. urutan implementasi Weekly Swing adalah: build PLAN, lalu build RECOMMENDATION, lalu build CONFIRM
2. `RECOMMENDATION` dibangun sebagai lapisan terpisah dari `PLAN`
3. `CONFIRM` dibangun sebagai overlay terhadap candidate `PLAN`
4. empty recommendation adalah hasil valid dan harus ditangani eksplisit
5. candidate non-recommended tetap dapat memiliki CONFIRM yang valid
6. artifact separation tidak boleh dikorbankan demi convenience view/API
