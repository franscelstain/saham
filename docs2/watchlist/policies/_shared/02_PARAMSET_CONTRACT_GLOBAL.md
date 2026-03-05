# 02 — Paramset Contract (Global)

## Purpose
Mengunci kontrak minimum paramset lintas policy agar semua policy punya bentuk, provenance, dan jejak audit yang konsisten.

## Prerequisites
- [`01_POLICY_FRAMEWORK_OVERVIEW.md`](01_POLICY_FRAMEWORK_OVERVIEW.md)

## Kontrak global paramset
- Setiap run PLAN/CONFIRM harus mereferensikan **paramset aktif**.
- Identitas minimum paramset: `policy_code` + `policy_version` + status ACTIVE di DB.
- `paramset_code` boleh dipakai untuk audit/operator convenience, tapi bukan pengganti identitas minimum.
- Paramset menyimpan metadata, asal-usul parameter (BT/DET/MAN), dan `hash_contract`.
- Paramset policy-spesifik boleh menambah field, tapi tidak boleh melanggar kontrak global ini.

## Minimal field yang wajib ada (LOCKED)
- `policy_code`
- `policy_version`
- `schema_version`
- `hash_contract.version.value`

`hash_contract.version.value` adalah sumber versi kontrak hash canonical.
Jangan membuat field alternatif yang diam-diam diperlakukan sama.

## Provenance per-parameter (LOCKED)
Setiap parameter **wajib** punya provenance, dalam salah satu dari dua bentuk:
- (A) **Implicit**: setiap node parameter berbentuk object audit  
  `{ value, origin, status, bt_target, rationale, change_triggers }`
- (B) **Explicit**: top-level `provenance` map per dotted-path

Satu policy boleh pilih salah satu pendekatan, tetapi implementasi validator dan audit harus konsisten.

## Yang tidak boleh
- parameter tanpa `origin`
- parameter tanpa alasan (`rationale`)
- parameter yang bisa berubah tetapi tidak punya `change_triggers`
- dua field berbeda yang maknanya sebenarnya sama

## Output kontrak ini
Dokumen ini harus cukup untuk memastikan bahwa:
- paramset bisa divalidasi tanpa menebak struktur
- auditor bisa tahu asal setiap parameter
- hash contract bisa dihitung dengan dasar yang jelas
