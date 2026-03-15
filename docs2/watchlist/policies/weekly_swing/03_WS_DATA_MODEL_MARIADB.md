# 03 — Weekly Swing Data Model (MariaDB)

## Purpose

Dokumen ini adalah owner normatif untuk shape data Weekly Swing yang menghubungkan runtime output contract dengan artefak persistence milik watchlist. Fokus utama dokumen ini adalah runtime PLAN / CONFIRM shape yang harus tetap parity terhadap persistence artifacts, fixtures, dan examples yang relevan.

## Scope

Dokumen ini mengunci:

- shape runtime output PLAN,
- shape runtime output CONFIRM,
- relationship PLAN / CONFIRM pair,
- semantics field yang wajib ada pada output strategy,
- mapping parity antara runtime shape tersebut dengan persistence artifacts milik watchlist,
- dan batas relasi antara runtime output contract dengan representasi persistence yang merealisasikannya.

Dokumen ini tidak mendefinisikan ulang kontrak upstream `market_data`.


## Runtime / Persistence Balance

Walaupun judul dokumen ini memakai istilah *Data Model (MariaDB)*, owner normatif pada dokumen ini mencakup dua sisi yang harus tetap parity:

- **runtime output contract** yang dipublikasikan oleh Weekly Swing untuk PLAN dan CONFIRM, dan
- **persistence-facing representation** milik watchlist yang merealisasikan shape tersebut pada artefak database, snapshot, atau implementation outputs yang relevan.

Dokumen ini karena itu tidak dibaca hanya sebagai daftar tabel persistence, dan juga tidak dibaca hanya sebagai contoh runtime payload. Dokumen ini adalah jembatan normatif antara keduanya.

## Ownership Rule

Examples, fixtures, dan SQL artifacts hanya boleh merealisasikan shape yang ditetapkan di dokumen ini dan di dokumen owner normatif Weekly Swing yang relevan. Jika terjadi perbedaan, dokumen ini dan owner normatif strategy lain selalu menang.

## A. PLAN Runtime Output Shape

PLAN runtime output berbentuk object JSON dengan top-level keys berikut:

- `meta`
- `items`
- `summary`

### A1. `meta` (PLAN)

Field normatif pada `meta` PLAN adalah:

- `policy`
- `asof_eod_date`
- `trade_date`
- `paramset_id`
- `paramset_hash`
- `plan_hash`
- `data_batch_hash`
- `generated_at`
- `source`
- `fail_code`
- `fail_reason_codes`

#### Semantics

- `policy` adalah label runtime strategy pada output PLAN. Pada examples saat ini nilainya `WEEKLY_SWING`. Untuk parity lintas dokumen, `meta.policy = WEEKLY_SWING` diperlakukan sebagai runtime / display label, sedangkan `policy_code = WS` pada paramset dan prosedur canonical diperlakukan sebagai canonical internal policy code untuk strategy yang sama.
- `paramset_id` dan `paramset_hash` mengikat PLAN terhadap paramset aktif yang dipakai saat run.
- `plan_hash` adalah hash output PLAN yang harus tetap stabil terhadap invariant immutability yang relevan.
- `data_batch_hash` merepresentasikan fingerprint batch data input yang dipakai untuk membentuk PLAN.
- `fail_code` dan `fail_reason_codes` menyatakan status failure / abort di level run bila relevan.

### A2. `items` (PLAN)

`items` adalah array. Untuk branch PLAN yang bukan `NO_TRADE`, setiap item normatif memiliki field:

- `ticker`
- `rank`
- `group_semantic`
- `score_total`
- `scores`
- `levels`
- `flags`
- `reasons`

#### `scores`

Field normatif pada `scores` adalah:

- `score_momentum`
- `score_breakout`
- `score_volume`
- `score_risk`

#### `levels`

Field normatif pada `levels` adalah:

- `entry_ref`
- `entry_band_low`
- `entry_band_high`
- `stop_price`
- `tp1_price`

#### `flags`

Field normatif pada `flags` adalah:

- `eligible`
- `hidden`

#### `reasons`

`reasons` adalah array object. Setiap reason item pada runtime output PLAN minimal memiliki:

- `code`
- `severity`
- `message`
- `payload`

### A3. `summary` (PLAN)

Field normatif pada `summary` PLAN adalah:

- `eligible_count`
- `top_picks_count`
- `secondary_count`
- `watch_only_count`
- `avoid_count`
- `no_trade`
- `no_trade_reason`

`no_trade_reason` berbentuk object dengan field:

- `code`
- `message`

### A4. Branch Rule for `NO_TRADE`

Untuk branch `NO_TRADE`, top-level shape tetap `meta`, `items`, `summary`. Dalam branch ini, `items` dapat kosong, `summary.no_trade = true`, `summary.no_trade_reason` terisi, dan `meta.fail_code` tetap dipakai hanya untuk abort/failure run-level yang memang memiliki dictionary fail code resmi.

## B. CONFIRM Runtime Output Shape

CONFIRM runtime output berbentuk object JSON dengan top-level keys berikut:

- `meta`
- `items`
- `summary`

### B1. `meta` (CONFIRM)

Field normatif pada `meta` CONFIRM adalah:

- `policy`
- `checked_at`
- `snapshot_ts`
- `snapshot_age_sec`
- `source`

#### Semantics

- `policy` pada CONFIRM mengikuti rule yang sama dengan PLAN: `WEEKLY_SWING` sebagai runtime / display label, sedangkan `WS` adalah canonical internal policy code pada paramset dan prosedur strategy.

### B2. `items` (CONFIRM)

`items` adalah array. Setiap item normatif memiliki field:

- `ticker`
- `label`
- `reasons`

`reasons` pada item CONFIRM adalah array object dengan field minimal:

- `code`
- `severity`
- `message`
- `payload`

#### Confirm Label Set

Label runtime CONFIRM yang tercermin pada examples dan summary saat ini adalah:

- `CONFIRMED`
- `NEUTRAL`
- `CAUTION`
- `DELAY`

Jika implementasi menambah label baru, label tersebut harus lebih dulu atau bersamaan ditetapkan secara normatif pada owner Weekly Swing yang relevan.

### B3. `summary` (CONFIRM)

Field normatif pada `summary` CONFIRM adalah:

- `confirmed_count`
- `neutral_count`
- `caution_count`
- `delay_count`

## C. PLAN / CONFIRM Pair Relationship

Pair artifact saat ini mengunci invariant berikut:

- `plan_hash_before`
- `plan_hash_after`
- `plan_hash_unchanged`

Kontrak pair mensyaratkan PLAN hash tidak berubah akibat proses CONFIRM. Relationship ini harus dibaca bersama execution canonical, confirm overlay, dan contract-test checklist.

## D. Confirm Strictness Boundary

Fixture CONFIRM menunjukkan boundary shape berikut:

- unknown top-level field pada payload CONFIRM dianggap schema drift dan expected fail dengan `CF_SCHEMA_DRIFT`,
- field non-contract yang bersifat orderbook-specific (`bid1_price`, `ask1_price`, `spread`, `orderbook_json`) dapat hadir pada payload input fixture tetapi harus diabaikan dan tidak boleh memengaruhi confirm decision.

Rule strictness tersebut wajib dibaca bersama `10_WS_CONFIRM_OVERLAY.md` dan `13_WS_CONTRACT_TEST_CHECKLIST.md`.

## E. Relationship to Examples and Fixtures

Shape di dokumen ini dicerminkan oleh:

- `examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json`
- `examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_NO_TRADE.json`
- `examples/WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json`
- `examples/WS_PLAN_CONFIRM_PAIR_EXAMPLE_A.json`
- `fixtures/confirm_payload_with_unknown_top_level_field.json`
- `fixtures/confirm_payload_with_orderbook_fields.json`

Artifacts tersebut tidak menjadi owner shape. Mereka hanya boleh mengikuti kontrak yang ditetapkan di dokumen ini.

## Final Rule

Tidak boleh ada field aktif pada runtime output Weekly Swing yang hanya hidup di examples atau fixtures tanpa ownership normatif yang jelas di dokumen ini atau di dokumen owner Weekly Swing yang relevan.
