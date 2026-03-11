# Book Index — Market Data Platform (EOD)

## Core scope and dependencies
- Terminology_and_Scope.md
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
- Corporate_Action_and_Adjustment_Policy.md
- Corporate_Action_and_Adjustment_Policy_Selected_Defaults_LOCKED.md
- Corporate_Action_Impact_Flags_Contract.md

## Run readiness and consumer safety
- Run_Status_and_Quality_Gates_LOCKED.md
- Effective_Trade_Date_Contract_LOCKED.md
- EOD_Cutoff_and_Finalization_Contract_LOCKED.md
- EOD_Eligibility_Snapshot_Contract_LOCKED.md
- Eligibility_Partial_Data_Behavior_LOCKED.md
- Dataset_Seal_and_Freeze_Contract_LOCKED.md
- Audit_Hash_and_Reproducibility_Contract_LOCKED.md
- Hash_Number_Formatting_LOCKED.md
- Downstream_Consumer_Read_Model_Contract_LOCKED.md
- Downstream_Data_Readiness_Guarantee_LOCKED.md

## Supporting datasets
- Market_Daily_Metrics_Contract.md

## How to use this book
1) Read `Terminology_and_Scope.md` first.
2) Lock identity/calendar/coverage before implementing ingest.
3) Implement bars -> indicators -> eligibility -> hash -> seal -> finalize in that exact order.
4) Treat the `ops/`, `tests/`, `registry/`, and `backtest/` folders as normative companions to this book, not optional notes.