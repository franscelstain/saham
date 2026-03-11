# Market Data Platform (EOD) — Documentation

This module provides:
1) Canonical EOD OHLCV bars.
2) Deterministic EOD indicators computed from canonical bars.
3) Run status, quality gates, cutoff/finalization rules, and effective trade date resolution.
4) Eligibility snapshot so consumer code never infers readiness from raw tables.
5) Audit hashes plus dataset seal/freeze semantics.
6) Optional non-streaming session snapshots captured manually or via normal API refresh, aligned to the effective trade date.
7) Replay/backtest specifications that validate upstream data quality and reproducibility, not strategy results.

This module does **not** define scoring, grouping, ranking, portfolio selection, execution behavior, broker integration, or any real-time streaming requirement.

## Scope boundary (LOCKED)
Included:
- public/free API acquisition and/or manual file/manual-entry ingestion
- source normalization into canonical schema
- canonical bar validation and invalid-row audit storage
- indicator computation
- eligibility publication
- cutoff and effective-date fallback
- audit hashes and reproducibility rules
- dataset sealing and freeze/correction flow
- runbooks, locking, logging, replay, and contract tests
- optional non-streaming session snapshots that do not mutate EOD artifacts

Excluded:
- paid-provider assumptions
- streaming / tick-by-tick / websocket processing
- screening/scoring/grouping/ranking
- signal generation
- trade recommendation policy
- execution logic
- broker integration
- portfolio construction or risk allocation

## Folder structure
- `book/` — core contracts and locked semantics
- `db/` — MariaDB schema contracts and DDL
- `registry/` — output-affecting registries and defaults
- `ops/` — operational runbooks and failure handling
- `indicators/` — deterministic formula specification
- `session_snapshot/` — optional non-streaming session snapshot contracts
- `tests/` — contract tests and golden fixtures
- `backtest/` — historical replay and data-quality backtest specs

## Consumer-visible dataset set (LOCKED)
For one readable effective trade date `D`, the upstream dataset consists only of:
- `eod_bars(trade_date = D)`
- `eod_indicators(trade_date = D)`
- `eod_eligibility(trade_date = D)`
- run metadata on `eod_runs` that resolves `trade_date_effective = D`, final status, hashes, and seal metadata
- optional `session_snapshots(trade_date = D)` that remain separate from the sealed EOD dataset

`eod_invalid_bars`, provider raw rows, run events, retry logs, and operator notes are audit artifacts, not consumer-visible dataset members.

## Consumer invariants (LOCKED summary)
- Consumers must resolve `trade_date_effective` from `eod_runs` and must not infer dates via `MAX(trade_date)`.
- Consumers must treat `eod_eligibility(trade_date = D, eligible = 1)` as the official readable universe for D.
- Consumers must read indicators from `eod_indicators`; consumers must not recompute indicators from `eod_bars` at read-time.
- Consumers must treat `SEALED` as a hard readiness condition.
- If requested trade date T is not finalized and sealed for consumption, consumers must fall back to the latest prior sealed `SUCCESS` effective date.
- Artifacts for one readable date must come from one coherent finalized run context; implementations must not mix rows across runs for the same date.

## Reproducibility guardrail (LOCKED)
Batch hashes prove content identity of the published dataset for `D`.
They must hash canonical content fields only, using locked ordering and formatting.
Per-run provenance such as `run_id`, `asof_run_id`, timestamps of ingestion, and operator identity remain mandatory audit metadata, but they must not change the content hash for an otherwise identical dataset.

## Final locked contracts
- book/Terminology_and_Scope.md
- book/Downstream_Consumer_Read_Model_Contract_LOCKED.md
- book/Downstream_Data_Readiness_Guarantee_LOCKED.md
- book/Determinism_Invariants_LOCKED.md
- book/Audit_Hash_and_Reproducibility_Contract_LOCKED.md
- book/Historical_Correction_and_Reseal_Contract_LOCKED.md

## Implementation-critical schema and operations
- db/Database_Schema_MariaDB.sql
- db/Database_Schema_Contracts_MariaDB.md
- db/Indices_and_Constraints_Contract_LOCKED.md
- db/EOD_Publications_Table.sql
- ops/Commands_and_Runbook.md
- ops/Failure_Playbook.md
- ops/Run_Ownership_and_Recovery_LOCKED.md
- ops/Run_Artifacts_Format.md
- ops/Audit_Evidence_Pack_Contract_LOCKED.md

## Testing, fixtures, and replay proof
- tests/Contract_Test_Matrix_LOCKED.md
- tests/Golden_Fixture_Catalog_LOCKED.md
- tests/Golden_Fixture_Examples_LOCKED.md
- tests/Test_Implementation_Guidance_LOCKED.md
- backtest/Historical_Replay_and_Data_Quality_Backtest.md
- backtest/Replay_Results_Schema_MariaDB.sql