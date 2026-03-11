# Canonicalization Contract (EOD Bars)

## Purpose
Define what canonical EOD bars mean and how provider data becomes the single source of truth.

## Canonical dataset (LOCKED)
eod_bars(trade_date, ticker_id, open, high, low, close, volume, [adj_close], source, ingested_at, run_id)

Consumers must treat eod_bars as the only allowed EOD OHLCV input.

## Pipeline stages (LOCKED)
1) Acquire provider bars via API
2) Normalize/map + unit normalization
3) Validate (per EOD_Bars_Contract)
4) Publish via idempotent upsert into eod_bars
5) Gate and declare readiness (via eod_runs, effective date contract)

## Idempotency (LOCKED)
- rerun for same date must not duplicate
- historical correction allowed only with new run_id + notes + recompute indicators + new hashes