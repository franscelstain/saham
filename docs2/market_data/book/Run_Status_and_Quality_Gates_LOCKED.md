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

## Status model (LOCKED)
Terminal downstream-visible statuses are only:
- `SUCCESS`
- `HELD`
- `FAILED`

Implementation-specific in-progress stage labels may exist internally, but they are not a substitute for final readiness.

## Final statuses (LOCKED)
- `SUCCESS`: all required stages completed, gates passed, hashes present, dataset sealed, and final state committed.
- `HELD`: technical pipeline may have completed partially or fully, but output is not safe to consume for requested date T.
- `FAILED`: required stage failed or mandatory artifact is missing.

## Minimum gates (LOCKED)
1) canonical bar publish completed
2) indicator compute completed
3) eligibility snapshot built
4) `coverage_ratio >= COVERAGE_MIN`
5) no mandatory artifact missing for requested date T
Consumers must treat `status='SUCCESS'` and seal presence as jointly required for readability.