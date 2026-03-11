# Canonicalization Contract (EOD Bars)

## Purpose
Define how provider bars become canonical internal bars and how rejected rows are handled.

## Canonical dataset (LOCKED)
Canonical EOD OHLCV is stored in:
- `eod_bars(trade_date, ticker_id, open, high, low, close, volume, adj_close, source, ingested_at, run_id)`

Rejected provider rows are stored only for audit in:
- `eod_invalid_bars(trade_date, ticker_id, source, provider_payload_ref, invalid_reason_code, observed_open, observed_high, observed_low, observed_close, observed_volume, observed_adj_close, run_id, recorded_at)`

Consumers must treat `eod_bars` as the only allowed canonical OHLCV source.
Consumers must never read `eod_invalid_bars` as market data input.

## Pipeline stages (LOCKED)
1) Acquire raw provider bars for requested date T.
2) Map provider identifiers to `ticker_id`.
3) Normalize units, number types, and timestamps.
4) Validate each row against `EOD_Bars_Contract`.
5) Publish valid bars into `eod_bars` via idempotent upsert.
6) Publish invalid rows into `eod_invalid_bars` for audit.
7) Compute readiness via run gates, effective date, hashes, and seal.

## Idempotency (LOCKED)
- Rerun for the same requested date must not duplicate canonical PKs.
- Rejected rows may be replaced for the same `run_id` if the rerun is within the same in-progress execution.
- Controlled historical correction for an already finalized date requires:
  - new `run_id`
  - new hashes
  - indicator recompute
  - reseal
  - preserved audit trail for the superseded run