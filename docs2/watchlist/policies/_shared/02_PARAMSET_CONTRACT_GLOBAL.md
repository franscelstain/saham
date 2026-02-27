# 02 — Paramset Contract (Global)

Kontrak global paramset:
- Setiap run PLAN/CONFIRM harus mereferensikan `paramset_code` yang aktif.
- Paramset menyimpan: metadata, asal-usul parameter (BT/DET/MAN), dan `hash_contract`.
- Paramset policy-spesifik boleh menambah field, tapi tidak boleh melanggar kontrak global ini.

Minimal field yang wajib ada di semua paramset:
- `policy_code`
- `policy_version`
- `schema_version`
- `hash_contract_version`
- `provenance` (BT/DET/MAN per parameter)
