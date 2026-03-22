# System Context and Dependencies

## Purpose
Dokumen ini merangkum konteks sistem dan dependensi utamanya. Detail normatif tetap hidup di owner folders.

## External and shared-foundation dependencies
Market-data bergantung pada sumber data dan shared foundation tertentu, terutama:
- upstream market-data providers
- market calendar requirements
- ticker identity / symbol lifecycle dependencies
- corporate action dependencies where relevant

Refer to owner contracts such as:
- `book/Source_Data_Acquisition_Contract_LOCKED.md`
- `book/Market_Calendar_Requirements_Contract.md`
- `book/Tickers_and_Identity_Dependency_Contract_LOCKED.md`
- `book/Symbol_Lifecycle_and_Mapping_Contract.md`
- `book/Corporate_Action_and_Adjustment_Policy.md`

## Platform-internal dependencies
Platform behavior juga bergantung pada area berikut:
- `registry/` for baseline config, indicator registry, normalization rules, and reason codes
- `indicators/` for deterministic formulas and computation behavior
- `session_snapshot/` for optional supplemental intraday/session snapshot capability
- `db/` for publication tables, current pointers, optional failure tables, and switch procedures
- `ops/` for scheduling, locking, release gates, run artifacts, and incident handling
- `tests/` and `backtest/` for proof and replay validation

## Operating model note
Paket ini harus jujur terhadap operating model. Bila sumber data masih gratis/public atau ada langkah operator/manual injection tertentu, hal itu adalah bagian dari operating model dan harus dibaca melalui contract/runbook terkait, bukan disembunyikan.

## Dependency reading pointers
- acquisition and source behavior → `book/Source_Data_Acquisition_Contract_LOCKED.md`
- publication pointer integrity → `book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`, `db/EOD_Current_Publication_Pointer_Table.sql`, `db/Publication_Current_Pointer_Switch_Procedure_LOCKED.sql`
- reproducibility and sealing → `book/Audit_Hash_and_Reproducibility_Contract_LOCKED.md`, `book/Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- run execution evidence → `ops/Run_Execution_Evidence_Pack_Contract_LOCKED.md`, `ops/Archived_Actual_Execution_Evidence_Contract_LOCKED.md`
- replay/backtest proof → `backtest/Historical_Replay_and_Data_Quality_Backtest.md`, `backtest/Backtest_Metrics_and_Acceptance_Criteria_LOCKED.md`


## Downstream consumer dependency direction
Market-data is a producer domain. Downstream consumers may depend on its published, consumer-readable output, but they do not own the meaning of that output.

For the active scope, `watchlist` is a downstream consumer of market-data. The dependency direction is:
- shared foundation -> market-data
- market-data producer contracts -> watchlist consumer intake
- `api_architecture` -> implementation translation only

That means `watchlist` must read producer-facing, publication-aware upstream output from market-data, not raw pipeline internals, temporary processing state, or implementation-only artifacts.

## Downstream intake anchor pointers
For downstream consumers, the minimum authoritative intake anchors are:
- `book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`

These anchors exist to keep downstream intake single in meaning.
