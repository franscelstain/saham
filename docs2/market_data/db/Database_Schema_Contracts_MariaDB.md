# Database Schema Contracts (MariaDB)

## Purpose
Define the minimum MariaDB schema semantics required to implement Market Data Platform contracts safely, deterministically, and auditably.

This document is normative.
It complements the concrete DDL in `Database_Schema_MariaDB.sql`.

## Core schema goals
The schema must support:
- canonical EOD bars
- invalid/rejected source-row audit evidence
- deterministic indicator storage
- explicit eligibility snapshot
- separated run-state semantics
- append-only event trail
- hash and seal evidence
- current publication resolution
- historical correction trail
- replay/result evidence
- auditable registry linkage
- explicit row-history strategy

## Required core tables
Minimum required schema support must exist for concepts equivalent to:
- `eod_bars`
- `eod_invalid_bars`
- `eod_indicators`
- `eod_eligibility`
- `eod_runs`
- `eod_run_events`
- `eod_publications`
- reason-code registry table

Equivalent naming is allowed only if semantics remain identical.

## Required table semantics

### 1. Canonical bars
Must support:
- exactly one current canonical row per `(trade_date, ticker_id)`
- deterministic canonical winner selection
- readable-state linkage to run/publication context

### 2. Invalid bars
Must support:
- rejected source-row evidence
- invalid reason code
- source row traceability
- duplicate-loser preservation when needed
- run/date linkage

### 3. Indicators
Must support:
- exactly one current row per `(trade_date, ticker_id)`
- explicit validity state
- invalid reason code
- indicator-set version identity
- readable-state linkage to run/publication context

### 4. Eligibility
Must support:
- exactly one current row per `(trade_date, ticker_id)`
- explicit `eligible` state
- explicit blocking reason code
- readable-state linkage to run/publication context

### 5. Runs
Must support, at minimum:
- requested trade date
- effective trade date
- lifecycle state
- terminal status
- quality gate state
- publishability state
- stage
- counts and telemetry
- hash fields
- seal metadata
- config identity
- correction/publication linkage

### 6. Run events
Must support:
- append-only event trail
- stage/event traceability
- severity
- optional reason-code linkage
- structured payload detail
- run/date linkage

### 7. Publications
Must support:
- current publication for one trade date
- superseded publication history
- publication version
- explicit readable-vs-audit-only distinction

## Required uniqueness and integrity constraints (LOCKED)

### Required uniqueness
- `eod_bars`: exactly one current row per `(trade_date, ticker_id)`
- `eod_indicators`: exactly one current row per `(trade_date, ticker_id)`
- `eod_eligibility`: exactly one current row per `(trade_date, ticker_id)`
- `md_replay_reason_code_counts`: one row per `(replay_id, trade_date, reason_code)`
- `eod_publications`: one row per `(trade_date, publication_version)`

### Required integrity semantics
- one coherent publication context must back one readable state
- one trade date must resolve to at most one current publication
- prior superseded publication must remain auditable
- invalid bars must never leak into canonical readable bars
- run events must remain append-only

## Run-state model requirement (LOCKED)
The schema must distinguish, semantically and preferably physically, at minimum:

### A. Lifecycle state
Execution progression state, for example:
- `PENDING`
- `RUNNING`
- `FINALIZING`
- `COMPLETED`
- `FAILED`
- `CANCELLED`

### B. Terminal status
Consumer-facing terminal outcome:
- `SUCCESS`
- `HELD`
- `FAILED`

### C. Quality gate state
Gate evaluation state:
- `PENDING`
- `PASS`
- `FAIL`
- `BLOCKED`

### D. Publishability state
Readability state:
- `NOT_READABLE`
- `READABLE`

These meanings must remain distinct.
A single overloaded `status` column is not sufficient for strong contract closure.

## Required schema support for effective-date publication
The schema must support a consumer-readable publication model where:
- one trade date D may have multiple historical publications
- only one publication may be current
- only the current sealed publication is consumer-readable
- superseded publications remain audit-only

## Required schema support for historical correction integrity
The schema must be able to represent:
- prior current publication
- new correction run
- approval metadata
- old/new hash trails
- publication switch result
- supersession relation

## Required schema support for explicit row-history strategy
The schema must support one of the following clearly documented strategies:

### Strategy A — Immutable publication-bound history tables
Recommended.
Use tables such as:
- `eod_bars_history`
- `eod_indicators_history`
- `eod_eligibility_history`

These preserve exact row sets per publication.

### Strategy B — Publication + hash + correction evidence only
Allowed for simpler deployments, but only if explicitly documented as the chosen history strategy.

If Strategy B is chosen:
- contracts must explicitly state that row-level historical audit is derived from publication trail + hash trail + correction evidence
- the implementation must not imply richer row-history than it actually stores

## Required replay-proof schema support
Replay storage must be able to represent:
- requested trade date
- effective trade date
- terminal status
- comparison result
- comparison note
- artifact-changed scope
- config identity
- publication version where relevant
- seal state
- mismatch summary
- reason-code counts

## Application-enforced integrity where MariaDB cannot express partial uniqueness
Some invariants may require application transaction discipline or locked procedure flow, including:
- exactly one current publication per trade date
- no ambiguous publication switch
- no dual-current publication state
- no mixed current/superseded read state

If MariaDB cannot express the invariant directly, the implementation must still enforce it deterministically.

## Severity model distinction
Two severity layers may exist:

### Reason-code severity
Registry classification such as:
- `INFO`
- `WARN`
- `HARD`

### Event severity
Run-event log severity such as:
- `INFO`
- `WARN`
- `ERROR`

These do not need identical enums, but the distinction must remain explicit.

## Cross-contract alignment
This schema contract must remain aligned with:
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `Determinism_Invariants_LOCKED.md`
- `Canonical_Row_History_and_Versioning_Policy_LOCKED.md`
- `Indices_and_Constraints_Contract_LOCKED.md`

## Anti-ambiguity rule (LOCKED)
If a required audit artifact, invalid-row evidence, run-state dimension, or row-history strategy is described as mandatory in contracts but not represented or explicitly chosen in schema design, then the schema is incomplete and not contract-consistent.