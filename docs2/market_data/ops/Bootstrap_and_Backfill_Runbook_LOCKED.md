# Bootstrap and Backfill Runbook (LOCKED)

## Purpose
Ensure Market Data Platform has enough historical data to produce stable upstream indicators and eligibility without warmup-driven distortion.

## Minimum bootstrap target (LOCKED)
To support the baseline indicators:
- minimum history per ticker: >= 60 trading days
Recommended for production stability and replay confidence:
- >= 3 years of trading days

## Steps
### Step 0 — validate global dependencies
- ticker master exists and is correct
- market calendar exists and trading-day order is correct

### Step 1 — bars backfill
- ingest canonical bars by date range
- validate coverage and invalid-bar patterns

### Step 2 — indicators backfill
- compute indicators with explicit `indicator_set_version`
- respect trading-day windows and null policy

### Step 3 — eligibility build
- build one eligibility row per ticker/date
- warmup gaps become `eligible=0` with explicit reason code

### Step 4 — historical replay
- verify deterministic hashes on stable inputs
- verify effective-date and seal behavior on degraded scenarios

## Resume requirement (LOCKED)
- backfill is resumable by date range
- rerun is idempotent

## Done criteria
- recent sample dates meet coverage threshold
- eligibility population is no longer dominated by insufficient history
- downstream consumer read path can resolve and read D without missing upstream artifacts
