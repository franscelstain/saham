# Canonicalization Contract (EOD Bars)

## Purpose
Define how provider bars become canonical internal bars and how rejected rows are handled.

## Canonical dataset (LOCKED)
Canonical EOD OHLCV is stored in:
- `eod_bars(trade_date, ticker_id, open, high, low, close, volume, adj_close, source, ingested_at, run_id)`

Rejected provider rows are stored only for audit in:
- `eod_invalid_bars(trade_date, ticker_id, source, source_row_ref, invalid_reason_code, observed_open, observed_high, observed_low, observed_close, observed_volume, observed_adj_close, run_id, recorded_at)`

Consumers must treat `eod_bars` as the only allowed canonical OHLCV source.
Consumers must never read `eod_invalid_bars` as market data input.

## Pipeline stages (LOCKED)
1) Acquire raw provider bars for requested date T.
2) Map provider identifiers to `ticker_id`.
3) Normalize units, number types, and timestamps.
4) Resolve provider duplicates for the same `(trade_date, ticker_id)` using the deterministic duplicate rule below.
5) Validate each row against `EOD_Bars_Contract`.
6) Publish valid bars into `eod_bars` via idempotent upsert.
7) Publish invalid rows into `eod_invalid_bars` for audit.
8) Compute readiness via run gates, effective date, hashes, and seal.

## Deterministic duplicate-provider rule (LOCKED)
If the provider delivers multiple candidate rows for the same `(trade_date, ticker_id)` within one run, the pipeline must choose exactly one canonical candidate before validation using a deterministic precedence chain:
1) latest provider payload timestamp if the provider exposes it
2) otherwise latest acquisition timestamp recorded by the platform
3) if still tied, lexical maximum of `source_row_ref`

All non-selected duplicates must be recorded as non-canonical audit rows with a reason code such as `BAR_DUPLICATE_SUPERSEDED` and must never reach `eod_bars`.

## Idempotency (LOCKED)
- Rerun for the same requested date must not duplicate canonical PKs.
- Rejected rows may be replaced for the same `run_id` if the rerun is within the same in-progress execution.
- Controlled historical correction for an already finalized date requires:
  - new `run_id`
  - new hashes
  - indicator recompute
  - reseal
  - preserved audit trail for the superseded run