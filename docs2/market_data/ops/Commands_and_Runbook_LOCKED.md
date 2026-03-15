# Commands and Runbook (Market Data Platform, LOCKED)

## Purpose
Lock the minimum commands and operator checkpoints required so the platform can produce upstream market data that is canonical, validated, deterministic, auditable, and safe for consumer modules.

This document does not define screening, scoring, grouping, ranking, strategy logic, or real-time streaming.

## General command rules (LOCKED)
- all commands must run within a clear `run_id` context
- commands for the same requested date must respect run ownership rules
- commands must write minimum structured evidence to `eod_run_events`
- no command may commit final `SUCCESS` before seal exists
- output-affecting parameters must come from the effective registry snapshot, not undocumented runtime overrides

## Minimum commands

### 1. `market-data:eod-bars:ingest`
#### Purpose
Acquire and canonicalize EOD bars for requested date T.

#### Minimum input
- `requested_date=T`
- source mode (`api|manual_file|manual_entry`)

#### Minimum output
- valid bars -> `eod_bars`
- invalid bars -> `eod_invalid_bars`
- telemetry counts -> `eod_runs`
- audit events -> `eod_run_events`

### 2. `market-data:eod-indicators:compute`
#### Purpose
Compute deterministic indicators from canonical bars.

#### Minimum input
- `requested_date=T`
- `indicator_set_version`

#### Minimum output
- indicator artifact -> `eod_indicators`
- updated run/event telemetry

### 3. `market-data:eod-eligibility:build`
#### Purpose
Build one eligibility row per universe ticker for date T or resolved candidate D as defined by the upstream flow.

#### Minimum input
- `requested_date=T`
- coverage universe snapshot for T/D according to contract

#### Minimum output
- `eod_eligibility`
- run/event telemetry

### 4. `market-data:audit:hash`
#### Purpose
Compute content hashes over consumer-visible artifacts.

#### Minimum input
- `requested_date=T`
- candidate effective date `D`

#### Minimum output
- `bars_batch_hash`
- `indicators_batch_hash`
- `eligibility_batch_hash`

### 5. `market-data:dataset:seal`
#### Purpose
Seal a coherent consumer-readable candidate dataset.

#### Minimum input
- `requested_date=T`
- candidate effective date `D`

#### Minimum output
- seal metadata for the candidate dataset
- updated run/event evidence

### 6. `market-data:run:finalize`
#### Purpose
Resolve terminal run status and effective-date readability.

#### Minimum input
- `requested_date=T`

#### Minimum output
- final `terminal_status`
- resolved `trade_date_effective`
- final run evidence

### 7. `market-data:daily`
#### Purpose
Execute the daily sequence:
- ingest
- compute
- eligibility
- hash
- seal
- finalize

### 8. `market-data:backfill`
#### Purpose
Historical backfill/recompute per trading-date range.

### 9. `market-data:session-snapshot`
#### Purpose
Capture optional non-streaming session snapshot aligned to readable effective trade date.

### 10. `market-data:session-snapshot:purge`
#### Purpose
Purge session snapshot rows according to retention policy.

---

## Operator checkpoints per command

### `market-data:eod-bars:ingest`
#### Verify before continuing
- requested date is correct
- owner run is correct
- source mode is expected
- acquisition completed or degraded state is explicit

#### Expected outputs
- canonical bars exist or explicit gap/failure evidence exists
- invalid bars stored when applicable
- run/event counts updated

#### Stop publish if
- source contract is untrustworthy
- coverage impact is unresolved
- canonical bars are incomplete in a way that may break locked gates

---

### `market-data:eod-indicators:compute`
#### Verify before continuing
- canonical bars for required keys/dates are available
- indicator set version is correct
- dependency windows are resolvable

#### Expected outputs
- one indicator row per `(trade_date, ticker_id)` where implementation chooses to materialize
- invalid-state rows are explicit
- run/event evidence updated

#### Stop publish if
- mandatory indicator artifact is incomplete
- compute failure is unresolved
- dependency ambiguity remains

---

### `market-data:eod-eligibility:build`
#### Verify before continuing
- coverage universe basis is correct
- indicator artifact is ready enough to evaluate eligibility
- row cardinality expectation is known

#### Expected outputs
- exactly one eligibility row per universe ticker/date
- blocked rows carry registered reason codes
- row counts match expectation

#### Stop publish if
- eligibility artifact is missing
- row cardinality is broken
- reason-code semantics are ambiguous

---

### `market-data:audit:hash`
#### Verify before continuing
- bars/indicators/eligibility candidate artifacts are coherent
- no mixed-run artifact set is being hashed
- serialization inputs follow locked contract

#### Expected outputs
- three content hashes populated
- event trail records successful hash generation

#### Stop publish if
- any mandatory hash is missing
- serialization ambiguity exists
- identical-content replay unexpectedly produces different hash

---

### `market-data:dataset:seal`
#### Verify before continuing
- hash set exists
- candidate dataset is coherent
- run ownership is still valid
- seal preconditions are met

#### Expected outputs
- seal metadata written
- candidate dataset marked sealed in the locked publication sense

#### Stop publish if
- seal write fails
- candidate hash set is incomplete
- publication target is ambiguous

---

### `market-data:run:finalize`
#### Verify before continuing
- cutoff policy is satisfied
- seal exists if final success is to be committed
- effective-date resolution is clear
- prior publication safety is preserved

#### Expected outputs
- final terminal status
- effective trade date
- final event evidence

#### Stop publish if
- success would be unsealed
- effective-date resolution is ambiguous
- current publication state would become ambiguous

---

## Exit discipline (LOCKED)
- technical stage success without publish-readiness may still leave run `HELD` or non-final
- hard failure -> non-success run outcome
- no command may disguise partial publish as final readable `SUCCESS`

## Operator flow for daily run (LOCKED)
1. run `market-data:daily --latest`
2. inspect final run state
3. verify hash presence if candidate success path
4. verify seal exists before treating date as readable
5. if `HELD` or `FAILED`, confirm fallback safety to prior readable sealed date
6. preserve evidence for anomalies

## Locking and ownership rule (LOCKED)
- one requested date must have one active writer owner for publish-critical stages
- hash/seal/finalize must be executed by the owning run context
- correction/backfill must not silently override current readable publication