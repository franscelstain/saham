# 13 — Weekly Swing Contract Test Checklist

## Purpose

Dokumen ini adalah simpul acceptance untuk Weekly Swing. Dokumen ini merangkum kondisi minimum yang wajib lolos agar implementasi dapat dianggap sesuai kontrak strategy.

## A. PLAN Runtime Shape Acceptance

**Acceptance Item**
PLAN runtime output wajib memiliki top-level shape `meta`, `items`, `summary` serta field-field normatif yang ditetapkan pada data model.

**Owner Contract**
- `03_WS_DATA_MODEL_MARIADB.md`
- `08_WS_PLAN_ALGORITHM.md`

**Supporting Artifacts**
- `examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_A.json`
- `examples/WS_PLAN_RUNTIME_OUTPUT_EXAMPLE_NO_TRADE.json`

**Expected Result**
PLAN payload **must fail acceptance** bila top-level shape bukan `meta`, `items`, `summary`, atau bila field normatif aktif hanya muncul di examples tetapi tidak dapat ditelusuri ke owner contract.

## B. CONFIRM Runtime Shape Acceptance

**Acceptance Item**
CONFIRM runtime output wajib memiliki top-level shape `meta`, `items`, `summary` serta field-item `ticker`, `label`, `reasons`.

**Owner Contract**
- `03_WS_DATA_MODEL_MARIADB.md`
- `10_WS_CONFIRM_OVERLAY.md`

**Supporting Artifacts**
- `examples/WS_CONFIRM_RUNTIME_OUTPUT_EXAMPLE_A.json`

**Expected Result**
CONFIRM payload **must fail acceptance** bila top-level shape drift dari `meta`, `items`, `summary` atau bila field item kontraktual `ticker`, `label`, `reasons` tidak tersedia sesuai owner normatif.

## C. PLAN / CONFIRM Pair Immutability

**Acceptance Item**
PLAN hash tidak boleh berubah akibat proses CONFIRM.

**Owner Contract**
- `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`
- `03_WS_DATA_MODEL_MARIADB.md`
- `10_WS_CONFIRM_OVERLAY.md`

**Supporting Artifacts**
- `examples/WS_PLAN_CONFIRM_PAIR_EXAMPLE_A.json`
- `fixtures/confirm_immutability_pair.json`

**Expected Result**
PLAN/CONFIRM pair **must preserve** `plan_hash_before = plan_hash_after` dan `plan_hash_unchanged = true`; setiap drift pada hash akibat proses CONFIRM **must fail acceptance**.

## D. Paramset Shape Acceptance

**Acceptance Item**
Paramset Weekly Swing wajib memiliki seluruh required top-level keys dan required section keys.

**Owner Contract**
- `04_WS_PARAMSET_JSON_CONTRACT.md`

**Supporting Artifacts**
- `db/PARAMSET_WS_ACTIVE_EXAMPLE.json`
- `fixtures/paramset_valid.json`
- `fixtures/paramset_missing_required_key.json`

**Expected Result**
Missing required top-level key **must fail validation**; misalnya hilangnya `risk` **must fail** dan payload tidak boleh dianggap paramset valid.

## E. Unknown-Key Acceptance

**Acceptance Item**
Unknown root key pada paramset wajib ditolak.

**Owner Contract**
- `04_WS_PARAMSET_JSON_CONTRACT.md`
- `06_WS_PARAMSET_VALIDATOR_SPEC.md`

**Supporting Artifacts**
- `fixtures/paramset_unknown_key.json`

**Expected Result**
Payload dengan `unknown_root_key` **must fail validation** dan tidak boleh di-normalisasi diam-diam sebagai paramset valid.

## F. Audit Object Completeness Acceptance

**Acceptance Item**
Audit object leaf wajib punya `value`, `origin`, `status`, `bt_target`, `rationale`, `change_triggers`.

**Owner Contract**
- `04_WS_PARAMSET_JSON_CONTRACT.md`
- `06_WS_PARAMSET_VALIDATOR_SPEC.md`

**Supporting Artifacts**
- `fixtures/paramset_missing_audit_field.json`

**Expected Result**
Payload tanpa field audit wajib seperti `liquidity.min_dv20_idr.rationale` **must fail validation**; validator tidak boleh mengisi field audit yang hilang secara implisit.

## G. Type and Enum Acceptance

**Acceptance Item**
Type drift dan invalid enum wajib ditolak.

**Owner Contract**
- `04_WS_PARAMSET_JSON_CONTRACT.md`
- `06_WS_PARAMSET_VALIDATOR_SPEC.md`

**Supporting Artifacts**
- `fixtures/paramset_type_drift.json`
- `fixtures/paramset_bad_enum.json`

**Expected Result**
String numerik untuk threshold aktif **must fail** sebagai type drift, dan enum seperti `origin` yang tidak valid **must fail** sebagai enum violation.

## H. Hash Contract Acceptance

**Acceptance Item**
Hash contract Weekly Swing wajib tetap locked pada `order_by`, `null_handling`, dan `scales` yang aktif.

**Owner Contract**
- `07_WS_REASON_CODES_AND_HASH.md`
- `06_WS_PARAMSET_VALIDATOR_SPEC.md`

**Supporting Artifacts**
- `fixtures/paramset_bad_hash_contract.json`
- `fixtures/hash_contract_vectors.json`

**Expected Result**
Drift hash contract seperti perubahan `null_handling` menjadi `INCLUDE_IN_HASH_PAYLOAD` atau perubahan scale aktif **must fail** validation / acceptance.

## I. Confirm Strictness Acceptance

**Acceptance Item**
Unknown top-level field pada payload CONFIRM wajib dianggap schema drift, sedangkan orderbook non-contract fields wajib diabaikan dan tidak memengaruhi decision.

**Owner Contract**
- `03_WS_DATA_MODEL_MARIADB.md`
- `10_WS_CONFIRM_OVERLAY.md`

**Supporting Artifacts**
- `fixtures/confirm_payload_with_unknown_top_level_field.json`
- `fixtures/confirm_payload_with_orderbook_fields.json`

**Expected Result**
- payload dengan `unknown_top_level` harus fail dengan `CF_SCHEMA_DRIFT`,
- field `bid1_price`, `ask1_price`, `spread`, dan `orderbook_json` harus diabaikan penuh, tidak masuk contract shape, dan tidak boleh mengubah confirm decision bila elemen kontraktualnya valid.

## J. Promote Procedure Acceptance

**Acceptance Item**
Promote / activate paramset Weekly Swing wajib mengikuti preconditions, lock semantics, dan OOS gate aktif.

**Owner Contract**
- `20_WS_CANONICAL_PARAMSET_PROCEDURES.md`

**Supporting Artifacts**
- `db/PROMOTE_PARAMSET.sql`

**Expected Result**
Promote **must be accepted only if** target paramset `DRAFT`, policy-scoped lock berhasil diperoleh, existing `ACTIVE` dideprecate, target dipromote menjadi `ACTIVE`, OOS gate aktif lolos, dan lock dilepas / rollback dijalankan sesuai hasil prosedur.

## K. Policy Identifier Parity

**Acceptance Item**
Identifier strategy harus konsisten antara canonical internal policy code dan runtime / display label.

**Owner Contract**
- `03_WS_DATA_MODEL_MARIADB.md`
- `04_WS_PARAMSET_JSON_CONTRACT.md`
- `20_WS_CANONICAL_PARAMSET_PROCEDURES.md`

**Supporting Artifacts**
- runtime examples PLAN / CONFIRM
- active paramset example

**Expected Result**
`policy_code = WS` **must be treated as** canonical internal policy code, sedangkan `meta.policy = WEEKLY_SWING` **must be treated as** runtime / display label untuk strategy yang sama; perbedaan ini tidak boleh dianggap drift kontrak.

## Final Acceptance Rule

Implementasi Weekly Swing hanya dapat dianggap sesuai spesifikasi jika seluruh acceptance item yang relevan terhadap area perubahan lolos tanpa membuat kontrak ganda, schema drift, atau perpindahan ownership ke examples, fixtures, atau SQL artifacts.
