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
- schema supports controlled correction by preserving run-level audit history instead of silently mutating sealed output semantics