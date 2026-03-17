# 05A — WS Canonical Field Matrix

## Purpose

Dokumen ini mengunci **canonical field set** untuk build layer B agar implementasi tidak drift saat menerjemahkan baseline Weekly Swing ke API, persistence, dan composite read.

Dokumen ini adalah **translation lock**, bukan owner rule bisnis baru.  
Seluruh isi wajib tunduk pada owner docs Weekly Swing.

## Scope Lock

- watchlist only
- weekly_swing only
- bukan portfolio
- bukan execution
- bukan market-data internals

## Rule of Use

1. Field pada tabel di bawah adalah nama canonical di boundary build layer B.
2. Alias internal boleh ada di code, tetapi wajib dimapping kembali ke nama canonical di boundary API/persistence logis.
3. Field canonical minimum tidak boleh dihilangkan hanya karena nilainya kosong; gunakan boolean/array kosong atau `NULL` yang sah sesuai kontrak.
4. Field canonical bukan lisensi untuk menambah rule bisnis baru.

## Canonical Field Matrix

| Artifact / Area | Canonical field | Required | Type / shape minimum | Notes |
|---|---|---:|---|---|
| PLAN API | `strategy_code` | YES | string | strategy aktif |
| PLAN API | `policy_code` | YES | string | policy aktif |
| PLAN API | `policy_version` | YES | string | versi rule |
| PLAN API | `schema_version` | YES | string | versi schema |
| PLAN API | `trade_date` | YES | date string | tanggal trade |
| PLAN API | `param_set_id` | YES | string/int | id paramset aktif |
| PLAN API | `source_artifact_type` | YES | `PLAN` | harus persis `PLAN` |
| PLAN API | `groups` | YES | object/array | grouping hasil PLAN |
| PLAN API | `candidates` | YES | array | candidate PLAN |
| PLAN API | `no_trade` | YES | boolean | selalu ada |
| PLAN API | `reason_codes` | YES | array | `[]` bila kosong |
| RECOMMENDATION API | `strategy_code` | YES | string |  |
| RECOMMENDATION API | `policy_code` | YES | string |  |
| RECOMMENDATION API | `policy_version` | YES | string |  |
| RECOMMENDATION API | `schema_version` | YES | string |  |
| RECOMMENDATION API | `trade_date` | YES | date string |  |
| RECOMMENDATION API | `param_set_id` | YES | string/int |  |
| RECOMMENDATION API | `source_artifact_type` | YES | `RECOMMENDATION` | harus persis `RECOMMENDATION` |
| RECOMMENDATION API | `source_plan_reference` | YES | string | canonical source ref |
| RECOMMENDATION API | `capital_mode` | YES | string | canonical selection mode |
| RECOMMENDATION API | `selected_items` | YES | array | `[]` bila kosong |
| RECOMMENDATION API | `reason_codes` | YES | array | `[]` bila kosong |
| RECOMMENDATION API | `is_empty` | YES | boolean | selalu ada |
| RECOMMENDATION API | `empty_reason_codes` | YES | array | `[]` bila tidak kosong |
| CONFIRM API | `strategy_code` | YES | string |  |
| CONFIRM API | `policy_code` | YES | string |  |
| CONFIRM API | `policy_version` | YES | string |  |
| CONFIRM API | `schema_version` | YES | string | versi schema runtime |
| CONFIRM API | `trade_date` | YES | date string |  |
| CONFIRM API | `param_set_id` | YES | string/int | id paramset aktif runtime |
| CONFIRM API | `source_artifact_type` | YES | `CONFIRM` | harus persis `CONFIRM` |
| CONFIRM API | `source_plan_reference` | YES | string | canonical source ref |
| CONFIRM API | `ticker` | YES | string | candidate PLAN |
| CONFIRM API | `confirm_eligibility_basis` | YES | string/object | basis eligibility |
| CONFIRM API | `confirm_status` | YES | string | canonical status |
| CONFIRM API | `reason_codes` | YES | array | `[]` bila kosong |
| CONFIRM API | `snapshot_reference` | YES | string | basis input confirm |
| Composite API | `plan` | YES | object | section canonical |
| Composite API | `recommendation` | YES | object/array | section canonical |
| Composite API | `confirm` | YES | object/array | section canonical |
| PLAN persistence | `strategy_code` | YES | string | logical field |
| PLAN persistence | `trade_date` | YES | date string | logical field |
| PLAN persistence | `policy_code` | YES | string | logical field |
| PLAN persistence | `policy_version` | YES | string | logical field |
| PLAN persistence | `schema_version` | YES | string | logical field |
| PLAN persistence | `param_set_id` | YES | string/int | logical field |
| PLAN persistence | `created_at` | YES | datetime | audit minimum |
| PLAN persistence | `source_hash` | YES | string/NULL | trace minimum |
| RECOMMENDATION persistence | `strategy_code` | YES | string | logical field |
| RECOMMENDATION persistence | `trade_date` | YES | date string | logical field |
| RECOMMENDATION persistence | `policy_code` | YES | string | logical field |
| RECOMMENDATION persistence | `policy_version` | YES | string | logical field |
| RECOMMENDATION persistence | `schema_version` | YES | string | logical field |
| RECOMMENDATION persistence | `param_set_id` | YES | string/int | logical field |
| RECOMMENDATION persistence | `source_plan_reference` | YES | string | logical source ref |
| RECOMMENDATION persistence | `capital_mode` | YES | string | logical selection mode |
| RECOMMENDATION persistence | `created_at` | YES | datetime | audit minimum |
| CONFIRM persistence | `strategy_code` | YES | string | logical field |
| CONFIRM persistence | `trade_date` | YES | date string | logical field |
| CONFIRM persistence | `policy_code` | YES | string | logical field |
| CONFIRM persistence | `policy_version` | YES | string | logical field |
| CONFIRM persistence | `schema_version` | YES | string | logical field |
| CONFIRM persistence | `param_set_id` | YES | string/int | logical field |
| CONFIRM persistence | `source_plan_reference` | YES | string | logical source ref |
| CONFIRM persistence | `ticker` | YES | string | logical field |
| CONFIRM persistence | `snapshot_reference` | YES | string | logical input ref |
| CONFIRM persistence | `confirm_status` | YES | string | logical status |
| CONFIRM persistence | `created_at` | YES | datetime | audit minimum |

## Forbidden Alias Drift

Boundary build layer B tidak boleh mengganti field canonical berikut dengan alias lain tanpa adapter eksplisit:
- `source_plan_reference`
- `capital_mode`
- `is_empty`
- `empty_reason_codes`
- `confirm_status`
- `snapshot_reference`
- `no_trade`

## Notes on Physical Storage

Physical DB column boleh berbeda bila ada alasan teknis, tetapi:
1. logical canonical field set tetap wajib terdokumentasi;
2. mapping physical-to-logical harus eksplisit di app/code docs;
3. audit layer B tetap menilai boundary logical contract, bukan preferensi nama kolom internal.

## Final Rule

Jika build layer B masih memakai istilah “atau ekuivalen” untuk field minimum pada boundary API/persistence, maka build guidance dianggap belum cukup presisi.
