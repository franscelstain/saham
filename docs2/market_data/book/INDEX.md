# Book Index — Market Data Platform (EOD)

## Core scope, terminology, and boundary invariants
- Terminology_and_Scope.md
- Domain_Boundary_Invariants_LOCKED.md

## Consumer readability, publication, and determinism
- Downstream_Consumer_Read_Model_Contract_LOCKED.md
- Consumer_Readability_Decision_Table_LOCKED.md
- Downstream_Data_Readiness_Guarantee_LOCKED.md
- Determinism_Invariants_LOCKED.md

## Publication identity, switch safety, and correction integrity
- Publication_Manifest_Contract_LOCKED.md
- Publication_Switch_Integrity_Contract_LOCKED.md
- Historical_Correction_and_Reseal_Contract_LOCKED.md
- Canonical_Row_History_and_Versioning_Policy_LOCKED.md

## Core dependencies and universe foundations
- Market_Calendar_Requirements_Contract.md
- Tickers_and_Identity_Dependency_Contract_LOCKED.md
- Coverage_Universe_Definition_LOCKED.md
- Symbol_Lifecycle_and_Mapping_Contract.md

## Source acquisition and canonical data
- Source_Data_Acquisition_Contract_LOCKED.md
- Source_Mapping_Contract_LOCKED.md
- Canonicalization_Contract_EOD_Bars.md
- EOD_Bars_Contract.md
- Invalid_Bar_Storage_Policy_LOCKED.md
- EOD_Data_Retention_and_History_Rewrite_Policy_LOCKED.md

## Indicators and adjustments
- EOD_Indicators_Contract.md
- ../indicators/EOD_Indicators_Formula_Spec.md
- Corporate_Action_and_Adjustment_Policy.md
- Corporate_Action_and_Adjustment_Policy_Selected_Defaults_LOCKED.md
- Corporate_Action_Impact_Flags_Contract.md

## Run readiness, effective date, and consumer safety
- Run_Status_and_Quality_Gates_LOCKED.md
- Effective_Trade_Date_Contract_LOCKED.md
- EOD_Cutoff_and_Finalization_Contract_LOCKED.md
- EOD_Eligibility_Snapshot_Contract_LOCKED.md
- Eligibility_Partial_Data_Behavior_LOCKED.md
- Dataset_Seal_and_Freeze_Contract_LOCKED.md
- Audit_Hash_and_Reproducibility_Contract_LOCKED.md
- Hash_Number_Formatting_LOCKED.md

## Audit and proof hardening
- Publication_Manifest_Contract_LOCKED.md
- Publication_Switch_Integrity_Contract_LOCKED.md
- Canonical_Row_History_and_Versioning_Policy_LOCKED.md

## Supporting datasets
- Market_Daily_Metrics_Contract.md

## Normative companion folders
The following folders are normative companions to this book and must be treated as part of the same source of truth:
- `../db/`
- `../ops/`
- `../tests/`
- `../registry/`
- `../backtest/`
- `../indicators/`
- `../session_snapshot/`
- `../examples/`

## Recommended reading order
1. Read `Terminology_and_Scope.md` first.
2. Read `Domain_Boundary_Invariants_LOCKED.md` to lock the upstream-only boundary.
3. Read the consumer-readability core:
   - `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
   - `Consumer_Readability_Decision_Table_LOCKED.md`
   - `Downstream_Data_Readiness_Guarantee_LOCKED.md`
4. Read the determinism and publication identity core:
   - `Determinism_Invariants_LOCKED.md`
   - `Publication_Manifest_Contract_LOCKED.md`
   - `Publication_Switch_Integrity_Contract_LOCKED.md`
5. Lock calendar, identity, coverage, and symbol-lifecycle dependencies before implementing ingest.
6. Implement upstream flow in this exact order:
   - bars
   - indicators
   - eligibility
   - hash
   - seal
   - finalize
7. Read `Historical_Correction_and_Reseal_Contract_LOCKED.md` and `Canonical_Row_History_and_Versioning_Policy_LOCKED.md` before implementing correction, publication switching, or row-history strategy.
8. Treat the companion folders as normative implementation, audit, testing, replay, evidence, and schema layers, not optional notes.

## Freeze note
This index maps the locked book-level contracts only.
Companion folders contain normative schema, operations, testing, replay, evidence, and example layers that complete the same source of truth.