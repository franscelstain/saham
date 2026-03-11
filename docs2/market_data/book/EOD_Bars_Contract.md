# EOD Bars Contract (Canonical OHLCV)

## Canonical output table
`eod_bars` with PK `(trade_date, ticker_id)`

Columns:
- `trade_date` DATE
- `ticker_id` INT
- `open/high/low/close` DECIMAL(18,4)
- `volume` BIGINT
- `adj_close` DECIMAL(18,4) NULL
- `source` VARCHAR(32)
- `ingested_at` DATETIME
- `run_id` BIGINT

## Canonical bar validation rules (LOCKED)
A bar is canonical and publishable to `eod_bars` only if all conditions pass:
1) `open`, `high`, `low`, `close` are non-null and strictly greater than 0.
2) `high >= GREATEST(open, close)`.
3) `low <= LEAST(open, close)`.
4) `high >= low`.
5) `volume` is non-null and `volume >= 0`.
6) `(trade_date, ticker_id)` is unique in canonical output.
7) `trade_date` must be a trading day in the market calendar.
8) `adj_close`, if present, must be strictly greater than 0.

## Invalid-bar handling (LOCKED)
- Invalid rows must not be inserted into `eod_bars`.
- Invalid rows may be stored in `eod_invalid_bars` for audit.
- If a canonical bar is missing because provider data was invalid or absent, eligibility for that ticker/date must be `eligible=0` with the appropriate reason code.

## Null policy (LOCKED)
- Canonical OHLCV fields except `adj_close` must never be NULL in `eod_bars`.
- Missing provider fields are handled as invalid rows, not as partially-null canonical rows.
