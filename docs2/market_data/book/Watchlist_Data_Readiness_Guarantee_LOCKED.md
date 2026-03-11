# Watchlist Data Readiness Guarantee (LOCKED)

This document is retained only to state an example downstream dependency. It does not introduce watchlist scoring/grouping logic.

## Downstream-safe conditions
For effective trade date D, Market Data Platform guarantees safe consumption only if:
1) `eod_runs` has a finalized sealed `SUCCESS` run that resolves D
2) canonical bars exist for D
3) indicator rows exist for D with `is_valid` and `indicator_set_version`
4) eligibility snapshot exists for D
5) audit hashes exist for D
6) dataset is sealed for D

## What downstream consumers must never do
- infer D via raw tables
- compute indicators from bars
- use unsealed datasets
- treat missing eligibility rows as implicit exclusion logic

================================================================================
docs/market_data/db/Database_Schema_Contracts_MariaDB.md
================================================================================
# Database Schema Contracts (MariaDB)

## Required tables
- `eod_runs`
- `eod_bars`
- `eod_invalid_bars`
- `eod_indicators`
- `eod_eligibility`
- `eod_reason_codes`
- `eod_run_events`

## Optional tables
- `intraday_snapshots`
- `eod_fetch_failures`
- `md_replay_daily_metrics`

## Required schema capabilities (LOCKED)
- canonical bars and invalid bars are stored separately
- run record stores hashes and seal metadata
- run-event logging supports auditable stage/event trail
- eligibility stores one row per coverage-universe ticker/date
- schema supports deterministic replay and downstream read safety