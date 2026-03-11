# Intraday Snapshot Contract (LOCKED)

## Scope
Intraday snapshot is an optional, best-effort upstream artifact for downstream consumers that need a same-day overlay.
It is not streaming data and it must not mutate EOD canonical datasets.

## Default slots
- `OPEN_CHECK` around 09:10
- optional `MIDDAY_CHECK` around 13:30
- optional `PRE_CLOSE_CHECK` around 14:45

## Scope rule (LOCKED)
Default scope is the eligibility set for the effective trade date D unless a narrower upstream-approved scope contract exists.
Default behavior must never assume downstream picks, rankings, or portfolio subsets.

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
- `trade_date` must equal `trade_date_effective`
- failure or absence of an intraday snapshot must never block EOD finalization or sealing
- retention and slot tolerance are governed by locked intraday defaults
- intraday rows are not inputs to EOD bar canonicalization or EOD indicator recomputation for the same date