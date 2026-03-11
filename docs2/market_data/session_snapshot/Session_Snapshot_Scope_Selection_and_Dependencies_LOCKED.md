# Session Snapshot Scope Selection and Dependencies (LOCKED)

## Purpose
Keep optional same-day snapshot capture feasible without depending on consumer policy outputs.

## Scope options
### A) Eligibility-set snapshots (default)
- snapshot tickers from `eod_eligibility(D)` where `eligible=1`
- does not require any picks/ranking interface
- bounded and upstream-owned

### B) Manual-scope snapshots (optional later)
- snapshot only a narrower manually supplied ticker list
- requires a stable, documented local input format
- market-data must not invent that list

## Locked rule
Until a stable manual-scope interface exists, Market Data Platform must implement option A.

## Alignment rule
All session snapshots must use `trade_date = trade_date_effective D`.