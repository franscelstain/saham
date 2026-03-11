# Database Schema Contracts (MariaDB)

## Purpose
Define the minimum MariaDB schema semantics required to implement Market Data Platform contracts safely and deterministically.

This document describes schema intent and required semantics.
It complements the concrete DDL in `Database_Schema_MariaDB.sql`.

## Core schema goals
The schema must support:
- canonical EOD bars
- deterministic indicator storage
- explicit eligibility snapshot
- run-level terminal status
- append-only event trail
- hash and seal evidence
- effective-date publication semantics
- historical correction trail
- replay/result evidence
- auditable registry linkage

## Required core tables
Minimum required schema support must exist for concepts equivalent to:
- `eod_bars`
- `eod_invalid_bars`
- `eod_indicators`
- `eod_eligibility`
- `eod_runs`
- `eod_run_events`
- reason-code registry table

Equivalent naming is allowed if semantics remain identical.

## Required table semantics

### 1. Canonical bars
Must support:
- one canonical row per `(trade_date, ticker_id)`
- deterministic storage of canonical OHLCV fields
- source identity for canonical winner row
- prevention of ambiguous duplicates

### 2. Invalid bars
Must support:
- rejected row evidence
- invalid reason code
- source row reference or equivalent traceability
- association to relevant run/date

### 3. Indicators
Must support:
- one indicator row per `(trade_date, ticker_id)`
- explicit validity state
- invalid reason code when invalid
- indicator-set version identity

### 4. Eligibility
Must support:
- one row per coverage-universe ticker/date
- explicit `eligible` state
- explicit blocking `reason_code`
- deterministic downstream-readable readiness artifact

### 5. Runs
Must support:
- requested trade date
- effective trade date
- terminal status
- stage identity
- counts and gate-related telemetry
- hash fields
- seal metadata
- config identity linkage
- current publication semantics for corrected history if implemented in run table

### 6. Run events
Must support:
- append-only event trail
- stage/event traceability
- event severity
- optional reason-code linkage
- run association

### 7. Reason-code registry
Must support:
- stable code identity
- category
- description
- severity/classification
- active/inactive state

## Required uniqueness and integrity constraints (LOCKED)

### Required uniqueness
- `eod_bars`: exactly one row per `(trade_date, ticker_id)`
- `eod_indicators`: exactly one row per `(trade_date, ticker_id)`
- `eod_eligibility`: exactly one row per `(trade_date, ticker_id)`
- replay reason-code count: one row per `(replay_id, trade_date, reason_code)`
- correction request identity: unique correction primary key and deterministic run linkage

### Required integrity semantics
- one coherent run context must back one sealed readable publication
- corrected publication must explicitly supersede prior publication
- superseded publication must remain queryable
- consumer-readable publication resolution must not depend on timestamp guessing

## Required schema support for effective-date publication
The schema must support a consumer-readable publication model where:
- one effective dataset publication is readable for one date D
- consumers can resolve the current readable publication safely
- consumers do not need to guess using latest timestamps or max dates

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

## Required schema support for determinism and reproducibility
The schema must preserve enough data to prove:
- which artifact set was hashed
- which run produced the publication
- which config identity was used
- which seal was current
- which historical publication was superseded by correction

## Required replay-proof schema support (LOCKED)
Replay result storage must be able to represent:
- requested trade date
- effective trade date
- terminal status
- comparison result
- comparison note
- seal state
- expected-vs-actual mismatch summary
- reason-code counts
- config identity
- publication version if replay is correction-aware

If replay storage is split across multiple tables, the overall semantics must still remain queryable without guessing.

## Application-enforced integrity where MariaDB cannot express partial uniqueness
Some invariants may require application transaction discipline or locked stored-procedure flow, for example:
- exactly one current publication per trade date
- no ambiguous publication switch
- no dual-current corrected publication state
- no mixed old/new publication exposure during switch

If MariaDB cannot express the invariant directly as a partial unique index, the implementation must still enforce it deterministically.

## Severity model distinction
Two different severity layers may exist:

### Reason-code severity
Registry-level classification such as:
- `INFO`
- `WARN`
- `HARD`

This classifies the semantic seriousness of the code itself.

### Event severity
Run-event log severity such as:
- `INFO`
- `WARN`
- `ERROR`

This classifies the event/log occurrence in run execution.

These two layers do not need identical enums, but the distinction must remain documented and intentional.

## Optional tables
Examples of optional but supported tables include:
- `md_replay_daily_metrics`
- `md_replay_reason_code_counts`
- per-ticker optional fetch-failure table
- correction request / publication history tables if correction lifecycle is implemented separately from `eod_runs`
- explicit `eod_publications` table if publication semantics are separated from `eod_runs`

## Anti-ambiguity rule (LOCKED)
The schema must be rich enough that:
- consumer-readable state can be resolved deterministically
- correction history can be audited without guessing
- replay results can be interpreted without hidden assumptions
- reason-code usage remains consistent with the official registry

## See also
- `Database_Schema_MariaDB.sql`
- `Indices_and_Constraints_Contract_LOCKED.md`
- `EOD_Publications_Table.sql`
- `../book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `../book/Historical_Correction_and_Reseal_Contract_LOCKED.md`