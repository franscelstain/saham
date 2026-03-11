# Audit Hash and Reproducibility Contract (LOCKED)

## Purpose
Define the exact content-hash contract for sealed upstream artifacts so:
- identical consumer-visible content produces identical hashes
- different run provenance alone does not change content identity
- corrected content produces new hashes
- reproducibility can be verified across reruns, replay, and runtime environments

This contract applies only to consumer-visible upstream artifacts for one effective trade date D.

## Hashed artifacts
For effective trade date D, the platform must compute and persist at minimum:
- `bars_batch_hash`
- `indicators_batch_hash`
- `eligibility_batch_hash`

These hashes represent content identity of the sealed dataset for D.

## Algorithm (LOCKED)
- hash algorithm: `SHA-256`
- output encoding: lowercase hexadecimal
- hashes must be computed from canonical serialized payloads only

## Scope of hashing (LOCKED)
Only consumer-visible artifact rows for the effective trade date D being sealed may be included.

Included:
- `eod_bars` rows for `trade_date = D`
- `eod_indicators` rows for `trade_date = D`
- `eod_eligibility` rows for `trade_date = D`

Excluded:
- invalid-bar audit rows
- provider raw rows
- run events
- retry logs
- operator notes
- session snapshots
- correction request metadata
- timestamps not part of canonical content identity
- run provenance fields

## Row ordering (LOCKED)
Rows must be sorted deterministically by the full artifact key.

Current minimum ordering:
- bars: `ticker_id ASC`
- indicators: `ticker_id ASC`
- eligibility: `ticker_id ASC`

If a future artifact adds a wider natural key, ordering must use all key columns ascending in schema key order.

## Field order (LOCKED)

### Bars payload field order
1. `trade_date`
2. `ticker_id`
3. `open`
4. `high`
5. `low`
6. `close`
7. `volume`
8. `adj_close`
9. `source`

### Indicators payload field order
1. `trade_date`
2. `ticker_id`
3. `is_valid`
4. `invalid_reason_code`
5. `indicator_set_version`
6. `dv20_idr`
7. `atr14_pct`
8. `vol_ratio`
9. `roc20`
10. `hh20`

### Eligibility payload field order
1. `trade_date`
2. `ticker_id`
3. `eligible`
4. `reason_code`

## Serialization rules (LOCKED)
Each logical row must serialize to exactly one line.

Rules:
- field delimiter: pipe character `|`
- line separator: newline `\n`
- NULL serializes as empty string
- no leading or trailing spaces
- no extra delimiter at line end
- final payload is the exact line-joined sequence
- no trailing newline after the last row
- rows must appear in locked row order
- fields must appear in locked field order

## Date formatting (LOCKED)
- date format: `YYYY-MM-DD`
- no locale-dependent formatting
- no time component inside hashed date fields unless explicitly part of artifact contract

## Number formatting (LOCKED)
Formatting rules are part of the hash contract.

### Integer-like values
Examples:
- `ticker_id`
- `volume`
- `eligible`
- `is_valid`

Rules:
- serialize as plain base-10 integer string
- no thousands separators
- no decimal point
- no sign unless negative is explicitly valid for that field

### Decimal values
Examples:
- `open`
- `high`
- `low`
- `close`
- `adj_close`
- `dv20_idr`
- `atr14_pct`
- `vol_ratio`
- `roc20`
- `hh20`

Rules:
- use fixed decimal formatting defined by canonical storage/contract
- no scientific notation
- no locale-specific decimal separator
- no thousands separators
- trailing zeros must follow the locked canonical format, not runtime convenience formatting

## Provenance exclusion rule (LOCKED)
The following are audit/provenance fields and must not affect content hashes by themselves:
- `run_id`
- `asof_run_id`
- `created_at`
- `updated_at`
- `ingested_at`
- `computed_at`
- `sealed_at`
- `sealed_by`
- operator notes
- correction request identifiers
- approval metadata
- config identity fields unless those values also change canonical consumer-visible content

These fields remain mandatory for auditability, but they do not define content identity.

## Run inclusion rule (LOCKED)
The hash payload for D must be built from the coherent artifact set that is being sealed and published as one consumer-readable dataset for D.

Implementations must not:
- mix rows from multiple runs for the same D
- hash rows from old publication and corrected publication together
- hash an unsealed partial candidate as if it were the current publication

## Reproducibility rule (LOCKED)
If all of the following remain identical:
- canonical consumer-visible rows for D
- row ordering
- field ordering
- serialization rules
- number/date formatting rules
- indicator set version
- market calendar logic
- ticker identity mapping relevant to the published rows
- effective config snapshot as it affects canonical output

then the resulting hashes must be identical across reruns and replay.

A different `run_id` alone must not change the hash.

## Changed-content rule (LOCKED)
If any consumer-visible hashed content changes for D, then the relevant artifact hash must change.

Examples:
- changed canonical bar value
- changed indicator value
- changed eligibility row
- changed reason code
- changed inclusion/exclusion of a row

## Unchanged-rerun rule (LOCKED)
If a rerun for D produces byte-identical serialized payloads for all hashed artifacts:
- the resulting hashes must remain identical
- the rerun must not be treated as a new corrected publication merely because it is newer in time

## Correction rule (LOCKED)
A controlled correction for D must:
- recompute the full hash set for D
- preserve old hash trail
- publish the new hash set only through a new sealed publication context

Old and new hash trails must both remain auditable.

## Minimum proof requirements
Tests and replay must be able to prove at minimum:
1. same content => same hashes
2. different `run_id` alone => same hashes
3. changed content => changed relevant hash
4. serialization order is stable
5. formatting is stable across runtime/locale differences
6. corrected publication preserves old hash trail while publishing new one

## Anti-drift rule (LOCKED)
If a hash changes without an intentional content change or contract/version change, it is a contract violation and must be treated as reproducibility drift.