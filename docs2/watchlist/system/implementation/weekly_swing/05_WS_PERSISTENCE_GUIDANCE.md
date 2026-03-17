# 05 — WS Persistence Guidance

## Purpose

Dokumen ini memberi panduan persistence artifact watchlist agar implementasi aplikasi tetap sinkron dengan baseline docs Weekly Swing.

Dokumen ini adalah **implementation translation only**.  
Dokumen ini tidak boleh dipakai untuk mengubah authority rule dari owner docs.

## Scope Lock

- watchlist only
- weekly_swing only
- bukan portfolio
- bukan execution
- bukan market-data internals

## Persistence Principles (LOCKED)

1. `PLAN`, `RECOMMENDATION`, dan `CONFIRM` adalah artifact terpisah
2. `RECOMMENDATION` adalah turunan dari `PLAN`, bukan update atas `PLAN`
3. `CONFIRM` adalah overlay terhadap candidate `PLAN`, bukan update atas `RECOMMENDATION`
4. artifact yang sudah dipublish tidak boleh dimutasi pada business fields inti
5. watchlist persistence tidak boleh bercampur dengan execution/order/broker persistence
6. watchlist persistence tidak boleh bercampur dengan portfolio/holding persistence
7. watchlist persistence tidak bertugas menyimpan raw market-data provider

## Persistence Objects

### PLAN Artifact Storage
Dipakai untuk:
- baseline immutable harian
- source bagi RECOMMENDATION
- source eligibility bagi CONFIRM

Minimum metadata:
- `strategy_code`
- `trade_date`
- `policy_code`
- `policy_version`
- `schema_version`
- `param_set_id`
- audit timestamps yang relevan
- source hash/reference bila digunakan

### RECOMMENDATION Artifact Storage
Dipakai untuk:
- replay
- audit
- consumer reads
- caching jika dibutuhkan

Minimum metadata:
- `strategy_code`
- `trade_date`
- `policy_code`
- `policy_version`
- `schema_version`
- `param_set_id`
- `source_plan_reference`
- mode selection/capital yang relevan
- audit timestamps yang relevan

### CONFIRM Artifact Storage
Dipakai untuk:
- history confirm
- consumer reads
- audit trail confirm

Minimum metadata:
- `strategy_code`
- `trade_date`
- `policy_code`
- `policy_version`
- `source_plan_reference`
- `ticker`
- confirm input/snapshot reference yang relevan
- audit timestamps yang relevan

### Optional Trace / Audit Storage
Boleh ada bila implementasi memerlukan:
- hash trace
- contract validation trace
- publish log
- request/response audit refs

## Source Reference Rules

1. setiap `RECOMMENDATION` record **must reference** source `PLAN`
2. setiap `CONFIRM` record **must reference** source `PLAN`
3. `CONFIRM` boleh menyimpan reference ke `RECOMMENDATION` hanya sebagai context, **bukan authority**
4. authority eligibility untuk `CONFIRM` tetap berasal dari candidate `PLAN`

## Minimal Storage Keys

Minimum key/logical fields yang wajib bisa dilacak:
- `strategy_code`
- `trade_date`
- `ticker` untuk item-level artifact
- `policy_code`
- `param_set_id`
- `policy_version`
- `schema_version`
- source reference fields
- timestamps audit yang relevan

## Immutability Rule

### Business Fields That Must Not Be Back-Mutated
- PLAN score/rank/group semantics
- RECOMMENDATION membership
- RECOMMENDATION ranking
- RECOMMENDATION scoring/label
- CONFIRM source reference authority

### Allowed Operational Metadata Update
Boleh hanya bila implementasi memang butuh:
- publish timestamp
- read cache timestamp
- non-business operational metadata

Operational metadata update tidak boleh mengubah makna bisnis artifact.

## Write Sequence Minimum

1. publish/freeze `PLAN`
2. derive dan persist `RECOMMENDATION` dari `PLAN`
3. derive dan persist `CONFIRM` dari candidate `PLAN` + confirm inputs yang sah
4. jangan pernah back-mutate business fields `PLAN` atau `RECOMMENDATION`

## Forbidden Persistence Patterns (LOCKED)

1. menulis confirm status ke row recommendation sehingga recommendation terlihat berubah
2. meng-overwrite ranking/grouping recommendation setelah confirm
3. menyisipkan ticker non-PLAN ke storage recommendation
4. mencampur watchlist persistence dengan broker/order/execution persistence
5. mencampur watchlist persistence dengan holdings/portfolio persistence
6. menjadikan recommendation sebagai authority eligibility confirm
7. menyimpan raw market-data provider sebagai tanggung jawab watchlist persistence

## Note on Source Data

Watchlist hanya menyimpan artifact hasil domain watchlist dan reference yang diperlukan untuk audit/traceability.  
Watchlist bukan owner raw market-data provider.

## Anti-Ambiguity Guard

- simpan `param_set_id` bila artifact perlu menunjuk instance paramset aktif yang dipakai saat generate runtime output
- simpan `policy_version` untuk menunjukkan versi rule/business contract
- simpan `schema_version` untuk menunjukkan versi schema/contract yang dipakai
- jangan memakai `paramset_version` sebagai nama field persistence bila maknanya ambigu

## Final Rule

Model persistence yang sah untuk Weekly Swing adalah:
- artifact terpisah
- source reference jelas
- immutable business fields
- no back-mutation from confirm to recommendation
- no leakage ke execution atau portfolio
