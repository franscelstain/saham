# Effective Trade Date Contract (LOCKED)

## Rule
For requested trade date `T`:
- if the requested run is finalized `SUCCESS` **and** the dataset for `T` is sealed, then `trade_date_effective = T`
- if the requested run is `HELD` or `FAILED`, or if `SUCCESS` is not sealed, then `trade_date_effective = last_good_trade_date`

`last_good_trade_date` means the latest prior trading day with:
- finalized `SUCCESS`
- required hashes present
- eligibility snapshot present
- dataset sealed

## No-good-fallback rule (LOCKED)
If no prior sealed `SUCCESS` date exists, then `trade_date_effective` must remain `NULL` and the platform must expose the requested run as not consumable.
Consumers must treat this as `no readable upstream dataset`, not as permission to infer a date from raw tables.

## Consumer invariant
Consumers must not build outputs from requested date T unless T is the effective sealed date.
Consumers must resolve D from `eod_runs`; consumers must not infer D from raw tables.

## Same-run consistency rule (LOCKED)
For any resolved effective date D, the consumer-visible bars, indicators, eligibility snapshot, hashes, and seal metadata must all come from one coherent finalized run context.
Implementations must not mix bars from one run with indicators/eligibility/hash from another run for the same D.