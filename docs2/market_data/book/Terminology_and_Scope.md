# Terminology and Scope

## Scope
This module covers:
- Acquiring EOD OHLCV from a provider (API).
- Normalizing/canonicalizing into internal schema.
- Computing EOD indicators from EOD bars.
- Quality gates + declaring an effective trade date for consumers.
- Publishing a daily eligibility snapshot for consumers.

This module does NOT cover:
- Watchlist scoring/grouping (policy logic).
- Intraday streaming (intraday snapshot is event-based, best-effort).
- Master data for tickers and market calendar (assumed managed elsewhere).

## Key terms
- Trading Day: exchange trading date.
- EOD Bar: OHLCV for one ticker on one Trading Day.
- EOD Indicator: daily derived feature computed from EOD bars using Trading Day windows.
- Run: one pipeline execution for a target date or date range (backfill).
- Quality Gate: pass/fail rules declaring whether data is safe to consume.
- Effective Trade Date: the official EOD date that consumers must use (may fallback).
- Eligibility Snapshot: daily list of tickers eligible for consumption.

## Ticker identity
- ticker_id: integer primary key in tickers.
- ticker_code: unique exchange code like BBCA.

## Design principles (LOCKED)
1) Deterministic: same inputs => same outputs.
2) Auditable: every output traceable to run_id, row counts, reason codes, hashes.
3) Fail-safe: if data is not ready, consumers must not consume partial data.
4) Separation of concerns: consumers do not compute indicators from bars.