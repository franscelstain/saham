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

It depends on shared-foundation master data outside this domain, especially the market calendar and ticker identity master.
Those global dependencies may live under `docs/db/` or an equivalent shared-foundation owner.
This domain consumes those dependencies, but it remains the authoritative owner for canonical EOD bars, EOD indicators, eligibility/readiness semantics, publication/read-model behavior, sealing, replay, correction handling, and upstream audit evidence.

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


## Downstream consumer intake rule
This domain is the owner of upstream market-data meaning for downstream consumers.

For the active documentation set, any downstream consumer such as `watchlist` must bind its intake to producer-facing contracts from this domain, especially consumer-readable and publication-aware contracts.

A downstream consumer must not:
- define its own replacement meaning for upstream readability
- read raw internal pipeline states as if they were consumer-facing output
- treat intermediate technical artifacts as the authoritative intake path

Use these as the primary intake anchors for downstream consumption:
- `book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`

This README does not define downstream watchlist behavior. It only fixes the allowed upstream meaning that a downstream consumer may read.

## Start here
This README is the orientation document itself. Before using it as the fourth read anchor referenced by `system/` and `audit/`, first read in this order:
1. `book/Terminology_and_Scope.md`
2. `book/Domain_Boundary_Invariants_LOCKED.md`
3. `book/INDEX.md`

After those three anchors, return here and continue according to the work being done:
- implementation and publication flow → read the implementation-critical contracts below first, then continue to `db/`, `ops/`, and `indicators/` as needed
- compliance, replay, and correction proof → continue to `tests/`, `backtest/`, and the related proof contracts referenced by `book/INDEX.md`
- example shape and executed evidence review → use `examples/` and `evidence/` only as companion material, not as a source of new behavior

## Implementation-critical contracts for build
A builder should not guess the runtime path from the whole tree. For the main upstream build path, read these files first and keep them aligned as one set:
1. `book/Domain_Boundary_Invariants_LOCKED.md`
2. `book/EOD_Bars_Contract.md`
3. `book/EOD_Indicators_Contract.md`
4. `book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
5. `book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`
6. `book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
7. `book/Dataset_Seal_and_Freeze_Contract_LOCKED.md`
8. `book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
9. `db/EOD_Current_Publication_Pointer_Table.sql`
10. `db/Publication_Current_Pointer_Switch_Procedure_LOCKED.sql`
11. `ops/Daily_Pipeline_Execution_and_Sealing_Runbook_LOCKED.md`
12. `ops/Audit_Query_Cookbook_LOCKED.md`

A builder should treat the files above as the shortest path to a coherent implementation. Everything else expands or supports that path; it must not silently replace it.

## Reading rule
Use this README for orientation only.
Use `book/INDEX.md` as the contract map for the Market Data Platform (EOD) book.
Use the companion folders (`db/`, `ops/`, `tests/`, `registry/`, `backtest/`, `indicators/`, `session_snapshot/`) only after the boundary and book-level contract map are understood. Use `examples/` and `evidence/` only as companion review material.

## Normative implementation and proof folders
The following folders are normative parts of the same source of truth:

Book-level contracts remain the primary behavioral owner for domain meaning and boundary. Companion folders may specify schema, formulas, procedures, tests, and proof obligations, but they must not redefine book-level ownership or create parallel contract authority.
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

They may illustrate, archive, or demonstrate compliance, but they must never become a second source of truth for domain behavior.
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
- executed bundles archived under `evidence/`

Illustrative shapes may still appear under `examples/`, but they do not count as executed proof.

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