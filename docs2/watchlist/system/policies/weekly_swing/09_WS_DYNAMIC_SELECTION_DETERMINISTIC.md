# 09 — WS Dynamic Selection (Deterministic)

## Scope

Dokumen ini menetapkan aturan deterministic selection untuk **PLAN group semantics** pada Weekly Swing.

Dokumen ini mengunci:
- candidate ordering yang relevan untuk PLAN;
- dynamic target count untuk group semantics PLAN;
- final group mapping seperti `TOP_PICKS`, `SECONDARY`, `WATCH_ONLY`, dan `AVOID`;
- tie-breaker deterministic pada selection PLAN.

Dokumen ini **tidak** mengunci final recommendation membership.

## Boundary Note — Dynamic Selection vs Recommendation

Dynamic selection pada dokumen ini hanya berlaku untuk penetapan group semantics PLAN.

Target dinamis pada dokumen ini tidak boleh dibaca sebagai target recommendation count.

Akibatnya:
- jumlah `TOP_PICKS` bukan jumlah recommendation final;
- jumlah `SECONDARY` bukan jumlah recommendation final;
- recommendation dapat kosong walaupun dynamic selection menghasilkan item pada group prioritas PLAN.

## Relationship to Recommendation

`TOP_PICKS` dan `SECONDARY` adalah group hasil PLAN.

Kedua group tersebut merepresentasikan prioritas PLAN, tetapi **tidak identik** dengan final recommendation set.

## Tie-Breaker Rule

Tie-breaker pada dokumen ini hanya berlaku untuk selection dan ordering PLAN.

Jika recommendation membutuhkan tie-breaker tambahan, tie-breaker tersebut **MUST** didefinisikan pada dokumen recommendation.

## Final Rules

1. Dokumen ini hanya mengunci deterministic selection untuk PLAN group semantics.
2. `TOP_PICKS`, `SECONDARY`, `WATCH_ONLY`, dan `AVOID` adalah output PLAN, bukan output RECOMMENDATION.
3. Dynamic selection pada dokumen ini **MUST NOT** dipakai untuk menyimpulkan final recommendation count.
4. Recommendation set **MAY** kosong walaupun `TOP_PICKS` dan/atau `SECONDARY` tidak kosong.
5. Recommendation membership **MUST** ditentukan oleh dokumen recommendation, bukan oleh dokumen ini.
