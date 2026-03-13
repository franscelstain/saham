# Source Mapping Contract — Public API or Manual Input (LOCKED)

## Purpose
Lock source-to-internal mapping for a personal EOD application that may use public/free APIs or manual input.

## Normalized adapter output (LOCKED)
- `ticker_code`
- `trade_date` (`YYYY-MM-DD`)
- `open`, `high`, `low`, `close` (decimal)
- `volume` (int)
- `adj_close` (decimal or null)
- `source_name`
- `captured_at`

## Mapping to internal (LOCKED)
Target: `eod_bars(trade_date, ticker_id, open, high, low, close, volume, adj_close, source, ingested_at, run_id)`
- `source = source_name`
- `ingested_at = captured_at` if available, otherwise system time when accepted
- `run_id = current run id`

## Date alignment (LOCKED)
- system timezone: timezone name **Asia/Jakarta**
- source date/timestamp must map to exchange trading day and be validated by market calendar
- manual rows must still pass the same market-calendar alignment rule

## Precision (LOCKED)
- prices: `DECIMAL(18,4)`
- volume: `BIGINT`

## Missing fields (LOCKED)
- missing OHLC => invalid => canonical bar rejected
- missing `adj_close` => keep `NULL`; price basis may fallback to `close`
- missing `volume` => invalid => canonical bar rejected

## Edge cases (LOCKED)
- suspended/no-trade day => missing bar => eligibility reason `ELIG_MISSING_BAR`
- corporate-action day => do not fabricate adjusted series
- manual override/correction must leave audit trail through `run_id`, source name, and correction logs