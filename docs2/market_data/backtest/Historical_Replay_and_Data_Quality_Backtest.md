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
- versioned ticker identity mapping / temporal universe membership
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

## Required replay dimensions (LOCKED)
1. unchanged-input replay: same inputs, same config, same mappings, same version set => identical output hashes
2. degraded-input replay: injected anomaly => expected `HELD` / `FAILED` / fallback outcome only
3. controlled-correction replay: corrected historical input => new hashes with preserved prior audit trail
4. formatting replay: locale/runtime differences must not change serialized hash payload

## Locked acceptance rule
A replay passes only if identical controlled inputs produce identical canonical outputs and identical hashes.
Any intentional semantic change must be represented as a versioned expectation update, not ignored as drift.