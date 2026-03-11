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
- rows included in the payload must belong only to the effective date D being sealed

## Reproducibility rule (LOCKED)
For identical canonical inputs, same config registry version, same ticker mapping, same market calendar, same indicator set version, and same serialization/formatting rules, the hashes must be identical across reruns.

===== docs/market_data/book/Run_Status_and_Quality_Gates_LOCKED.md =====
# Run Status and Quality Gates (LOCKED)

## Purpose
Run telemetry and finalization rules so consumers never consume half-ready data.

## `eod_runs` conceptual fields
- requested/effective dates
- status
- current/final stage
- coverage ratio
- row counts
- invalid counts
- warning/hard reject counts
- hashes
- seal metadata
- notes and timestamps

## Final statuses (LOCKED)
- `SUCCESS`: all required stages completed, gates passed, hashes present, dataset sealed.
- `HELD`: technical pipeline may have completed, but output is not safe to consume for requested date T.
- `FAILED`: required stage failed or mandatory artifact is missing.

## Minimum gates (LOCKED)
1) canonical bar publish completed
2) indicator compute completed
3) eligibility snapshot built
4) `coverage_ratio >= COVERAGE_MIN`
5) no mandatory artifact missing for requested date T
6) hashes present before seal
7) finalization occurs only after cutoff contract permits it
8) seal is written before requested date T can become consumer-visible effective output

## Minimum status mapping (LOCKED)
- bars missing or coverage below threshold => `HELD`
- indicators missing => `FAILED`
- eligibility missing => `FAILED`
- hashes missing at finalization time => `FAILED`
- unsealed dataset => not ready; requested date must not become effective

## Consumer rule
Consumers must use `trade_date_effective`, not `trade_date_requested`.