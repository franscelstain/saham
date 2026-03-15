# Audit Query Cookbook (LOCKED)

## Purpose
Provide standard query patterns or query intents for retrieving audit evidence from the schema without ad hoc guesswork.

The exact SQL may vary by implementation details, but the questions answered here are mandatory for audit-grade usability.

## Query 1 — Current publication for one trade date D
### Goal
Resolve the one current publication for D.

### Query intent
- resolve `eod_current_publication_pointer` by `trade_date = D`
- join the pointed `publication_id` to `eod_publications`
- validate `trade_date`, `seal_state`, and mirror current-state columns
- use `eod_publications.is_current = 1` only as a supporting consistency check, not as the primary source of truth

### Canonical audit join path
1. `eod_current_publication_pointer.trade_date = D`
2. `eod_current_publication_pointer.publication_id = eod_publications.publication_id`
3. `eod_publications.run_id = eod_runs.run_id`

### Must answer
- publication_id
- run_id
- publication_version
- seal_state
- sealed_at

## Query 2 — Prior superseded publications for one trade date D
### Goal
List historical publications for D that are no longer current.

### Query intent
- resolve the current publication through `eod_current_publication_pointer`
- list `eod_publications` for `trade_date = D` where the row is not the current pointed publication
- sort by `publication_version`

### Must answer
- publication history chain
- which one was superseded by which

## Query 3 — Run summary for one run_id
### Goal
Retrieve the authoritative run state.

### Query intent
- filter `eod_runs` by `run_id`

### Must answer
- lifecycle_state
- terminal_status
- quality_gate_state
- publishability_state
- stage
- requested/effective date
- hashes
- config identity

## Query 4 — Event trail for one run_id
### Goal
Retrieve append-only operational history.

### Query intent
- filter `eod_run_events` by `run_id`
- sort by `event_time`

### Must answer
- stage chronology
- error/warn progression
- finalization trail

## Query 5 — Invalid bar evidence for one requested date
### Goal
Inspect rejected source rows.

### Query intent
- filter `eod_invalid_bars` by `trade_date`
- optionally filter by `run_id` or `ticker_id`

### Must answer
- invalid reason code distribution
- source row traceability
- duplicate-loser evidence

## Query 6 — Eligibility blocking counts by reason for one date
### Goal
Summarize readability blockers.

### Query intent
- filter `eod_eligibility` by `trade_date`
- group by `reason_code`

### Must answer
- blocked counts by reason
- readable vs blocked universe size

## Query 7 — Correction before/after proof for one correction_id
### Goal
Explain one correction event.

### Query intent
- resolve `eod_dataset_corrections`
- join prior/new run and publication evidence

### Must answer
- prior run/publication
- new run/publication
- approval metadata
- publication switch result

## Query 8 — Old/new hash comparison for corrected date D
### Goal
Compare prior and corrected publication manifests.

### Query intent
- resolve the current publication through `eod_current_publication_pointer`
- resolve superseded publications for D from `eod_publications`
- compare bars/indicators/eligibility hashes

### Must answer
- old hash set
- new hash set
- which publication is current now

## Query 9 — Replay result for one replayed date
### Goal
Explain replay result and mismatch scope.

### Query intent
- filter `md_replay_daily_metrics` by `(replay_id, trade_date)`

### Must answer
- comparison_result
- comparison_note
- artifact_changed_scope
- config_identity
- expected vs actual outcome summary

## Query 10 — Replay reason-code distribution
### Goal
Inspect replay anomaly or blocking distribution.

### Query intent
- filter `md_replay_reason_code_counts` by `replay_id` and `trade_date`

### Must answer
- reason code counts
- whether mismatch is concentrated in one layer

## Query 11 — Publication-bound row history
### Goal
Retrieve exact rows for a historical publication.

### Query intent
- resolve the target `publication_id` first
- filter `*_history` tables by that `publication_id`

### Must answer
- exact historical row set for that publication

## Query 12 — Config identity used by a readable publication
### Goal
Prove which config snapshot produced the current publication.

### Query intent
- resolve the current publication through `eod_current_publication_pointer`
- validate the pointed publication row
- join to `eod_runs`
- read config identity fields

### Must answer
- config_version
- config_hash
- config_snapshot_ref or equivalent identity

## Usage rule (LOCKED)
An audit-grade implementation must be able to answer all the questions above quickly and deterministically.

## Cross-contract alignment
This cookbook must remain aligned with:
- `Audit_Evidence_Pack_Contract_LOCKED.md`
- `Run_Artifacts_Format_LOCKED.md`
- `../db/Database_Schema_Contracts_MariaDB.md`
- correction/publication contracts
- replay contracts

## Anti-ambiguity rule (LOCKED)
If an operator cannot answer the audit questions above without inventing undocumented joins or inference logic, the audit usability layer is incomplete.
## Build-safety rule (LOCKED)
This cookbook must teach the same join path that the implementation actually uses.
If a simpler-looking query bypasses the pointer owner, bypasses publication validation, or bypasses the one-publication context, that query must not be published here as a normal audit pattern.
