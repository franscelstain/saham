# Historical Replay and Data-Quality Backtest

## Purpose
This is **not** a downstream trading-strategy backtest.
Its purpose is to prove that the Market Data Platform can reproduce historical upstream datasets deterministically and that data-quality gates behave as expected.

## Replay scope
Replay over historical trading dates must validate at minimum:
- canonical bar publish reproducibility
- indicator reproducibility for the selected indicator set version
- eligibility snapshot reproducibility
- effective-date fallback behavior when requested dates are intentionally degraded
- hash stability
- seal/freeze behavior

## Replay inputs
- historical provider snapshots or frozen provider extracts
- versioned market calendar
- versioned ticker identity mapping
- effective-dated config registry
- selected indicator set version
- golden anomaly scenarios (missing bars, invalid bars, coverage drops, provider failure)

## Required outputs per replayed date
- run status
- effective trade date
- row counts
- reason-code counts
- hashes
- seal state
- comparison result versus expected/golden output

## Locked acceptance rule
A replay passes only if identical controlled inputs produce identical canonical outputs and identical hashes.
Any intentional semantic change must be represented as a versioned expectation update, not ignored as drift.
