# 02 — Paramset Contract (Global)

Kontrak global paramset:
- Setiap run PLAN/CONFIRM harus mereferensikan **paramset aktif** (minimal: `policy_code` + `policy_version` + status ACTIVE di DB; opsional: `paramset_code` untuk audit).
- Paramset menyimpan: metadata, asal-usul parameter (BT/DET/MAN), dan `hash_contract`.
- Paramset policy-spesifik boleh menambah field, tapi tidak boleh melanggar kontrak global ini.

Minimal field yang wajib ada di semua paramset:
- `policy_code`
- `policy_version`
- `schema_version`
- `hash_contract.version.value` (versi kontrak hash canonical; menggantikan kebutuhan field `hash_contract_version`)
- Provenance per-parameter **wajib ada**, boleh dalam 2 bentuk:
  - (A) **Implicit**: setiap node parameter berbentuk object audit `{ value, origin, status, bt_target, rationale, change_triggers }`, atau
  - (B) **Explicit**: top-level `provenance` map per dotted-path.
