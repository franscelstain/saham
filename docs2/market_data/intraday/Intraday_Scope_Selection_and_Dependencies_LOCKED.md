# Intraday Scope Selection and Dependencies (LOCKED)

## Purpose
Keep intraday snapshot feasible without depending on downstream policy outputs.

## Scope options
### A) Eligibility-set snapshots (default)
- snapshot tickers from `eod_eligibility(D)` where `eligible=1`
- does not require any downstream picks/ranking interface
- bounded and upstream-owned

### B) External-scope snapshots (optional later)
- snapshot only a narrower externally supplied ticker list
- requires a stable, documented interface from the requesting downstream system
- market-data must not invent that list

## Locked rule
Until a stable external scope interface exists, Market Data Platform must implement option A.

## Alignment rule
All intraday snapshots must use `trade_date = trade_date_effective D`.
