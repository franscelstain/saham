# Market Data Platform (EOD)

## Purpose
This documentation set is the locked source of truth for Market Data Platform (EOD).
It is written as system specification, not as exploratory notes.

It defines the upstream market-data layer that produces:
- canonical EOD bars
- deterministic indicators
- eligibility snapshot
- run, hash, seal, and publication metadata
- correction and replay evidence
- immutable publication-bound history snapshots for production-grade auditability
- optional supplemental session snapshots

It does not define downstream scoring, ranking, picks, signals, portfolio construction, or broker execution.

## Domain boundary
Market Data Platform is an upstream data-production and publication-readiness module.

It may decide:
- what data is canonical
- what data is valid
- what is readable as upstream dataset
- what publication is current
- whether a correction safely supersedes a prior publication
- whether replay matched expectation

It must never decide:
- buy / sell
- ranking / picks
- entry / exit
- strategy fit
- portfolio action
- broker action

Read the boundary layer first:
- `book/Terminology_and_Scope.md`
- `book/Domain_Boundary_Invariants_LOCKED.md`

## Start here
Read in this order before going deeper into schema, ops, or proof packs:
1. `book/Terminology_and_Scope.md`
2. `book/Domain_Boundary_Invariants_LOCKED.md`
3. `book/INDEX.md`

After that, continue according to the work being done:
- implementation and publication flow → focus on the implementation-critical contracts referenced by `book/INDEX.md`, then continue to `db/`, `ops/`, and `indicators/` as needed
- compliance, replay, and correction proof → continue to `tests/`, `backtest/`, and the related proof contracts referenced by `book/INDEX.md`
- example shape and executed evidence review → use `examples/` and `evidence/` only as companion material, not as a source of new behavior

## Reading rule
Use this README for orientation only.
Use `book/INDEX.md` as the contract map for the Market Data Platform (EOD) book.
Use the companion folders (`db/`, `ops/`, `tests/`, `registry/`, `backtest/`, `indicators/`, `session_snapshot/`) only after the boundary and book-level contract map are understood. Use `examples/` and `evidence/` only as companion review material.

## Normative implementation and proof folders
The following folders are normative parts of the same source of truth:
- `book/`
- `db/`
- `ops/`
- `tests/`
- `registry/`
- `backtest/`
- `indicators/`
- `session_snapshot/`

## Companion review folders
The following folders are companion material and do not define new behavior beyond the normative contracts above:
- `examples/`
- `evidence/`

## Production-grade auditability stance
Production-grade row-history strategy is Strategy A:
- immutable publication-bound history snapshots
- publication-linked row history
- append-only history semantics
- preserved prior publication row state after correction

This is reflected by:
- `book/Canonical_Row_History_and_Versioning_Policy_LOCKED.md`
- history tables in `db/Database_Schema_MariaDB.sql`
- immutability guards in `ops/History_Table_Immutability_Guards_LOCKED.sql`

## Proof-by-execution stance
Proof specification alone is not the final target.

A mature implementation should also preserve:
- executed run evidence bundle
- executed replay evidence
- executed publication manifest
- executed correction diff
- executed test output evidence

This is reflected by:
- `ops/Run_Execution_Evidence_Pack_Contract_LOCKED.md`
- `ops/Executed_Run_Admission_Criteria_LOCKED.md`
- `tests/Executed_Proof_Admission_Criteria_LOCKED.md`
- files in `examples/`

## Archived actual execution evidence
Illustrative examples are not the same as archived actual execution evidence.

A mature implementation should preserve archived actual evidence bundles separately from examples, for example under:
- `evidence/runs/`
- `evidence/replays/`
- `evidence/corrections/`
- `evidence/tests/`

See:
- `ops/Archived_Actual_Execution_Evidence_Contract_LOCKED.md`
- `examples/ARCHIVED_EVIDENCE_FOLDER_STRUCTURE_LOCKED.md`

## Current-publication precedence
For the hardened production model, current publication resolution must use:
1. `eod_current_publication_pointer`
2. pointed publication validation
3. supporting consistency checks on `eod_publications` and `eod_runs`

Pointer mismatch is an operational incident and readability must fail safe until reconciled.

See:
- `book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`

## Freeze status
This documentation set is the locked source of truth for Market Data Platform (EOD).
It is written as system specification, not as exploratory notes.

Changes to locked contracts, publication semantics, correction flow, replay proof, consumer-readiness behavior, schema enforcement, row-history strategy, or audit evidence requirements must be versioned and reviewed explicitly.

## Anti-drift rule
If a document changes behavior for:
- current publication resolution
- seal/readability semantics
- correction switching
- row-history strategy
- hash/replay proof
- indicator formulas
- run-state interpretation
- audit evidence requirements
- schema enforcement invariants

then the change must be treated as a versioned contract change, not an editorial cleanup.

## Minimal compliance expectation
A compliant implementation of this documentation set must be able to prove:
- one coherent current readable publication per trade date
- explicit fallback when requested date is not readable
- reproducible artifact hashes for unchanged content
- explicit correction supersession trail
- immutable row-history snapshots for production-grade auditability
- append-only run event trail
- replay evidence and test evidence aligned with fixture-based proof contracts
- hardened current-publication integrity through pointer or equivalently strong enforcement
- clear distinction between illustrative examples and executed evidence