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

## Consumer invariant
Consumers must not build outputs from requested date T unless T is the effective sealed date.
Consumers must resolve D from `eod_runs`; consumers must not infer D from raw tables.
