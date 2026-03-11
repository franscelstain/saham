# Provider Data Acquisition Contract (API-based)

## Purpose
Lock the acquisition behavior so outputs stay deterministic and auditable.

## Provider adapter (LOCKED)
Implementation must use an adapter interface:
- fetchEodBars(trade_date, ticker_codes[]) -> provider_bars[]

## Request rules (LOCKED)
- bounded concurrency (default low)
- throttle + jitter between requests (configurable)
- retry with backoff on 429/503/timeout (max 3)
- circuit breaker if error rate spikes
- record http_status + error class in run notes (and optionally per-ticker log)

## Failure classification (LOCKED)
- transient (429/503/timeout): retry, then HELD if unresolved
- format change / parsing fails widely: FAILED
- partial: allowed if coverage >= COVERAGE_MIN, else HELD

## Parsing + mapping (LOCKED)
Map provider OHLCV into internal canonical bars (see EOD_Bars_Contract).

## Audit recording (LOCKED)
- create/update eod_runs per stage
- each eod_bars row records source, ingested_at, run_id