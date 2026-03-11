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
A different `run_id` alone must not change the hash.