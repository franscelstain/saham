# EOD Bars Contract (Canonical OHLCV)

## Purpose
Define the authoritative current-state canonical OHLCV artifact published by Market Data Platform for one trade date D.

This contract governs:
- canonical row identity
- minimum fields
- publication-context semantics
- validation rules
- invalid-row handling
- null policy

## Canonical output table
`eod_bars`

## Row identity vs publication context (LOCKED)
The logical artifact identity for the current readable bars table is:
- `(trade_date, ticker_id)`

That identity remains the current-state row key.
It does **not** expand to `(trade_date, ticker_id, publication_id)` in the live current table.

However, every live readable row must also carry one mandatory `publication_id` showing which sealed current publication produced that readable state.

Therefore:
- the live current table keeps one row per `(trade_date, ticker_id)`
- `publication_id` is a mandatory publication-context field on that row
- `publication_id` is not a second competing primary key for the live current table
- historical publication-bound row sets belong in publication trail and/or `*_history` tables, not as duplicate live current rows

## Minimum fields
Required minimum fields:
- `trade_date` DATE
- `ticker_id` BIGINT/INT
- `open`, `high`, `low`, `close` DECIMAL
- `volume` BIGINT
- `adj_close` DECIMAL NULL
- `source` VARCHAR(32)
- `run_id` BIGINT
- `publication_id` BIGINT
- `created_at` / equivalent audit timestamp

Equivalent naming is allowed only if semantics remain identical.

## Current-state publication-context rule (LOCKED)
For the live readable table `eod_bars`:
- each row must belong to exactly one sealed publication context
- that context must be represented by non-null `publication_id`
- `publication_id` must match the current readable publication for `trade_date`
- superseded publication row sets must not remain side-by-side in `eod_bars`

If historical row preservation is required per publication version, it must be stored through:
- publication trail + hashes + correction evidence, and/or
- immutable `eod_bars_history` rows keyed by `(publication_id, trade_date, ticker_id)`

## Canonical bar validation rules (LOCKED)
A bar is canonical and publishable to `eod_bars` only if all conditions pass:
1) `open`, `high`, `low`, `close` are non-null and strictly greater than 0.
2) `high >= GREATEST(open, close)`.
3) `low <= LEAST(open, close)`.
4) `high >= low`.
5) `volume` is non-null and `volume >= 0`.
6) `(trade_date, ticker_id)` is unique in current readable output.
7) `trade_date` must be a trading day in the market calendar.
8) `adj_close`, if present, must be strictly greater than 0.
9) `source`, `run_id`, and `publication_id` are mandatory audit/context fields for every canonical live row.

## Invalid-bar handling (LOCKED)
- Invalid rows must not be inserted into `eod_bars`.
- Invalid rows must be recorded in `eod_invalid_bars` with `invalid_reason_code` unless the provider payload was never received at all.
- If a canonical bar is missing because provider data was invalid or absent, eligibility for that ticker/date must be `eligible=0` with the appropriate reason code.

## Null policy (LOCKED)
- Canonical OHLCV fields except `adj_close` must never be NULL in `eod_bars`.
- `publication_id` must never be NULL in `eod_bars`.
- Missing provider fields are handled as invalid rows, not as partially-null canonical rows.
- Consumers must treat `eod_bars` as already validated canonical current-state output and must not apply a second, incompatible validity policy.
