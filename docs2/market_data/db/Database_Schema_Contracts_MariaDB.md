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
- `session_snapshots`
- `eod_fetch_failures`
- `md_replay_daily_metrics`

## Required schema capabilities (LOCKED)
- canonical bars and invalid bars are stored separately
- run record stores hashes and seal metadata
- run-event logging supports auditable stage/event trail
- eligibility stores one row per coverage-universe ticker/date
- schema supports deterministic replay and downstream read safety
- schema supports controlled correction by preserving run-level audit history instead of silently mutating sealed output semantics

## Required schema support for historical correction integrity (LOCKED)

The schema must support all semantics required by:
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`

At minimum, the storage model must be able to represent:
- historical run identity
- current published sealed state for one trade date D
- superseded prior publication state for D
- correction request metadata
- approval trail
- correction execution linkage
- old/new hash trail
- publication switch without silent overwrite

Allowed implementation patterns:
1. fields on `eod_runs` such as:
   - `supersedes_run_id`
   - `publication_version`
   - `is_current_publication`
2. separate publication table
3. correction request table + publication table + run linkage

The contract does not force one exact schema pattern, but all semantics above are mandatory.

## Required replay-proof schema support (LOCKED)

Replay result storage must be able to represent:
- requested trade date
- effective trade date
- terminal status
- comparison result
- seal state
- expected-vs-actual mismatch summary
- reason-code counts

If replay storage is split across multiple tables, the overall semantics must still remain queryable without guessing.

## Optional tables
Examples of optional but supported tables include:
- `md_replay_daily_metrics`
- `md_replay_reason_code_counts`
- correction request / publication history tables if correction lifecycle is implemented separately from `eod_runs`