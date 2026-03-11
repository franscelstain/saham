# Market Data Platform (EOD) — Documentation

## Purpose
Market Data Platform (EOD) is an upstream data-production module. Its job is to produce market data that is canonical, validated, deterministic, auditable, and safe for downstream consumers.

This module provides:
1) Canonical EOD OHLCV bars.
2) Deterministic EOD indicators computed from canonical bars.
3) Run status, quality gates, cutoff/finalization rules, and effective trade date resolution.
4) Eligibility snapshot so consumers never infer readiness from raw tables.
5) Audit hashes plus dataset seal/freeze semantics.
6) Optional intraday snapshots as best-effort overlay artifacts aligned to the effective trade date.
7) Replay/backtest specifications that validate upstream data quality and reproducibility, not downstream strategy results.

This module does **not** define downstream policy logic such as scoring, grouping, ranking, portfolio selection, or execution behavior.

## Scope boundary (LOCKED)
Included:
- provider acquisition
- symbol mapping and canonicalization
- canonical bar validation and invalid-row audit storage
- indicator computation
- eligibility publication
- cutoff and effective-date fallback
- audit hashes and reproducibility rules
- dataset sealing and freeze/correction flow
- runbooks, locking, logging, replay, and contract tests
- optional intraday snapshots that do not mutate EOD artifacts

Excluded:
- downstream screening/scoring/grouping/ranking
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
- `intraday/` — best-effort intraday snapshot contracts
- `tests/` — contract tests and golden fixtures
- `backtest/` — historical replay and data-quality backtest specs

## Consumer invariants (LOCKED summary)
- Consumers must resolve `trade_date_effective` from `eod_runs` and must not infer dates via `MAX(trade_date)`.
- Consumers must treat `eod_eligibility(trade_date = D, eligible = 1)` as the official readable universe for D.
- Consumers must read indicators from `eod_indicators`; consumers must not recompute indicators from `eod_bars` at read-time.
- Consumers must treat `SEALED` as a hard readiness condition.
- If requested trade date T is not finalized and sealed for consumption, consumers must fall back to the latest prior sealed `SUCCESS` effective date.
- Artifacts for one readable date must come from one coherent finalized run context; implementations must not mix rows across runs for the same date.