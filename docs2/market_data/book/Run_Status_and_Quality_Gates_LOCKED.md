# Run Status and Quality Gates (LOCKED)

## Purpose
Define the minimum run-state model, quality-gate model, and publishability model so Market Data Platform can distinguish:
- execution progress
- terminal outcome
- gate evaluation
- consumer readability

This contract exists so implementation does not collapse too many meanings into one status field.

## Core state dimensions (LOCKED)
A run must be understandable across four distinct dimensions:

1. lifecycle state
2. terminal status
3. quality gate state
4. publishability state

These may be stored as separate columns or equivalent normalized state model, but the meanings must remain distinct.

---

## 1. Lifecycle state

### Meaning
Execution progression state of the run itself.

### Minimum lifecycle values
- `PENDING`
- `RUNNING`
- `FINALIZING`
- `COMPLETED`
- `FAILED`
- `CANCELLED`

### Interpretation
- `PENDING`: run created but active processing not yet started
- `RUNNING`: active processing in progress
- `FINALIZING`: artifact generation completed enough to evaluate final state, but final commit/readability resolution not yet closed
- `COMPLETED`: execution path completed
- `FAILED`: execution path failed hard
- `CANCELLED`: run intentionally cancelled and not publishable

Lifecycle state is not the same as consumer readability.

---

## 2. Terminal status

### Meaning
Consumer-facing terminal outcome for the requested date context.

### Allowed terminal values
- `SUCCESS`
- `HELD`
- `FAILED`

### Interpretation
- `SUCCESS`: requested/effective date is readable according to publication rules
- `HELD`: requested date is not readable, but fallback to prior readable date may keep consumers operational
- `FAILED`: required stage failed or mandatory artifact is unusable

Terminal status is not the same as lifecycle state.

Example:
- lifecycle may be `COMPLETED`
- terminal status may still be `HELD`

---

## 3. Quality gate state

### Meaning
Evaluation result of the locked quality/readiness gates for the candidate artifact set.

### Minimum gate-state values
- `PENDING`
- `PASS`
- `FAIL`
- `BLOCKED`

### Interpretation
- `PENDING`: not yet evaluated or not yet fully evaluable
- `PASS`: all required gates passed
- `FAIL`: one or more required gates failed
- `BLOCKED`: evaluation could not complete because a required prerequisite artifact was missing or invalid

### Minimum gates (LOCKED)
At minimum, gates must cover:
1. canonical bar artifact exists
2. indicator artifact exists
3. eligibility artifact exists
4. coverage threshold passes
5. required hashes exist before readable success
6. seal exists before readable success
7. publication resolution is unambiguous
8. cutoff/finalization timing policy is satisfied

---

## 4. Publishability state

### Meaning
Whether the candidate state is consumer-readable.

### Minimum publishability values
- `NOT_READABLE`
- `READABLE`

### Interpretation
- `NOT_READABLE`: candidate or requested-date state must not be read by consumers
- `READABLE`: a current sealed publication exists and may be read by consumers

Publishability is the direct consumer-readability layer.

---

## Terminal-outcome mapping rules (LOCKED)

### SUCCESS
A run may resolve to terminal `SUCCESS` only if:
- lifecycle state has completed relevant execution successfully
- quality gate state = `PASS`
- publishability state = `READABLE`
- hashes exist
- seal exists
- current publication is resolved unambiguously

### HELD
A run should resolve to terminal `HELD` when:
- requested date is not readable
- prior readable fallback may still exist
- lifecycle may have completed or partially completed
- publishability state remains `NOT_READABLE`

Typical causes:
- coverage below threshold
- source degradation
- candidate not safely publishable

### FAILED
A run should resolve to terminal `FAILED` when:
- required stage failed hard
- mandatory artifact missing or unusable
- candidate cannot be trusted for publication
- publishability state remains `NOT_READABLE`

Typical causes:
- compute failure
- source schema drift
- hash failure with no safe continuation
- seal failure with no safe publish path

---

## Stage model
Stage remains useful as a separate execution-location indicator, for example:
- `INGEST_BARS`
- `PUBLISH_BARS`
- `COMPUTE_INDICATORS`
- `BUILD_ELIGIBILITY`
- `HASH`
- `SEAL`
- `FINALIZE`

Stage tells *where* the run is or failed.
Lifecycle/terminal/gate/publishability tell *what that means*.

## Minimum state examples

### Example A — Normal readable success
- lifecycle state: `COMPLETED`
- terminal status: `SUCCESS`
- quality gate state: `PASS`
- publishability state: `READABLE`

### Example B — Held requested date with safe fallback
- lifecycle state: `COMPLETED`
- terminal status: `HELD`
- quality gate state: `FAIL`
- publishability state: `NOT_READABLE`

### Example C — Hard compute failure
- lifecycle state: `FAILED`
- terminal status: `FAILED`
- quality gate state: `BLOCKED` or `FAIL`
- publishability state: `NOT_READABLE`

### Example D — Candidate ready except seal missing
- lifecycle state: `FINALIZING`
- terminal status: not yet final or eventually `FAILED` / `HELD`
- quality gate state: `BLOCKED`
- publishability state: `NOT_READABLE`

This state must never be treated as readable success.

## Consumer rule
Consumers do not interpret raw lifecycle/gate internals directly.
Consumers rely on:
- terminal status
- effective trade date
- publishability/current publication resolution
- seal state

But the system must still preserve internal states to avoid ambiguity.

## Required cross-contract alignment
This contract must remain aligned with:
- `Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `../ops/Failure_Playbook_LOCKED.md`
- `../db/Database_Schema_Contracts_MariaDB.md`

## Anti-ambiguity rule (LOCKED)
If execution progress, gate evaluation, and consumer readability cannot be distinguished cleanly, then the run-state model is too compressed and must not be treated as sufficient.