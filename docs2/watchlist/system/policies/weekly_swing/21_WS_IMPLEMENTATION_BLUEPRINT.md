# 21 — WS Implementation Blueprint

## Purpose

Dokumen ini menetapkan urutan implementasi Weekly Swing secara praktis agar boundary antara PLAN, RECOMMENDATION, dan CONFIRM tetap terjaga saat development berlangsung.

## Implementation Principles

Implementasi Weekly Swing **MUST** mengikuti prinsip berikut:
1. bangun PLAN terlebih dahulu;
2. bangun RECOMMENDATION hanya setelah PLAN immutable tersedia;
3. bangun CONFIRM sebagai overlay yang membaca candidate PLAN;
4. jangan membaca CONFIRM untuk membentuk RECOMMENDATION;
5. jangan mencampur payload normatif PLAN, RECOMMENDATION, dan CONFIRM walaupun consumer/view akhirnya menggabungkannya.

## Suggested Module Map

### PLAN
- `PlanEngine`
- `PlanAssembler`
- `PlanSerializer`

### RECOMMENDATION
- `RecommendationEngine`
- `RecommendationPolicyReader`
- `RecommendationAssembler`
- `RecommendationSerializer`

### CONFIRM
- `ConfirmBinder`
- `ConfirmOverlayEngine`
- `ConfirmSerializer`

### Shared / Support
- `PolicyResolver`
- `ReasonCodeResolver`
- `RuntimeArtifactRepository`
- `ContractTestSuite`

## Build Order

### Phase 1 — Lock Core Contracts
Baca dan kunci dulu owner utama.

### Phase 2 — Build PLAN Foundation
1. bind EOD inputs yang sah;
2. build candidate pool;
3. compute PLAN score;
4. resolve ranking;
5. resolve group semantics;
6. assemble PLAN runtime output;
7. freeze PLAN as immutable artifact.

### Phase 3 — Build PLAN Runtime Persistence / Delivery
Serialize dan expose PLAN artifact untuk downstream recommendation dan confirm.

### Phase 4 — Build RECOMMENDATION
1. bind immutable PLAN artifact;
2. form recommendation source universe;
3. compute recommendation score;
4. resolve dynamic recommendation count;
5. resolve capital-free mode;
6. resolve capital-aware mode jika diaktifkan;
7. assemble RECOMMENDATION runtime output.

### Phase 5 — Build CONFIRM Overlay
1. bind candidate PLAN yang valid;
2. validate overlay input / snapshot yang sah;
3. evaluate confirm per item;
4. assemble CONFIRM runtime output.

### Phase 6 — Build Composite Consumer / View Layer
Gabungkan PLAN, RECOMMENDATION, dan CONFIRM dalam consumer/view jika dibutuhkan tanpa mencampur source semantics.

### Phase 7 — Contract Tests and Anti-Drift
Jalankan checklist contract dan boundary tests.

### Phase 8 — Promotion Readiness
Freeze owner contracts, fixtures, examples, dan verify implementation against owner map.

## Recommendation Implementation Notes

Implementer **MUST** menjaga hal berikut saat membangun RECOMMENDATION:
- source recommendation berasal dari PLAN immutable;
- recommendation tidak membaca CONFIRM;
- recommendation tidak mengubah PLAN;
- recommendation dapat menghasilkan empty set;
- capital-aware recommendation hanya boleh bergantung pada PLAN dan capital input yang sah.

## Confirm Implementation Notes

Implementer **MUST** menjaga hal berikut saat membangun CONFIRM:
- CONFIRM eligibility berasal dari candidate PLAN binding;
- ticker non-recommended tetap dapat di-confirm jika masih valid sebagai candidate PLAN;
- CONFIRM tidak mengubah recommendation output;
- hasil confirm pada ticker recommended hanya berfungsi sebagai strengthening signal.

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

## Final Rules

1. Urutan implementasi Weekly Swing adalah: build PLAN, lalu build RECOMMENDATION, lalu build CONFIRM.
2. RECOMMENDATION dibangun sebagai lapisan terpisah dari PLAN.
3. CONFIRM dibangun sebagai overlay terhadap candidate PLAN.
4. Empty recommendation adalah hasil yang valid dan harus ditangani secara eksplisit.
5. Candidate non-recommended tetap dapat memiliki CONFIRM yang valid.
