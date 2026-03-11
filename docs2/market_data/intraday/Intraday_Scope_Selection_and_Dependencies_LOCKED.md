# Intraday Scope Selection and Dependencies (LOCKED)

## Purpose
Make intraday snapshot implementation feasible without depending on incomplete Watchlist features.

## Scope options (LOCKED)
A) Eligibility-set snapshots (recommended first)
- snapshot tickers from eod_eligibility(D) where eligible=1
- does NOT require Watchlist picks output
- higher load than picks-only, but still bounded (typically 100–300)

B) Picks-only snapshots (later optimization)
- snapshot only Watchlist picks list for the day
- REQUIRES Watchlist module to provide "picks list" as an input
- market_data must not invent picks

## LOCKED rule
Until Watchlist provides a stable picks list interface:
- market_data must implement Eligibility-set snapshots (Option A).

Once picks interface exists:
- Option B may be enabled as config switch.

## Alignment rule
All intraday snapshots must tag trade_date = trade_date_effective D.