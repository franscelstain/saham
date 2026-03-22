# Book Index — Market Data Platform (EOD)

## Core scope, terminology, and boundary invariants
- Terminology_and_Scope.md
- Domain_Boundary_Invariants_LOCKED.md

## Consumer readability, publication, and determinism
- Downstream_Consumer_Read_Model_Contract_LOCKED.md
- Consumer_Readability_Decision_Table_LOCKED.md
- Downstream_Data_Readiness_Guarantee_LOCKED.md
- Determinism_Invariants_LOCKED.md

## Publication identity, pointer integrity, switch safety, and correction integrity
- Publication_Manifest_Contract_LOCKED.md
- Publication_Current_Pointer_Integrity_Contract_LOCKED.md
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


## Supporting datasets
- Market_Daily_Metrics_Contract.md

## Normative companion folders
The following folders are normative companions to this book and must be treated as part of the same source of truth:

These folders extend implementation detail, persistence, proof, and operations. They do not replace book-level ownership for domain scope, boundary, publication semantics, or other core behavioral contracts unless a book contract explicitly delegates that narrower subject.
- `../db/`
- `../ops/`
- `../tests/`
- `../registry/`
- `../backtest/`
- `../indicators/`
- `../session_snapshot/`

## Companion review folders
The following folders are companion material for review and illustration. They do not define new behavior beyond the normative contracts above:
- `../examples/`
- `../evidence/`

## Freeze note
This index maps the primary book-level contracts and the companion normative folders that complete the same source of truth. It must not be read as a list of `LOCKED` files only, because some owned contracts in this book are intentionally non-LOCKED and several companion folders are authoritative support layers. Companion review folders contain illustration and archived review material only.

## Evidence note
Archived actual execution evidence is part of the normative proof ecosystem, but is expected to live outside the book folder in an evidence archive area or equivalent official repository.