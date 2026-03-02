# 20 — Canonical ParamSet Procedures — Weekly Swing

## Purpose
Menetapkan cara:
- memilih ACTIVE param_set WS secara deterministik
- mempromosikan param_set WS sambil menjaga auditability (plan_run menyimpan param_set_id)

Rule (LOCKED):
Paramset hasil kalibrasi tidak boleh dipromote menjadi ACTIVE tanpa OOS proof yang lulus sesuai:
`17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`.

Tanpa OOS proof, paramset hanya boleh berstatus DRAFT.

## Prerequisites
### Weekly Swing
- 19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md
- 17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md

## Inputs
- watchlist_param_sets
- params_json WS (validator pass)
- contoh paramset ACTIVE (non-normatif): `db/PARAMSET_WS_ACTIVE_EXAMPLE.json`
- lock mechanism GET_LOCK
- OOS proof storage: `watchlist_bt_oos_eval_ws`

## Process

### 1) Canonical query: ambil ACTIVE param_set WS
```sql
SELECT *
FROM watchlist_param_sets
WHERE policy_code = 'WS'
  AND status = 'ACTIVE'
ORDER BY updated_at DESC, param_set_id DESC
LIMIT 1;
```

Wajib cek:
- policy_version match
- validator WS pass (dok 06_WS_PARAMSET_VALIDATOR_SPEC.md)

### 2) Lifecycle status (LOCKED)
- DRAFT: kandidat (boleh dibuat/diupdate), belum boleh dipakai live
- ACTIVE: dipakai live
- DEPRECATED: tidak dipakai lagi (audit)

Rule (LOCKED):
- Hanya boleh ada 1 ACTIVE untuk `policy_code='WS'` pada waktu tertentu.

### 3) Promotion atomic (WS)
Sebelum promote ke ACTIVE, wajib lolos gate ini:

Checklist gate (LOCKED):
1) Pastikan OOS proof tersedia untuk hasil kalibrasi terkait (dok 16_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md).
2) Pastikan ada record di watchlist_bt_oos_eval_ws yang relevan untuk param hasil kalibrasi.

Rule (LOCKED): definisi "record OOS yang relevan"
Record OOS dianggap relevan hanya jika memenuhi:
- policy_code = 'WS'
- policy_version sama dengan policy_version paramset yang akan dipromote
- eval_model sama dengan eval_model yang dipakai saat menghasilkan bt_eval/OOS
- window IS/OOS match (from_date_is/to_date_is dan from_date_oos/to_date_oos) terhadap run kalibrasi
- param_id_best_is sama dengan param_id hasil pemilihan best param untuk kalibrasi tersebut

Jika tidak bisa melakukan match ini secara deterministik, proses promote wajib menerima `oos_id`
sebagai input dan memverifikasi semua kondisi di atas terhadap row tersebut.

3) Pastikan metrik OOS memenuhi acceptance criteria (dok 16_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md).

Jika salah satu gagal:
- proses promote harus abort
- status paramset tetap DRAFT
- dilarang promote ACTIVE

Langkah (transactional):
1) Validate params_json (app-layer) + policy_version match
2) Jalankan Gate OOS proof (section 3)
3) GET_LOCK('WS:PARAMSET', 10)
4) Deprecate current ACTIVE (jika ada)
5) Promote target param_set_id → ACTIVE
6) COMMIT
7) RELEASE_LOCK

Rule (LOCKED):
- Jika gagal di langkah mana pun setelah GET_LOCK, wajib ROLLBACK dan RELEASE_LOCK.
- Deprecate dan Promote harus berada dalam 1 transaksi yang sama.

Catatan:
- Fail codes dictionary global ada di: ../../db/04_DB_SEED_GLOBAL.sql

## Outputs
- Hanya 1 ACTIVE paramset WS pada waktu tertentu.
- Paramset ACTIVE selalu memiliki OOS proof lulus.

## Failure modes
- lock timeout → abort
- invalid json / policy_version mismatch / validator fail → abort
- OOS proof missing / tidak lulus → abort
- error DB → rollback + release lock

## Reference
- `db/PARAMSET_WS_ACTIVE_EXAMPLE.json` (contoh paramset ACTIVE untuk bootstrap/dev; bukan kontrak)
- `_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md`

## Next
### Weekly Swing
Selesai.
### Action
- db/PROMOTE_PARAMSET.sql
- db/REASON_CODES_SEED.sql