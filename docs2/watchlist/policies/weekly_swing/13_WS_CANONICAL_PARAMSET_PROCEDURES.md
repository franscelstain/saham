# 13 — Canonical ParamSet Procedures — Weekly Swing

## Purpose
Menetapkan cara:
- memilih ACTIVE param_set WS secara deterministik
- mempromosikan param_set WS sambil menjaga auditability (plan_run menyimpan param_set_id)

## Prerequisites
### Weekly Swing
12_WS_CONTRACT_TEST_CHECKLIST.md

## Inputs
- watchlist_param_sets
- params_json WS (validator pass)
- contoh paramset ACTIVE (non-normatif): `db/PARAMSET_WS_ACTIVE_EXAMPLE.json`
- lock mechanism GET_LOCK

## Process
### 1) Canonical query: ambil ACTIVE param_set WS
SELECT *
FROM watchlist_param_sets
WHERE policy_code = 'WS'
  AND status = 'ACTIVE'
ORDER BY updated_at DESC, param_set_id DESC
LIMIT 1;

Wajib cek:
- policy_version match
- validator WS pass (dok 06_WS_PARAMSET_VALIDATOR_SPEC.md)

### 2) Lifecycle status
- ACTIVE: dipakai live
- DEPRECATED: tidak dipakai lagi (audit)

### 3) Promotion atomic (WS)
Langkah (transactional):
1) Validate params_json (app-layer) + policy_version match
2) GET_LOCK('WS:PARAMSET', 10)
3) Deprecate current ACTIVE (jika ada)
4) Promote target param_set_id → ACTIVE
5) COMMIT
6) RELEASE_LOCK

Catatan:
- Fail codes dictionary global ada di: `../../db/04_DB_SEED_GLOBAL.sql`

## Outputs
- Hanya 1 ACTIVE paramset WS pada waktu tertentu.

## Failure modes
- lock timeout / invalid json => abort.

## Reference
- `db/PARAMSET_WS_ACTIVE_EXAMPLE.json` (contoh paramset ACTIVE untuk bootstrap/dev; bukan kontrak)
- `_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md`

## Next
### Weekly Swing
Selesai.
### Action
- db/PROMOTE_PARAMSET.sql
- db/REASON_CODES_SEED.sql