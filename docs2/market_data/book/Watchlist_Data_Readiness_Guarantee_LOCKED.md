# Watchlist Data Readiness Guarantee (LOCKED)

## Purpose
Define the exact conditions under which Market Data Platform guarantees Watchlist can consume data safely.

## Watchlist-ready conditions (LOCKED)
For an effective trade date D:
1) eod_runs has a finalized SUCCESS run for requested day OR selected fallback effective date D
2) eod_bars contains rows for D (canonical bars)
3) eod_indicators contains rows for D with is_valid and indicator_set_version set
4) eod_eligibility exists for D
5) audit hashes exist for D
6) dataset is SEALED for D

## What Watchlist must read
- trade_date_effective D from eod_runs
- eligible=1 tickers from eod_eligibility(D)
- indicators from eod_indicators(D)
- optional bars from eod_bars(D)

## What Watchlist must never do
- infer D by max(trade_date)
- compute indicators from bars
- include eligible=0 tickers
- use unsealed datasets