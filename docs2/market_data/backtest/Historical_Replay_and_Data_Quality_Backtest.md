# Historical Replay and Data-Quality Backtest (LOCKED)

## Purpose
This is not a downstream trading-strategy backtest.

Its purpose is to prove that Market Data Platform can:
- reproduce historical upstream datasets deterministically
- preserve correction history explicitly
- verify quality-gate behavior on degraded data
- verify seal/publication behavior
- verify that replay outcomes match expected fixture-backed results

## Replay scope
Replay over historical trading dates must validate at minimum:
- canonical bar reproducibility
- indicator reproducibility for the selected indicator set version
- eligibility reproducibility
- effective-date fallback behavior
- content hash reproducibility
- seal/publication behavior
- historical correction publication behavior where applicable

## Required replay inputs
- historical provider snapshots or frozen extracts
- versioned market calendar
- versioned ticker identity / temporal membership snapshot
- effective-dated config registry snapshot
- selected indicator-set version
- golden anomaly scenarios
- correction scenario fixtures where correction integrity is being tested

## Required outputs per replayed date
For each replayed date, record at minimum:
- requested trade date
- effective trade date
- terminal status
- row counts
- reason-code counts
- bars/indicators/eligibility hashes
- seal state
- comparison result vs expected output
- mismatch summary when result is not a match

## Required replay dimensions (LOCKED)

### 1. Unchanged-input replay
Same inputs, same config snapshot, same calendar, same identity mapping, same contract versions.

Must prove:
- same canonical outputs
- same eligibility outputs
- same hashes
- same comparison result = `MATCH`

### 2. Degraded-input replay
Intentionally degraded input or anomaly injection.

Must prove:
- expected non-happy-path status
- expected fallback behavior
- expected comparison result classification
- no silent false-success publication

### 3. Controlled-correction replay
Use original published state plus corrected rerun scenario.

Must prove:
- prior publication preserved
- corrected publication becomes current only after reseal
- old and new hash trails both remain auditable
- unchanged rerun case does not create fake correction publication

### 4. Formatting/runtime stability replay
Vary runtime or locale conditions without changing semantic input.

Must prove:
- serialized hash payload does not drift
- hash outputs remain identical

## Comparison result classes
Minimum replay comparison classes:
- `MATCH`
- `MISMATCH`
- `EXPECTED_DEGRADE`
- `UNEXPECTED`

### Meaning
- `MATCH`: actual output matches expected golden outcome exactly
- `MISMATCH`: actual output differs from expected golden outcome unexpectedly
- `EXPECTED_DEGRADE`: degraded scenario produced the expected degraded result
- `UNEXPECTED`: result does not match expected class and needs investigation

## Locked acceptance rules
A replay passes only if:
- identical controlled inputs produce identical canonical outputs and identical hashes
- degraded scenarios produce the expected degraded outcomes
- corrected-history scenarios preserve prior publication and publish new current state only after reseal
- unchanged rerun scenarios do not create fake correction publications

## Failure interpretation
Replay failure means at least one of the following:
- canonical output drift
- indicator drift
- eligibility drift
- hash drift
- readiness/publication drift
- correction-history integrity failure
- fixture expectation mismatch

## Anti-fake-proof rule (LOCKED)
A replay that merely “runs successfully” without proving expected outputs and publication semantics does not satisfy this contract.