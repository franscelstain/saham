# 25 — WS Recommendation Reason Codes and Tests

## Purpose

Dokumen ini menetapkan reason codes normatif dan acceptance minimum untuk layer **RECOMMENDATION** pada Weekly Swing.

## A. Recommendation Reason Codes (LOCKED)

Minimal reason code families yang sah:
- `WS_REC_SELECTED`
- `WS_REC_NOT_SELECTED`
- `WS_REC_BORDERLINE`
- `WS_REC_EMPTY_SET`
- `WS_REC_RANK_OUTSIDE_DYNAMIC_TARGET`
- `WS_REC_CAPITAL_AWARE`
- `WS_REC_CAPITAL_INSUFFICIENT`
- `WS_REC_MIN_LOT_NOT_AFFORDABLE`

## C. Acceptance Minima (LOCKED)

Acceptance minimum recommendation **MUST** mencakup seluruh kasus berikut:
- recommendation tersedia dari PLAN yang valid;
- recommendation tersedia tanpa CONFIRM;
- recommendation set boleh kosong;
- recommendation dapat berjalan tanpa capital input;
- recommendation dapat berjalan dengan capital input;
- recommendation tidak membaca CONFIRM;
- recommendation tidak berubah sebelum vs sesudah CONFIRM.

## D. Determinism Acceptance (LOCKED)

Recommendation **MUST** lolos replay deterministik.

## E. Cross-Layer Acceptance (LOCKED)

1. ticker recommended lalu confirmed tetap mempertahankan recommendation score/rank/label yang sama;
2. ticker non-recommended yang masih valid sebagai PLAN candidate tetap boleh memiliki hasil CONFIRM;
3. CONFIRM tidak boleh menjadi alasan recommendation baru muncul atau hilang.

## Final Rule

1. setiap item recommendation harus explainable melalui `reason_codes[]`;
2. recommendation harus lolos acceptance minimum untuk empty set, capital-free, capital-aware, dan replay deterministik;
3. recommendation dan confirm harus terbukti terpisah boundary-nya;
4. hasil CONFIRM tidak boleh menjadi sumber perubahan recommendation.
