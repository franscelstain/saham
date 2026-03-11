# Audit Hash and Reproducibility Contract (LOCKED)

## Hashed artifacts
For effective date D, compute and persist:
- `bars_batch_hash`
- `indicators_batch_hash`
- `eligibility_batch_hash`

## Algorithm (LOCKED)
- SHA-256
- lowercase hex output

## Ordering (LOCKED)
Rows must be sorted by the full deterministic key of the artifact:
- bars: `ticker_id ASC`
- indicators: `ticker_id ASC`
- eligibility: `ticker_id ASC`

If a future artifact uses a wider key, all key columns must be sorted ascending in schema key order.

## Serialization (LOCKED)
- one logical row becomes one serialized line
- fields are serialized in fixed column order defined by the contract/schema
- delimiter is pipe character `|`
- line separator is newline `\n`
- NULL serializes as empty string
- no extra spaces
- final payload is the exact joined line sequence with no trailing newline

## Reproducibility rule (LOCKED)
For identical canonical inputs, same config registry version, same ticker mapping, same market calendar, and same indicator set version, the hashes must be identical across reruns.
