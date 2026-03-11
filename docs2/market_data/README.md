# Market Data Platform (EOD) — Documentation

## Purpose
Market Data Platform (EOD) provides:
1) Canonical EOD OHLCV data that is deterministic and auditable.
2) EOD indicators computed from canonical EOD bars.
3) Run status + quality gates to declare when data is safe for consumers.
4) An effective trade date rule so consumers never guess dates.
5) A daily eligibility snapshot so consumers avoid ad-hoc filtering from raw tables.

This module is upstream. Consumers (including Watchlist policies) must treat it as the source of truth.

## Folder structure
- book/ — core contracts (stable, referenced by all consumers)
- db/ — MariaDB schema contracts and DDL
- registry/ — reason codes + indicator baseline + platform config
- ops/ — operational playbooks and runbooks
- indicators/ — deterministic indicator formulas
- intraday/ — best-effort snapshot contracts for CONFIRM consumers
- tests/ — contract tests specs + golden fixtures guidance
- backtest/ — historical replay (data-quality backtest) specs

## Consumer rules (LOCKED summary)
- Consumers must read trade_date_effective from eod_runs.
- Consumers must use eod_eligibility (eligible=1) to select candidates.
- Consumers must not compute indicators from eod_bars directly.
- If run is not SUCCESS for the requested trading day, consumers must use the effective trade date (fallback).

## Ops
- See ops/Commands_and_Runbook.md and ops/Failure_Playbook.md