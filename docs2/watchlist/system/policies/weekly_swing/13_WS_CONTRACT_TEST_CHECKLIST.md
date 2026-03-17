# 13 — WS Contract Test Checklist

## Scope

Checklist ini menetapkan acceptance minimum untuk artefak dan boundary Weekly Swing berikut:
1. PLAN
2. RECOMMENDATION
3. CONFIRM
4. relationship antar ketiga lapisan tersebut

## A. PLAN Runtime Shape Acceptance
- [ ] PLAN runtime output memiliki `meta`, `items`, `summary`
- [ ] PLAN output dapat direplay dengan stabil

## B. RECOMMENDATION Runtime Shape Acceptance
- [ ] recommendation output memiliki `meta`, `items`, dan `summary`
- [ ] recommendation `meta.trade_date` cocok dengan PLAN `trade_date`
- [ ] recommendation hanya memuat ticker yang berasal dari PLAN item yang sah
- [ ] recommendation output dapat tersedia walaupun CONFIRM belum ada
- [ ] recommendation output tidak memuat field hasil CONFIRM sebagai input pembentukannya
- [ ] recommendation set boleh kosong
- [ ] `empty_recommendation_flag = true` jika dan hanya jika `recommended_count = 0`
- [ ] recommendation ranking deterministik untuk input yang sama
- [ ] mode `CAPITAL_FREE` tetap valid tanpa capital input
- [ ] mode `CAPITAL_AWARE` tetap deterministic untuk capital input yang sama

## C. Empty Recommendation Acceptance
- [ ] recommendation set kosong dianggap valid jika proses recommendation selesai dengan policy yang sah
- [ ] recommendation set kosong tidak dianggap error hanya karena `TOP_PICKS` dan/atau `SECONDARY` pada PLAN tidak kosong
- [ ] consumer tetap dapat membaca PLAN dan CONFIRM walaupun recommendation set kosong

## D. CONFIRM Runtime Shape Acceptance
- [ ] setiap hasil CONFIRM harus terikat ke candidate PLAN yang sah pada `trade_date` yang sama
- [ ] ticker di luar candidate PLAN tidak boleh menghasilkan CONFIRM yang valid
- [ ] ticker recommended dapat di-confirm
- [ ] ticker non-recommended tetap dapat di-confirm selama masih valid sebagai candidate PLAN
- [ ] recommendation yang kosong tidak menghalangi CONFIRM terhadap candidate PLAN
- [ ] CONFIRM tidak menambah ticker baru di luar PLAN
- [ ] CONFIRM tidak mengubah recommendation membership/rank/score/label/hash

## E. Cross-Layer Boundary Acceptance
- [ ] PLAN dapat berdiri sendiri tanpa RECOMMENDATION dan tanpa CONFIRM
- [ ] RECOMMENDATION hanya membaca PLAN immutable
- [ ] CONFIRM hanya membaca PLAN candidate binding dan overlay input yang sah
- [ ] RECOMMENDATION tidak membaca hasil CONFIRM
- [ ] CONFIRM tidak membentuk recommendation baru
- [ ] ticker recommended lalu confirmed tetap mempertahankan recommendation score/rank/label yang sama
- [ ] ticker non-recommended lalu confirmed tetap tidak otomatis menjadi recommended

## F. Determinism Acceptance
- [ ] PLAN replay dengan input yang sama menghasilkan PLAN output yang identik
- [ ] recommendation replay dengan PLAN dan policy yang sama menghasilkan output recommendation yang identik
- [ ] recommendation capital-aware replay dengan capital input yang sama menghasilkan output yang identik
- [ ] menjalankan CONFIRM setelah recommendation tidak mengubah payload recommendation normatif
- [ ] menjalankan CONFIRM pada ticker non-recommended tidak mengubah membership recommendation

## Final Rules

1. Acceptance Weekly Swing **MUST** mencakup PLAN, RECOMMENDATION, CONFIRM, dan boundary di antaranya.
2. Recommendation availability **MUST NOT** bergantung pada CONFIRM.
3. Recommendation set **MAY** kosong dan kondisi tersebut **MUST** tetap dianggap valid.
4. CONFIRM eligibility **MUST** berasal dari candidate PLAN membership.
5. Ticker non-recommended **MAY** tetap memiliki CONFIRM yang valid jika masih merupakan candidate PLAN.
6. CONFIRM **MUST NOT** mengubah recommendation payload normatif.
