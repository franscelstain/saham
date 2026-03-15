# EOD Cutoff and Finalization Contract (LOCKED)

## Finalization time model
`cutoff_time` is determined by either:
- exchange session close time + `CUT_OFF_GRACE_MINUTES`, or
- fixed `PLATFORM_EOD_CUTOFF_TIME`

The chosen rule must come from the config registry and must be audit-visible per run.

## Finalization semantics (LOCKED)
`SUCCESS` is a **post-seal final state**, not an intermediate stage label.
A run may evaluate gates before seal, but it must not be recorded as finalized `SUCCESS` until all success preconditions are complete.

## Locked rules
- A run for requested date T must not be finalized `SUCCESS` before `cutoff_time(T)`.
- `HELD` or `FAILED` may be recorded before cutoff if an unrecoverable failure is already known.
- A run becomes success-eligible only if all of the following hold for the same `run_id` and the same consumer-visible date D:
  - canonical bars published
  - indicators computed
  - eligibility built
  - quality gates passed
  - hashes computed
- Seal is written only after the run is success-eligible and before final `SUCCESS` is committed.
- Finalized `SUCCESS` requires:
  - success-eligible state achieved
  - seal written for the same effective date D
  - final status committed on the same run context
- `trade_date_effective = D` becomes consumer-usable only together with finalized `SUCCESS` plus seal.

## Anti-ambiguity rule (LOCKED)
Implementations must not expose a transient state where `terminal_status='SUCCESS'` but hashes or seal are still missing.
If hash/seal is pending or failed, the run must remain non-consumable (`HELD`, `FAILED`, or equivalent pre-final status outside the downstream contract).