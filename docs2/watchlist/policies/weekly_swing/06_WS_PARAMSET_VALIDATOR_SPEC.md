# 06 — Weekly Swing Paramset Validator Specification

## Purpose

Dokumen ini adalah owner normatif untuk penentuan valid atau tidak validnya paramset Weekly Swing.

## Scope

Dokumen ini mengunci failure conditions dan expected validator outcomes untuk kontrak paramset aktif Weekly Swing.

## Ownership Rule

Fixtures negatif hanya membuktikan rule yang ditetapkan di dokumen ini. `_refs` tidak boleh lebih authoritative daripada dokumen ini.

## A. Required Top-Level Presence

### Rule
Paramset wajib memiliki seluruh required top-level keys yang ditetapkan di `04_WS_PARAMSET_JSON_CONTRACT.md`.

### Failure Condition
Jika satu required top-level key hilang, validator wajib fail.

### Concrete Fixture Mapping
- `fixtures/paramset_missing_required_key.json` menghilangkan top-level key `risk` dan harus gagal.

### Expected Result
Paramset dengan top-level required key yang hilang tidak boleh dianggap valid.

## B. Unknown Root Key Rejection

### Rule
Unknown root key dilarang.

### Failure Condition
Jika terdapat key root yang tidak termasuk kontrak aktif Weekly Swing, validator wajib fail.

### Concrete Fixture Mapping
- `fixtures/paramset_unknown_key.json` menambahkan `unknown_root_key` dan harus gagal.

### Expected Result
Validator menolak payload dengan unknown root key.

## C. Audit Object Completeness

### Rule
Setiap audit object wajib memiliki field:
- `value`
- `origin`
- `status`
- `bt_target`
- `rationale`
- `change_triggers`

### Failure Condition
Jika salah satu field audit wajib hilang, validator wajib fail.

### Concrete Fixture Mapping
- `fixtures/paramset_missing_audit_field.json` menghilangkan `liquidity.min_dv20_idr.rationale` dan harus gagal.

### Expected Result
Audit object yang tidak lengkap dianggap invalid.

## D. Type Validation

### Rule
Leaf value harus mengikuti type contract Weekly Swing.

### Failure Condition
String numerik, object yang salah bentuk, atau type drift lain pada leaf aktif menyebabkan validator fail.

### Concrete Fixture Mapping
- `fixtures/paramset_type_drift.json` mengubah `liquidity.min_dv20_idr.value` dari numerik menjadi string numerik dan harus gagal.

### Expected Result
Validator menolak type drift walaupun nilai string tampak dapat di-cast.

## E. Origin Enum Validation

### Rule
`origin` wajib memakai enum yang diizinkan kontrak Weekly Swing.

### Failure Condition
Nilai enum di luar set yang diizinkan menyebabkan validator fail.

### Concrete Fixture Mapping
- `fixtures/paramset_bad_enum.json` mengubah `liquidity.min_dv20_idr.origin` menjadi `NOT_A_VALID_ORIGIN` dan harus gagal.

### Expected Result
Validator menolak origin yang tidak terdaftar pada kontrak aktif.

## F. Hash Contract Lock Validation

### Rule
Hash contract pada paramset aktif wajib cocok dengan contract owner untuk:
- `order_by`
- `null_handling`
- `scales`

### Failure Condition
Perubahan pada nilai locked hash contract menyebabkan validator fail.

### Concrete Fixture Mapping
- `fixtures/paramset_bad_hash_contract.json` mengubah `hash_contract.null_handling.value` menjadi `INCLUDE_IN_HASH_PAYLOAD` dan harus gagal.

### Expected Result
Validator menolak drift pada hash contract yang locked.

## G. Canonical Sort-Key Validation

### Rule
`grouping.sort_keys.value` wajib mengikuti urutan canonical Weekly Swing:

- `score_total_desc`
- `score_breakout_desc`
- `score_momentum_desc`
- `dv20_idr_desc`
- `atr14_pct_asc`
- `ticker_id_asc`

### Failure Condition
Perubahan order atau isi sort keys menyebabkan validator fail, kecuali ownership normatifnya diubah secara eksplisit.

## H. Contract Cohesion Rule

### Rule
Validator tidak boleh menerima payload yang shape-nya lolos secara teknis tetapi melanggar fixed-value contract atau audit-object completeness.

### Expected Result
Payload hanya valid jika lolos seluruh rule shape, type, audit, enum, unknown-key, dan locked-contract validation yang relevan.

## I. Supporting Fixtures

Fixtures utama untuk area validator saat ini adalah:

- `fixtures/paramset_valid.json`
- `fixtures/paramset_missing_required_key.json`
- `fixtures/paramset_unknown_key.json`
- `fixtures/paramset_type_drift.json`
- `fixtures/paramset_bad_enum.json`
- `fixtures/paramset_missing_audit_field.json`
- `fixtures/paramset_bad_hash_contract.json`

Fixtures tersebut mendukung dokumen ini dan tidak menggantikannya sebagai owner normatif.

## Final Validator Rule

Tidak ada invalid state Weekly Swing yang boleh hidup hanya di fixtures atau di file referensial tanpa rule normatif yang dapat ditelusuri ke dokumen ini.
