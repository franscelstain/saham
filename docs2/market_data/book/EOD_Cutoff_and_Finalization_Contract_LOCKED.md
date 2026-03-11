# EOD Cutoff and Finalization Contract (LOCKED)

## Finalization time model
`cutoff_time` is determined by either:
- exchange session close time + `CUT_OFF_GRACE_MINUTES`, or
- fixed `PLATFORM_EOD_CUTOFF_TIME`

The chosen rule must come from the config registry and must be audit-visible per run.

## Locked rules
- A run for requested date T must not be finalized `SUCCESS` before `cutoff_time(T)`.
- `HELD` or `FAILED` may be recorded before cutoff if an unrecoverable failure is already known.
- Final `SUCCESS` requires:
  - canonical bars published
  - indicators computed
  - eligibility built
  - quality gates passed
  - hashes computed
  - seal written