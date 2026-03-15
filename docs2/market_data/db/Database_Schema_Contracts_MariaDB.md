# Database Schema Contracts (MariaDB, LOCKED)

## Purpose
Define the minimum persistence contract required for the Market Data Platform in MariaDB so the schema supports:
- canonical readable-state storage
- invalid-row evidence
- publication-aware consumer reads
- sealed publication switching
- correction-safe auditability
- deterministic replay support
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
- `eod_current_publication_pointer` when the hardened current-publication model is used
- reason-code registry table

Equivalent naming is allowed only if semantics remain identical.

## Required table semantics

### 1. Canonical bars
Must support:
- exactly one current readable row per `(trade_date, ticker_id)`
- deterministic canonical winner selection
- mandatory non-null `publication_id` publication context on every live current row
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
- exactly one current readable row per `(trade_date, ticker_id)`
- explicit validity state
- invalid reason code
- indicator-set version identity
- mandatory non-null `publication_id` publication context on every live current row
- readable-state linkage to run/publication context

### 4. Eligibility
Must support:
- exactly one current readable row per `(trade_date, ticker_id)`
- explicit `eligible` state
- explicit blocking reason code
- mandatory non-null `publication_id` publication context on every live current row
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

### 8. Current-publication pointer
When the hardened pointer model is adopted, schema must support:
- exactly one pointer row per readable trade date
- one pointed publication per trade date
- publication-to-trade-date consistency
- transactional alignment with publication switch flow

## Live current tables vs history tables (LOCKED)
The schema must keep a clean distinction between:

### A. Live current readable tables
- `eod_bars`
- `eod_indicators`
- `eod_eligibility`

These store one current readable row per `(trade_date, ticker_id)`.
In these live current tables:
- `publication_id` is mandatory publication context
- `publication_id` is not the live-table primary key
- superseded publication row sets must not remain side-by-side with the current readable row set

### B. Historical publication-bound row sets
When retained, these belong in:
- `eod_bars_history`
- `eod_indicators_history`
- `eod_eligibility_history`
- or equivalent immutable publication-bound storage

History tables may use `(publication_id, trade_date, ticker_id)` as the row identity.
That is distinct from the live current-table identity.

## Required uniqueness and integrity constraints (LOCKED)

### Required uniqueness
- `eod_bars`: exactly one current readable row per `(trade_date, ticker_id)`
- `eod_indicators`: exactly one current readable row per `(trade_date, ticker_id)`
- `eod_eligibility`: exactly one current readable row per `(trade_date, ticker_id)`
- `md_replay_reason_code_counts`: one row per `(replay_id, trade_date, reason_code)`
- `eod_publications`: one row per `(trade_date, publication_version)`

### Required integrity semantics
- one coherent publication context must back one readable state
- one trade date must resolve to at most one current publication
- where the hardened pointer model is used, current-publication resolution must prefer `eod_current_publication_pointer`
- live current rows in `eod_bars`, `eod_indicators`, and `eod_eligibility` must carry non-null `publication_id`
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
Allowed only for explicitly documented non-production, legacy, or intentionally weaker deployments.

Strategy B must not be presented as equal in audit strength to Strategy A and must not be described as the default production-grade strategy.

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
- `../book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `../book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `../book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `../book/Determinism_Invariants_LOCKED.md`
- `../book/Canonical_Row_History_and_Versioning_Policy_LOCKED.md`
- `../book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`
- `Indices_and_Constraints_Contract_LOCKED.md`

For current-publication resolution behavior, the book-level pointer contract remains the sole behavioral owner. Schema notes and DDL may enforce that contract, but must not soften it.

## Anti-ambiguity rule (LOCKED)
If a required audit artifact, invalid-row evidence, run-state dimension, publication-context rule, or row-history strategy is described as mandatory in contracts but not represented or explicitly chosen in schema design, then the schema is incomplete and not contract-consistent.


**Publication-context integrity rule for live current tables.**
For `eod_bars`, `eod_indicators`, and `eod_eligibility`, `publication_id` is a mandatory publication-context field used to bind the current readable rows to the resolved current publication. This field is required as an integrity anchor for downstream reads and operational verification. It must not be interpreted as permitting side-by-side storage of multiple publication versions in the live current tables for the same `(trade_date, ticker_id)` key. Live current tables retain a single current-state row per `(trade_date, ticker_id)`; publication-versioned history belongs in the publication trail and `*_history` tables.
