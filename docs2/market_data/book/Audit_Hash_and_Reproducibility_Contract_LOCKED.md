# Audit Hash and Reproducibility Contract (LOCKED)

## Purpose
Define the exact hash contract for sealed upstream artifacts so reruns with identical controlled inputs produce identical batch hashes.

## Hashed artifacts
For effective date D, compute and persist:
- `bars_batch_hash`
- `indicators_batch_hash`
- `eligibility_batch_hash`

## Algorithm (LOCKED)
- SHA-256
- lowercase hex output

## Artifact row ordering (LOCKED)
Rows must be sorted by the full deterministic key of the artifact.
For the current schema:
- bars: `ticker_id ASC`
- indicators: `ticker_id ASC`
- eligibility: `ticker_id ASC`

If a future artifact uses a wider key, all key columns must be sorted ascending in schema key order.

## Artifact field order (LOCKED)
Within one serialized line, fields must appear exactly in the following order.

### Bars payload field order
1) `trade_date`
2) `ticker_id`
3) `open`
4) `high`
5) `low`
6) `close`
7) `volume`
8) `adj_close`
9) `source`
10) `run_id`

### Indicators payload field order
1) `trade_date`
2) `ticker_id`
3) `is_valid`
4) `invalid_reason_code`
5) `indicator_set_version`
6) `dv20_idr`
7) `atr14_pct`
8) `vol_ratio`
9) `roc20`
10) `hh20`
11) `run_id`

### Eligibility payload field order
1) `trade_date`
2) `ticker_id`
3) `eligible`
4) `reason_code`
5) `asof_run_id`

## Serialization (LOCKED)
- one logical row becomes one serialized line
- fields are serialized in the fixed field order defined above
- delimiter is pipe character `|`
- line separator is newline `\n`
- NULL serializes as empty string
- no extra spaces
- final payload is the exact joined line sequence with no trailing newline
- rows included in the payload must belong only to the effective date D being sealed

## Number and timestamp formatting (LOCKED)
Formatting is governed by `Hash_Number_Formatting_LOCKED.md` and is part of the hash contract.
Locale, thousands separators, scientific notation, and trimmed trailing zeros are forbidden.

## Run inclusion rule (LOCKED)
Hashing is performed over the canonical rows that are consumer-visible for effective date D and tied to the sealing run.
A correction run for the same date must recompute the full artifact hash set from the corrected canonical rows and publish a new seal trail.

## Reproducibility rule (LOCKED)
For identical canonical inputs, same config registry version, same ticker mapping, same market calendar, same indicator set version, and same serialization/formatting rules, the hashes must be identical across reruns.