# 03 — WS Data Model (MariaDB)

## Purpose

Dokumen ini menetapkan model data dan runtime output shape normatif untuk Weekly Swing watchlist.

Dokumen ini mencakup tiga artefak runtime yang berbeda:
1. PLAN runtime output
2. RECOMMENDATION runtime output
3. CONFIRM runtime output

## Scope

Dokumen ini mencakup:
- runtime output shape untuk PLAN;
- runtime output shape untuk RECOMMENDATION;
- runtime output shape untuk CONFIRM;
- relationship PLAN / RECOMMENDATION / CONFIRM;
- boundary field antar lapisan.

## A. PLAN Runtime Output Shape

PLAN runtime output **MUST** terdiri dari `meta`, `items`, dan `summary`.

## B. RECOMMENDATION Runtime Output Shape

RECOMMENDATION runtime output **MUST** terdiri dari `meta`, `items`, dan `summary`.

### B1. meta (RECOMMENDATION)
`meta` minimal **MUST** memuat:
- `strategy_code`
- `trade_date`
- `policy_code`
- `param_set_id`
- `policy_version`
- `schema_version`
- `capital_mode`
- `input_capital` (nullable)
- `generated_at`
- `recommendation_hash` (optional)

### B2. items (RECOMMENDATION)
Setiap item minimal **MUST** memuat:
- `ticker`
- `plan_rank`
- `plan_group_semantic`
- `recommendation_score`
- `recommendation_rank`
- `recommendation_label`
- `recommended_flag`
- `reason_codes[]`

### B3. summary (RECOMMENDATION)
`summary` minimal **MUST** memuat:
- `recommended_count`
- `capital_mode`
- `empty_recommendation_flag`
- `recommended_tickers[]`

## C. CONFIRM Runtime Output Shape

CONFIRM runtime output hanya berlaku untuk ticker yang berhasil di-bind ke candidate PLAN yang valid pada `trade_date` yang sama.

CONFIRM runtime output **MUST NOT** diperlakukan sebagai input pembentuk RECOMMENDATION.

## D. PLAN / RECOMMENDATION / CONFIRM Relationship

### D1. PLAN as Root Artifact
PLAN adalah artefak akar untuk `trade_date` yang sama.

### D2. RECOMMENDATION Relationship
RECOMMENDATION item **MUST** berasal dari PLAN item yang sah dan **MUST NOT** membaca field hasil CONFIRM.

### D3. CONFIRM Relationship
CONFIRM item **MUST** berasal dari binding ke candidate PLAN yang sah.
CONFIRM item boleh ada walaupun ticker tidak berada pada recommendation set.

### D4. Valid Combined States
Kombinasi state berikut valid:
- `PLAN only`
- `PLAN + RECOMMENDATION`
- `PLAN + CONFIRM (candidate still PLAN-rooted)`
- `PLAN + RECOMMENDATION + CONFIRM`

Kombinasi state berikut tidak valid:
- `RECOMMENDATION without PLAN`
- `CONFIRM without PLAN candidate`

### D5. Boundary Rule
Field hasil CONFIRM **MUST NOT** hidup sebagai input field recommendation.
Field recommendation **MUST NOT** dipakai untuk memutuskan eligibility candidate CONFIRM.

## Final Data Model Rules (LOCKED)

1. Weekly Swing runtime memiliki tiga artefak utama: PLAN, RECOMMENDATION, dan CONFIRM.
2. RECOMMENDATION **MUST** berasal dari PLAN dan tidak boleh dibentuk dari CONFIRM.
3. CONFIRM **MUST** berasal dari candidate PLAN yang sah.
4. Ticker non-recommended **MAY** tetap memiliki CONFIRM selama masih valid sebagai candidate PLAN.
5. Ticker recommended lalu confirmed **MAY** tampil dengan kedua state sekaligus.


## Terminology Lock

- `param_set_id` menunjuk row/instance paramset aktif yang dipakai untuk membentuk artifact.
- `policy_version` menunjuk versi kontrak Weekly Swing yang sedang berlaku.
- `schema_version` menunjuk versi schema paramset yang tervalidasi.
- Nama `paramset_version` tidak lagi normatif dan tidak boleh dipakai sebagai field meta runtime karena berpotensi mencampur tiga makna di atas.
