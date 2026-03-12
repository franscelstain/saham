# Market Data Platform (EOD)

## Purpose
This documentation set is the locked source of truth for Market Data Platform (EOD).

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

## Final locked contracts
- `book/Terminology_and_Scope.md`
- `book/Domain_Boundary_Invariants_LOCKED.md`
- `book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `book/Consumer_Readability_Decision_Table_LOCKED.md`
- `book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `book/Determinism_Invariants_LOCKED.md`
- `book/Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- `book/Publication_Manifest_Contract_LOCKED.md`
- `book/Publication_Switch_Integrity_Contract_LOCKED.md`
- `book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `book/Canonical_Row_History_and_Versioning_Policy_LOCKED.md`

## Implementation-critical schema and operations
- `db/Database_Schema_MariaDB.sql`
- `db/Database_Schema_Contracts_MariaDB.md`
- `db/Indices_and_Constraints_Contract_LOCKED.md`
- `db/EOD_Publications_Table.sql`
- `db/Publication_Switch_Procedure_LOCKED.sql`
- `db/Schema_Enforcement_Notes_LOCKED.md`
- `ops/Commands_and_Runbook_LOCKED.md`
- `ops/Failure_Playbook_LOCKED.md`
- `ops/Run_Ownership_and_Recovery_LOCKED.md`
- `ops/Run_Artifacts_Format_LOCKED.md`
- `ops/Audit_Evidence_Pack_Contract_LOCKED.md`
- `ops/Audit_Query_Cookbook_LOCKED.md`
- `ops/Incident_Classification_and_Response_Matrix_LOCKED.md`
- `ops/Operator_Decision_Trees_LOCKED.md`
- `ops/Correction_Diff_Artifact_Contract_LOCKED.md`
- `ops/History_Table_Immutability_Guards_LOCKED.sql`
- `ops/Performance_SLO_and_Limits_LOCKED.md`

## Testing, fixtures, and replay proof
- `tests/Contract_Test_Matrix_LOCKED.md`
- `tests/Golden_Fixture_Catalog_LOCKED.md`
- `tests/Golden_Fixture_Examples_LOCKED.md`
- `tests/Test_Implementation_Guidance_LOCKED.md`
- `tests/Fixture_Package_Manifest_LOCKED.md`
- `tests/Test_Coverage_Closure_Contract_LOCKED.md`
- `tests/Negative_Test_Catalog_LOCKED.md`
- `tests/Indicator_Test_Vectors_LOCKED.md`
- `tests/Indicator_Expected_Output_Oracle_LOCKED.md`
- `backtest/Historical_Replay_and_Data_Quality_Backtest.md`
- `backtest/Replay_Results_Schema_MariaDB.sql`

## Executed evidence examples
These example files exist to demonstrate proof-by-execution style evidence, not just proof specification.
- `examples/Executed_Replay_Evidence_Example_LOCKED.md`
- `examples/Executed_Publication_Manifest_Example_LOCKED.md`
- `examples/Executed_Correction_Diff_Example_LOCKED.md`
- `examples/Executed_Test_Run_Example_LOCKED.md`

## Recommended reading order
1. Read `book/Terminology_and_Scope.md` first.
2. Read `book/Domain_Boundary_Invariants_LOCKED.md` to lock the upstream-only boundary and anti-domain-leak rules.
3. Read the consumer-readability and publication core:
   - `book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
   - `book/Consumer_Readability_Decision_Table_LOCKED.md`
   - `book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
4. Read the determinism and publication identity core:
   - `book/Determinism_Invariants_LOCKED.md`
   - `book/Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
   - `book/Publication_Manifest_Contract_LOCKED.md`
   - `book/Publication_Switch_Integrity_Contract_LOCKED.md`
5. Read dependency and canonical data contracts before implementing ingest:
   - calendar
   - identity
   - coverage universe
   - source acquisition
   - source mapping
   - canonicalization
6. Read indicator contracts and formula spec before implementing indicator compute.
7. Implement upstream flow in this exact order:
   - bars
   - indicators
   - eligibility
   - hash
   - seal
   - finalize
8. Read correction and history policy before implementing correction or publication switching:
   - `book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
   - `book/Canonical_Row_History_and_Versioning_Policy_LOCKED.md`
9. Treat `db/`, `ops/`, `tests/`, `registry/`, `backtest/`, `indicators/`, `session_snapshot/`, and `examples/` as normative companions to the book, not optional notes.

## Normative companion folders
The following folders are normative parts of the same source of truth:
- `book/`
- `db/`
- `ops/`
- `tests/`
- `registry/`
- `backtest/`
- `indicators/`
- `session_snapshot/`
- `examples/`

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

## Freeze status
This documentation set is the locked source of truth for Market Data Platform (EOD).

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