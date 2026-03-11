# Session Snapshot Contract (LOCKED)

## Scope
Session snapshot is an optional, non-streaming upstream artifact for consumer modules that need an additional intra-session reference layer.
It may be captured manually or through a normal API refresh at fixed slots.
It is not real-time streaming data and it must not mutate EOD canonical datasets.

## Important semantic rule (LOCKED)
Session snapshot is aligned to the consumer-readable effective trade date D.
It is **not** a promise that `trade_date` equals the wall-clock capture date.
Wall-clock capture timing is represented only by `captured_at`.

## Default slots
- `OPEN_CHECK` around 09:10
- optional `MIDDAY_CHECK` around 13:30
- optional `PRE_CLOSE_CHECK` around 14:45

## Scope rule (LOCKED)
Default scope is the eligibility set for the effective trade date D unless a narrower upstream-approved scope contract exists.
Default behavior must never assume picks, rankings, or portfolio subsets.

## Minimum fields
- `trade_date`
- `snapshot_slot`
- `ticker_id`
- `captured_at`
- `last_price`
- `prev_close`
- `chg_pct`
- `volume`
- `day_high`
- `day_low`
- source/audit/error fields

## Locked rules
- `trade_date` must equal resolved `trade_date_effective` D
- `captured_at` must store the actual wall-clock timestamp of capture
- failure or absence of a session snapshot must never block EOD finalization or sealing
- retention and slot tolerance are governed by locked session snapshot defaults
- session snapshot rows are not inputs to EOD bar canonicalization or EOD indicator recomputation for the same date
- consumers must not infer that `trade_date` alone means the snapshot was captured on that same calendar day; they must use `captured_at` for that question