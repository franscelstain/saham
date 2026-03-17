# 23 — WS Recommendation Input / Output Contract

## Purpose

Dokumen ini menetapkan kontrak input dan output normatif untuk layer **RECOMMENDATION** pada Weekly Swing.

## A. Inputs (LOCKED)

RECOMMENDATION **MUST** mengonsumsi hanya immutable PLAN output untuk `trade_date` yang sama.

## B. Optional Inputs (LOCKED)

RECOMMENDATION **MAY** menerima optional capital input untuk mode capital-aware.

## C. Forbidden Inputs (LOCKED)

RECOMMENDATION **MUST NOT** membaca:
- CONFIRM status;
- snapshot intraday;
- intraday breakout result;
- order book / bid-ask ladder;
- transaksi portfolio aktual.

## D. Recommendation Runtime Output Shape (LOCKED)

RECOMMENDATION runtime output **MUST** terdiri dari:
1. `meta`
2. `items`
3. `summary`

Setiap item recommendation minimal **MUST** memuat:
- `ticker`
- `plan_rank`
- `plan_group_semantic`
- `recommendation_score`
- `recommendation_rank`
- `recommendation_label`
- `recommended_flag`
- `reason_codes[]`

## E. Relationship to PLAN and CONFIRM (LOCKED)

1. setiap item recommendation **MUST** berasal dari item PLAN yang sah;
2. recommendation output **MUST NOT** menambah ticker dari luar PLAN;
3. recommendation output **MUST NOT** memuat field hasil CONFIRM sebagai bagian pembentuk recommendation;
4. recommendation membership, score, rank, dan label **MUST NOT** berubah akibat CONFIRM.

## G. Empty Recommendation Behavior (LOCKED)

Recommendation set **MAY** kosong.

Recommendation set kosong **MUST NOT** dianggap error hanya karena `TOP_PICKS` dan/atau `SECONDARY` pada PLAN tidak kosong.

## Final Rule

1. source recommendation **MUST** hanya berasal dari PLAN + policy aktif + optional capital input yang sah;
2. CONFIRM **MUST NOT** menjadi input recommendation;
3. recommendation output **MUST** tersedia dalam struktur `meta`, `items`, `summary`;
4. empty recommendation output **MUST** tetap valid selama dihasilkan dari proses yang sah;
5. recommendation output **MUST** dapat direplay secara deterministik untuk input yang sama.
