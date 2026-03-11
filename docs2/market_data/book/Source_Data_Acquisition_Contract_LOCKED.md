# Source Data Acquisition Contract (API or Manual)

## Purpose
Lock acquisition behavior so outputs stay deterministic, auditable, and easy to implement whether data comes from public/free APIs or manual input.

## Source adapter (LOCKED)
Implementation must normalize every source through one adapter contract:
- `fetchOrLoadEodBars(trade_date, ticker_codes[]) -> source_bars[]`

Allowed source modes:
- public/free API pull
- local CSV/JSON import
- manual entry/import prepared in a controlled format

The downstream canonicalization contract must not care which source mode produced the row once it has been normalized.

## Minimum normalized source fields (LOCKED)
Every normalized source row must provide:
- `ticker_code`
- `trade_date` (`YYYY-MM-DD`)
- `open`
- `high`
- `low`
- `close`
- `volume`
- `adj_close` (nullable)
- `source_name`
- `source_row_ref` (nullable but recommended)
- `captured_at`

## Request/load rules (LOCKED)
- bounded concurrency for API mode
- throttle + jitter between requests when API mode is used
- retry with backoff on transient API errors when API mode is used
- manual-file mode must validate schema before rows are accepted
- every acquisition stage must record source mode and source name in run telemetry

## Failure classification (LOCKED)
- transient API error: retry, then `HELD` if unresolved
- source format change / parsing failure: `FAILED`
- manual file schema mismatch: `FAILED`
- partial source coverage: allowed only if coverage >= `COVERAGE_MIN`, else `HELD`

## Parsing + mapping (LOCKED)
Map normalized source OHLCV into internal canonical bars as defined by `EOD_Bars_Contract.md`.

## Audit recording (LOCKED)
- create/update `eod_runs` per stage
- each `eod_bars` row records `source`, `ingested_at`, and `run_id`
- source mode used for a run must be visible in logs or run notes