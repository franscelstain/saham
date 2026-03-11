# Provider Mapping Contract — Yahoo API (LOCKED)

## Purpose
Lock provider-to-internal mapping when using Yahoo via PHP API.

## Adapter output (LOCKED)
- ticker_code, trade_date (YYYY-MM-DD)
- open, high, low, close (decimal)
- volume (int)
- adj_close (decimal or null)

## Mapping to internal (LOCKED)
Target: eod_bars(trade_date, ticker_id, open, high, low, close, volume, adj_close, source, ingested_at, run_id)
- source = YAHOO
- ingested_at = now
- run_id = current run id

## Date alignment (LOCKED)
- system timezone Asia/Jakarta
- provider date/timestamp must map to exchange trading day and be validated by market calendar

## Precision (LOCKED)
- prices DECIMAL(18,4)
- volume BIGINT

## Missing fields (LOCKED)
- missing OHLC => invalid => eligible=0 (ELIG_PROVIDER_ERROR / ELIG_INVALID_BAR)
- missing adj_close => keep NULL; price basis may fallback to close

## Edge cases (LOCKED)
- suspended/no trade day => missing bar => ELIG_MISSING_BAR
- CA day => do not fabricate adj series