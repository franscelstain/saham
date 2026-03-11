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

## Minimum status mapping (LOCKED)
- bars missing or coverage below threshold => `HELD`
- indicators missing => `FAILED`
- eligibility missing => `FAILED`
- hashes missing at finalization time => `FAILED`
- unsealed dataset => not ready; requested date must not become effective

## Consumer rule
Consumers must use `trade_date_effective`, not `trade_date_requested`.
