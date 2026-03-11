# Bootstrap and Backfill Runbook (LOCKED)

## Purpose
Ensure Market Data Platform produces enough historical EOD bars + indicators so Watchlist does not see:
- ELIG_INSUFFICIENT_HISTORY everywhere
- unstable coverage and incomplete indicators

## Minimum bootstrap target (LOCKED)
To support Weekly Swing baseline indicators (dv20, atr14, roc20, hh20, vol_ratio):
- minimum history required per ticker: >= 60 trading days
Recommended for real usage (calibration + stability):
- >= 3 years trading days

## Steps (LOCKED)

### Step 0 — Validate global dependencies
- tickers master exists and active tickers are correct
- market calendar exists and trading-day order is correct

### Step 1 — Bars backfill (month batches)
Run month-by-month:
- ingest canonical bars for the date range
- validate coverage and invalid patterns

Expected output:
- eod_bars populated for the range
- eod_runs recorded per run

### Step 2 — Indicators backfill (same ranges)
Compute indicators for the same date range:
- must set indicator_set_version explicitly
- must respect trading-day windows and null policy

Expected output:
- eod_indicators populated
- early dates may be NULL until warmup satisfied

### Step 3 — Eligibility build for each effective date
- build eod_eligibility after indicators for each date
- tickers without warmup => eligible=0 ELIG_INSUFFICIENT_HISTORY

### Step 4 — Replay (data-quality backtest)
Run historical replay (minimum 60 trading days sample):
- verify determinism hashes are stable for SUCCESS days
- verify coverage_ratio distribution is sane

## Resume requirement (LOCKED)
Backfill must be resumable:
- checkpoint per month
- rerun must be idempotent

## When bootstrap is considered DONE
- last 60 trading days: SUCCESS rate high, coverage meets threshold
- eligible tickers count is stable and not dominated by insufficient history
- watchlist consumer read model can load D without missing tables