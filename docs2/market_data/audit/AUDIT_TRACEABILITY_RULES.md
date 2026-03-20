# Audit Traceability Rules

## Definition
Traceability berarti satu klaim dapat dilacak dari ringkasan sistem ke contract, schema, operations, tests, dan bila relevan ke evidence.

## Minimum trace chain
Ideal chain:
- `system/`
- `book/`
- `db/`
- `ops/`
- `tests/`
- `evidence/`

## Broken trace examples
- system overview menyebut artifact yang tidak punya contract
- contract menjanjikan field yang tidak ada dukungan schema
- ops runbook menjalankan flow yang tidak ada contract-nya
- evidence mengandung output yang tidak bisa dihubungkan ke contract atau run identity

## Review expectation
Audit harus menyebut area mana yang traceable penuh, mana yang partial, dan mana yang broken.
