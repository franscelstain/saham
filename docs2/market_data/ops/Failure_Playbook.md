# Failure Playbook (LOCKED)

## Purpose
Provide the minimum operator-facing failure handling guide for Market Data Platform so common failure scenarios can be triaged and handled consistently without breaking upstream publication safety.

This playbook is operational guidance for upstream market-data production only.
It does not define downstream trading decisions.

## Core operational rules (LOCKED)
1. Consumer-readable publication requires a coherent sealed dataset.
2. A requested date must never be exposed as readable if required hash/seal/readiness conditions are not met.
3. Partial success of internal stages must not be misrepresented as final `SUCCESS`.
4. Historical correction must never overwrite prior sealed publication in place.
5. If safety is uncertain, preserve the prior current readable publication and fail or hold the new candidate state.

## Minimum triage questions
For any failure or anomaly, answer these first:
1. What requested trade date is affected?
2. Which stage failed or degraded?
3. Did the failure affect consumer-readable publication?
4. Is the current prior sealed publication still intact?
5. Is fallback behavior still safe?
6. Is this a simple rerun case, a hold/fail case, or a historical correction case?

---

## 1. Source timeout / source unavailable

### Typical symptoms
- API timeout
- empty source response
- repeated acquisition retry exhaustion
- incomplete source payload retrieval

### Immediate operator action
- verify retry policy outcome
- verify whether failure is ticker-scoped or broad
- inspect acquisition event trail and counts
- determine whether required coverage is still achievable

### Expected status behavior
- ticker-scoped isolated failures may still allow `SUCCESS` if coverage and all locked gates pass
- broader acquisition failure may lead to `HELD`
- unrecoverable acquisition/config failure may lead to `FAILED`

### Consumer safety rule
Do not publish requested date as readable unless all locked readiness conditions still pass.

### Additional notes
If optional fetch-failure tracking is implemented, affected tickers may receive `ELIG_FETCH_FAILURE`.

---

## 2. Source rate limit

### Typical symptoms
- response indicates throttling
- retries delayed by backoff
- partial acquisition due to request budget exhaustion

### Immediate operator action
- verify throttle/backoff behavior
- confirm whether retry exhaustion occurred
- assess coverage impact
- inspect whether run should remain in progress, become `HELD`, or fail

### Expected status behavior
- minor rate-limit impact may still end in `SUCCESS`
- material coverage degradation usually leads to `HELD`
- configuration/auth misbehavior disguised as rate limiting may require `FAILED`

### Consumer safety rule
Rate limit alone is not the decision.
Readiness depends on final artifact completeness and gates.

---

## 3. Source response/schema drift

### Typical symptoms
- normalization/parsing errors
- required source fields missing unexpectedly
- source payload structure changed
- malformed payload count spikes

### Immediate operator action
- stop trusting best-effort normalization
- confirm whether source contract changed
- record failure evidence
- prevent publication of ambiguous canonical output

### Expected status behavior
- usually `FAILED`
- do not silently continue with guessed mapping

### Consumer safety rule
Schema drift is a hard trust boundary.
Do not publish requested date from ambiguous normalization logic.

---

## 4. Partial coverage / coverage below threshold

### Typical symptoms
- canonical valid bars written for only part of the universe
- `coverage_ratio < COVERAGE_MIN`
- eligibility heavily blocked for missing bars

### Immediate operator action
- verify numerator/denominator calculation
- confirm whether degradation is source-wide or ticker-subset specific
- inspect dominant blocking reasons
- preserve prior readable publication

### Expected status behavior
- typically `HELD`
- do not seal requested date

### Consumer safety rule
Fallback to the latest prior sealed readable publication remains the safe path if available.

---

## 5. Indicator compute failure

### Typical symptoms
- indicator job exception
- missing mandatory indicator rows
- invalid dependency bars causing compute abort
- indicator table incomplete for requested date

### Immediate operator action
- inspect compute stage logs/events
- confirm whether failure is isolated or artifact-wide
- verify whether mandatory indicator artifact is complete
- avoid downstream readability assumptions

### Expected status behavior
- usually `FAILED`
- missing mandatory indicator artifact is incompatible with final readable success

### Consumer safety rule
Consumers must not infer readiness from bars alone if indicators are incomplete or failed.

---

## 6. Hash computation failure

### Typical symptoms
- one or more content hashes are NULL
- serialization failed
- hash artifact generation aborted
- replay/hash proof mismatch for candidate publication

### Immediate operator action
- verify artifact completeness first
- verify serialization contract inputs
- confirm no mixed-run row set is being hashed
- block seal/final success

### Expected status behavior
- `FAILED` or remain non-final/held
- never final `SUCCESS`

### Consumer safety rule
No hash -> no trusted sealed publication for the candidate dataset.

---

## 7. Seal write failure

### Typical symptoms
- seal metadata missing
- seal write exception
- candidate run looks success-eligible but seal step failed

### Immediate operator action
- confirm hashes exist
- confirm candidate artifact set is coherent
- do not finalize as `SUCCESS`
- preserve prior current readable publication

### Expected status behavior
- `FAILED` or remain `HELD`
- never final `SUCCESS`

### Consumer safety rule
An unsealed candidate dataset is not consumer-readable.

---

## 8. Finalize before cutoff / cutoff policy violation

### Typical symptoms
- final success attempted too early
- requested date not yet permitted by cutoff contract
- stage sequencing bypassed timing rules

### Immediate operator action
- verify cutoff policy and effective config
- stop early final success commit
- keep run non-final or held until timing rule permits completion

### Expected status behavior
- non-final or `HELD`
- never early final `SUCCESS`

### Consumer safety rule
Timing policy is part of publication safety, not mere scheduling preference.

---

## 9. Run ownership conflict / duplicate writer

### Typical symptoms
- two writers processing the same requested date
- hash/seal/finalize collision
- lock conflict or ownership-loss evidence

### Immediate operator action
- identify active owner run
- prevent double publication
- fail or abort the non-owner path
- confirm current publication state remains singular

### Expected status behavior
- conflicting path usually `FAILED`
- do not allow ambiguous current publication resolution

### Consumer safety rule
Only one coherent publication context may become current for a date.

---

## 10. Replay mismatch

### Typical symptoms
- replay result = `MISMATCH` or `UNEXPECTED`
- identical-input replay no longer reproduces prior expected hash/output
- degraded replay classification differs from expectation

### Immediate operator action
- identify whether mismatch is caused by:
  - canonical row drift
  - indicator drift
  - eligibility drift
  - formatting drift
  - config drift
  - fixture expectation drift
- compare actual vs expected hash and row outputs
- do not dismiss as harmless without explanation

### Expected status behavior
- replay mismatch does not automatically change production publication state
- but it is an operational defect until explained and resolved

### Consumer safety rule
If replay mismatch indicates reproducibility drift in current production logic, treat it as a serious contract concern.

---

## 11. Config drift / wrong effective config

### Typical symptoms
- replay or rerun uses different config behavior unexpectedly
- run config identity missing or inconsistent
- output changed without intentional content-contract change

### Immediate operator action
- inspect config identity linked to the run
- compare effective config snapshots
- determine whether output drift is expected or accidental
- preserve current safe publication until resolved

### Expected status behavior
- depends on stage and impact
- accidental config-driven output drift may require hold/fail or controlled correction

### Consumer safety rule
Undocumented config drift must never silently redefine current publication semantics.

---

## 12. Historical correction reseal failure

### Typical symptoms
- correction run produced candidate changed output
- old publication exists
- new seal failed or correction validation failed

### Immediate operator action
- keep prior current publication active
- record correction failure trail
- do not switch publication
- preserve old/new evidence for investigation

### Expected status behavior
- correction run may fail or remain non-published
- prior publication remains current

### Consumer safety rule
No half-published correction state is allowed.

---

## 13. Publication switch failure during correction

### Typical symptoms
- corrected run sealed successfully
- publication switch to new current state fails or becomes ambiguous
- old and new publication both appear current, or neither is clearly current

### Immediate operator action
- restore a single-current-publication state immediately
- prefer preserving prior current publication if ambiguity cannot be resolved safely
- record incident and switch evidence
- do not allow consumer reads against ambiguous publication selection

### Expected status behavior
- correction publication should remain non-current until single-current state is restored safely

### Consumer safety rule
Exactly one current publication must resolve for one effective trade date.

---

## 14. Session snapshot failure

### Typical symptoms
- snapshot source timeout
- partial snapshot capture
- snapshot slot missed
- snapshot source error

### Immediate operator action
- record snapshot failure reason
- confirm EOD sealed publication logic is unaffected
- retry or skip according to snapshot policy

### Expected status behavior
- snapshot failure must not decide EOD terminal status by itself

### Consumer safety rule
Session snapshot is supplemental upstream data, not a blocker for EOD readability.

---

## 15. When to escalate
Escalate when any of the following occurs:
- source schema drift
- hash reproducibility drift
- ambiguous publication state
- current publication cannot be resolved safely
- historical correction cannot preserve prior trail
- config drift changes canonical output unexpectedly
- repeated replay mismatch without clear explanation

---

## 16. Minimum evidence to preserve for every incident
For every meaningful failure or anomaly, preserve at minimum:
- requested trade date
- affected run ID
- stage at failure
- terminal or non-terminal status outcome
- dominant reason codes
- hash presence/absence
- seal presence/absence
- current publication state
- config identity
- correction linkage if applicable

---

## 17. Operator anti-shortcut rules (LOCKED)
Operators must not:
- force `SUCCESS` on an unsealed candidate
- overwrite prior sealed publication in place
- switch current publication based only on recency timestamp
- ignore replay mismatch without diagnosis
- use snapshot artifacts as a substitute for EOD readiness
- invent unregistered reason codes for blocked eligibility rows