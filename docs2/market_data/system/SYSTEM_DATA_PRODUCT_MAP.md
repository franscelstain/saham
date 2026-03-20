# System Data Product Map

## Purpose
Dokumen ini memetakan produk data utama yang dihasilkan platform. Ia tidak menggantikan contract detail.

## Main products
### Canonical EOD bars
Owner pointers:
- `book/EOD_Bars_Contract.md`
- `book/Canonicalization_Contract_EOD_Bars.md`
- `book/Canonical_Row_History_and_Versioning_Policy_LOCKED.md`

### Deterministic EOD indicators
Owner pointers:
- `book/EOD_Indicators_Contract.md`
- `indicators/EOD_Indicators_Formula_Spec.md`
- `indicators/Indicator_Computation_Specification.md`
- `registry/Indicator_Registry_Baseline_LOCKED.md`

### Eligibility and readability artifacts
Owner pointers:
- `book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `book/Eligibility_Partial_Data_Behavior_LOCKED.md`
- `book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`

### Publication and current-pointer artifacts
Owner pointers:
- `book/Publication_Manifest_Contract_LOCKED.md`
- `book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`
- `db/EOD_Publications_Table.sql`
- `db/EOD_Current_Publication_Pointer_Table.sql`
- `db/Publication_Switch_Procedure_LOCKED.sql`
- `db/Publication_Current_Pointer_Switch_Procedure_LOCKED.sql`

### Correction, replay, and reseal artifacts
Owner pointers:
- `book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `book/Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `book/Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- `backtest/Historical_Replay_and_Data_Quality_Backtest.md`
- `backtest/Replay_Results_Schema_MariaDB.sql`

### Optional supplemental session snapshots
Owner pointers:
- `session_snapshot/Session_Snapshot_Contract_LOCKED.md`
- `session_snapshot/Session_Snapshot_Scope_Selection_and_Dependencies_LOCKED.md`
- `session_snapshot/Session_Snapshot_Date_Alignment_with_Effective_Date_LOCKED.md`

## Readability rule
System map ini hanya menunjukkan produk utama dan pointer owner-nya. Behavior rinci tetap harus dibaca dari file owner yang dirujuk.
