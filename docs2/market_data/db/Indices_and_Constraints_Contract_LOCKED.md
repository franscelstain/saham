# Indices and Constraints Contract (LOCKED)

## Purpose
Define the minimum index, uniqueness, and integrity expectations required so Market Data Platform remains deterministic, queryable, and safe for publication resolution.

This document complements:
- `Database_Schema_MariaDB.sql`
- `Database_Schema_Contracts_MariaDB.md`

## Required unique-key semantics (LOCKED)

### Canonical bars
Must enforce:
- one row per `(trade_date, ticker_id)`

### Indicators
Must enforce:
- one row per `(trade_date, ticker_id)`

### Eligibility
Must enforce:
- one row per `(trade_date, ticker_id)`

### Replay reason-code counts
Must enforce:
- one row per `(replay_id, trade_date, reason_code)`

### Publication versioning
If explicit publication table is used, must enforce:
- one row per `(trade_date, publication_version)`

## Required supporting indexes

### Bars
Recommended:
- `(ticker_id, trade_date)`
- `(run_id)`

### Indicators
Recommended:
- `(ticker_id, trade_date)`
- `(run_id)`
- `(invalid_reason_code)`

### Eligibility
Recommended:
- `(ticker_id, trade_date)`
- `(run_id)`
- `(reason_code)`

### Runs
Recommended:
- `(trade_date_requested, lifecycle_state)`
- `(trade_date_requested, terminal_status)`
- `(trade_date_effective, terminal_status)`
- `(trade_date_effective, publishability_state)`
- `(quality_gate_state)`
- `(stage)`
- `(trade_date_effective, is_current_publication)`
- `(supersedes_run_id)`

### Publications
Recommended:
- `(trade_date, is_current)`
- `(run_id)`
- `(supersedes_publication_id)`

### Corrections
Recommended:
- `(trade_date, status)`
- `(prior_run_id)`
- `(new_run_id)`

## Integrity semantics that may require application enforcement
Some invariants are logically mandatory even if MariaDB cannot express them as a partial unique constraint.

These include:
- exactly one current publication per trade date
- no ambiguous publication switch
- no simultaneous old/new current publication after correction
- no publish-critical mixed-run artifact resolution

## Foreign-key guidance
Foreign keys are recommended where operationally safe, for example:
- publication -> run
- correction.prior_run_id -> run
- correction.new_run_id -> run
- indicator.invalid_reason_code -> reason-code registry (if registry design supports it)
- eligibility.reason_code -> reason-code registry (if registry design supports it)

If hard FK constraints are not used for operational reasons, semantic linkage must still be enforced by application/service logic.

## Queryability rule (LOCKED)
Indexes must support at minimum:
- consumer-readable publication resolution
- replay result lookup
- correction trail inspection
- run/event audit investigation
- per-date artifact validation

## Anti-ambiguity rule (LOCKED)
Lack of a direct partial unique index does not weaken the contract.
The invariant must still be enforced deterministically by transaction or procedure discipline.