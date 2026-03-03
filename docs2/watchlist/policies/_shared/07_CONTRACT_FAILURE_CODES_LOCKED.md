# 07 — Contract Failure Codes (LOCKED)

## Purpose
`CF_*` adalah **kode kegagalan deterministik** untuk:
- contract tests (WS_CT_XXX), dan
- validator failures (paramset/registry/type drift),
bukan reason code trading (WS_*) dan bukan fail code runtime (PLAN_ABORT_* / CONFIRM_ABORT_*).

## Rules (LOCKED)
- `CF_*` hanya dipakai di test layer / validator layer.
- Pesan error bebas dilarang; FAIL harus mengeluarkan `CF_*` yang tepat.
- Satu failure => satu `CF_*` utama (boleh ada detail tambahan sebagai context).

## Code Dictionary (LOCKED)
- `CF_PARAMSET_MISSING_KEY`
  - Makna: key wajib (berdasarkan registry completeness) tidak ada di paramset.
  - Dipakai oleh: WS_CT_002

- `CF_PARAMSET_UNKNOWN_KEY`
  - Makna: paramset mengandung key yang tidak terdaftar di registry (drift).
  - Dipakai oleh: WS_CT_003

- `CF_PARAMSET_TYPE_DRIFT`
  - Makna: tipe `value` pada key tidak sesuai definisi tipe di registry.
  - Dipakai oleh: WS_CT_004

- `CF_PARAMSET_AUDIT_SCHEMA_INVALID`
  - Makna: node audit leaf tidak memiliki field wajib `{ value, origin, status, bt_target, rationale, change_triggers }` atau formatnya salah.
  - Dipakai oleh: WS_CT_005

- `CF_PARAMSET_ENUM_INVALID`
  - Makna: nilai enum (origin/status/mode keys) tidak termasuk allowed set (LOCKED).
  - Dipakai oleh: WS_CT_006

- `CF_HASH_CONTRACT_VIOLATION`
  - Makna: aturan `hash_contract` yang LOCKED dilanggar (order_by/null_handling/scales dp).
  - Dipakai oleh: WS_CT_007

- `CF_EVAL_GATE_INVALID`
  - Makna: aturan gating eval dilanggar (tipe/limit/range/constraint).
  - Dipakai oleh: WS_CT_008

- `CF_UNIVERSE_EQUIVALENCE_MISMATCH`
  - Makna: universe equivalence (BT vs PROD) tidak match pada sample yang disepakati.
  - Dipakai oleh: WS_CT_018

- `CF_PLAN_UNIVERSE_SNAPSHOT_SCHEMA_INVALID`
  - Makna: export snapshot universe PLAN tidak sesuai schema yang LOCKED.
  - Dipakai oleh: WS_CT_019

- `CF_EVAL_METRICS_INSUFFICIENT`
  - Makna: metrik minimal untuk memilih param_id BEST/ACTIVE tidak terpenuhi (sufficiency guard fail).
  - Dipakai oleh: WS_CT_020

- `CF_OOS_PROOF_FAILED`
  - Makna: bukti OOS (70/30 split + acceptance criteria) gagal.
  - Dipakai oleh: WS_CT_021

- `CF_ARTIFACT_REFERENCE_VIOLATION`
  - Makna: dokumen menyebut artefak di luar manifest/ledger allowlist.
  - Dipakai oleh: WS_CT_022