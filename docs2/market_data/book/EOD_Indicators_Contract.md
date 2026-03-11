# EOD Indicators Contract

## Output table
`eod_indicators` with PK `(trade_date, ticker_id)`

Meta columns:
- `is_valid` (1/0)
- `invalid_reason_code`
- `indicator_set_version`
- `computed_at`
- `run_id`

Minimum baseline columns:
- `dv20_idr`
- `atr14_pct`
- `vol_ratio`
- `roc20`
- `hh20`

## Locked semantics
- Windows use **trading-day order**, never calendar-day differences.
- All output-affecting semantics come from the effective config registry and selected defaults in this documentation set.
- `ATR` / `TR` always use real OHLC and previous real close, never adjusted price.
- Price-series indicators use `P(D)`, where `P(D) = adj_close` when `PRICE_BASIS_DEFAULT=ADJ_CLOSE` and `adj_close` is available, otherwise `close`.
- `D[-20]` means the 20th prior trading day relative to D, excluding D itself.
- Insufficient history for any mandatory baseline indicator yields `NULL` for that indicator and `is_valid=0` with reason code `IND_INSUFFICIENT_HISTORY`.
- Any semantic change to formulas, defaults, hash-affecting formatting, or included baseline columns requires a new `indicator_set_version` and recomputation.

## Validity policy (LOCKED)
`is_valid=1` iff:
- required input bars exist for all needed trading-day positions
- formulas complete without compute error
- no mandatory baseline indicator is NULL

Otherwise `is_valid=0` with one of the registered indicator reason codes.