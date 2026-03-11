# Market Data Platform (EOD) — Documentation

## Purpose
Market Data Platform (EOD) is an upstream data-production module. Its job is to produce market data that is canonical, validated, deterministic, auditable, and safe for downstream consumers.

This module provides:
1) Canonical EOD OHLCV bars.
2) Deterministic EOD indicators computed from canonical bars.
3) Run status, quality gates, and effective trade date resolution.
4) Eligibility snapshot so consumers never infer readiness from raw tables.
5) Audit hashes and dataset seal/freeze semantics.
6) Optional intraday snapshots as best-effort overlay input for downstream consumers.

This module does **not** define downstream policy logic such as scoring, grouping, ranking, or portfolio selection.

## Scope boundary (LOCKED)
Included:
- provider acquisition
- mapping and canonicalization
- bar validation
- indicator computation
- eligibility publication
- effective-date fallback
- audit hashes
- dataset sealing
- runbooks, locking, logging, replay, and contract tests

Excluded:
- watchlist scoring/grouping/ranking
- portfolio logic
- trading signals
- execution logic
- broker integration

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
- If requested trade date T is not finalized `SUCCESS`, consumers must fall back to the latest prior sealed `SUCCESS` effective date.
