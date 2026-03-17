# 08 — WS PLAN Algorithm

## Purpose

Dokumen ini menetapkan algoritma normatif untuk membentuk **PLAN** Weekly Swing dari input EOD yang sah sampai ke final PLAN assembly.

PLAN bukan owner untuk memilih final recommendation set. Recommendation adalah lapisan terpisah yang membaca output PLAN immutable sesuai kontrak recommendation.

## Scope

Dokumen ini mencakup:
- pembentukan candidate pool;
- scoring PLAN;
- ordering / ranking PLAN;
- group semantics PLAN;
- plan-derived levels;
- final PLAN output assembly.

Dokumen ini **tidak** mencakup:
- recommendation selection;
- recommendation score;
- recommendation membership;
- confirm overlay logic;
- order execution;
- transaksi aktual.

Output akhir dokumen ini adalah **PLAN final** yang menjadi input normatif bagi layer **RECOMMENDATION** dan **CONFIRM**.

## Canonical PLAN Pipeline (LOCKED)

1. run intake and prerequisite check
2. candidate binding
3. eligibility and data-readiness gate
4. hard guard evaluation
5. score computation
6. forced `WATCH_ONLY` evaluation
7. deterministic selection
8. final group mapping
9. run summary and output assembly

## Boundary Note — PLAN vs RECOMMENDATION

Hasil ranking dan group semantics pada PLAN tidak identik dengan final recommendation set.

Secara khusus:
- item pada `TOP_PICKS` tidak otomatis menjadi recommended;
- item pada `SECONDARY` tidak otomatis menjadi recommended;
- recommendation set dapat kosong walaupun hasil PLAN masih memiliki item pada `TOP_PICKS` dan/atau `SECONDARY`.

## Relationship to Downstream Layers

Output pada akhir PLAN assembly adalah artefak normatif yang menjadi input bagi dua lapisan berbeda:
1. **RECOMMENDATION**
2. **CONFIRM**

Tidak ada logic recommendation atau confirm yang boleh disisipkan ke dalam assembly PLAN pada dokumen ini.

## Final Rules

1. Dokumen ini hanya mengunci pembentukan PLAN.
2. PLAN final **MUST** selesai sebelum RECOMMENDATION dibentuk.
3. PLAN final **MUST** selesai sebelum CONFIRM dibaca sebagai overlay.
4. Membership `TOP_PICKS` dan `SECONDARY` adalah hasil PLAN, bukan hasil RECOMMENDATION.
5. Recommendation selection **MUST** didefinisikan pada dokumen recommendation, bukan pada dokumen PLAN ini.
