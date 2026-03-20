# System Runtime Flow

## Purpose
Dokumen ini menjelaskan alur runtime tingkat atas dan menunjuk ke owner docs yang mengatur tiap tahap.

## High-level flow
1. acquire source data
2. validate and normalize
3. build canonical EOD bars
4. compute deterministic indicators
5. derive eligibility/readiness artifacts
6. seal and publish dataset artifacts
7. switch current pointers safely
8. support correction, replay, and reseal when required
9. archive run evidence and proof artifacts

## Owner pointers by stage
### Acquisition
- `book/Source_Data_Acquisition_Contract_LOCKED.md`
- `book/Source_Mapping_Contract_LOCKED.md`

### Validation / canonicalization
- `book/EOD_Bars_Contract.md`
- `book/Canonicalization_Contract_EOD_Bars.md`
- `book/Invalid_Bar_Storage_Policy_LOCKED.md`

### Indicator computation
- `book/EOD_Indicators_Contract.md`
- `indicators/EOD_Indicators_Formula_Spec.md`
- `indicators/Indicator_Computation_Specification.md`
- `registry/Indicator_Registry_Baseline_LOCKED.md`

### Eligibility and readability
- `book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `book/Eligibility_Partial_Data_Behavior_LOCKED.md`
- `book/Downstream_Data_Readiness_Guarantee_LOCKED.md`

### Publication and pointer switching
- `book/Publication_Manifest_Contract_LOCKED.md`
- `book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`
- `db/Publication_Switch_Procedure_LOCKED.sql`
- `db/Publication_Current_Pointer_Switch_Procedure_LOCKED.sql`

### Correction / replay / reseal
- `book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `book/Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `backtest/Historical_Replay_and_Data_Quality_Backtest.md`
- `ops/Historical_Correction_Runbook_LOCKED.md`

### Operational execution and evidence
- `ops/Daily_Pipeline_Execution_and_Sealing_Runbook_LOCKED.md`
- `ops/Run_Execution_Evidence_Pack_Contract_LOCKED.md`
- `ops/Archived_Actual_Execution_Evidence_Contract_LOCKED.md`
- `tests/Executed_Proof_Admission_Criteria_LOCKED.md`

## Reminder
`SYSTEM_RUNTIME_FLOW.md` tidak menetapkan behavior rinci. Ia hanya memetakan alur dan menunjuk ke pemilik aturan.
