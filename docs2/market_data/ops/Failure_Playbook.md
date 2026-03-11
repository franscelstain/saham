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

## Failure decision summary table

| Failure class | Retry allowed | Publish allowed | Expected terminal state | Keep prior current publication? |
|---|---|---|---|---|
| Source timeout (isolated) | Yes, limited | Only if gates still pass | `SUCCESS` or `HELD` | Yes |
| Source timeout (broad) | Yes, limited | Usually no | `HELD` | Yes |
| Source schema drift | No best-effort publish | No | `FAILED` | Yes |
| Coverage below threshold | Limited after diagnosis | No | `HELD` | Yes |
| Indicator compute failure | Only after root-cause fix/rerun | No | `FAILED` | Yes |
| Hash failure | Retry after validation/fix | No | `FAILED` or non-final | Yes |
| Seal write failure | Retry after diagnosis | No | `FAILED` or `HELD` | Yes |
| Finalize before cutoff | Wait/retry later | No early publish | non-final or `HELD` | Yes |
| Run lock conflict | Non-owner path no | No ambiguous publish | `FAILED` for conflicting path | Yes |
| Replay mismatch | N/A | Existing current publication may remain | operational defect, not auto publish change | Yes |
| Config drift | After diagnosis only | No silent publish change | depends on impact | Yes |
| Correction reseal failure | After diagnosis only | No correction publish | failed or non-published | Yes |
| Publication switch failure | After safe recovery only | No ambiguous current state | correction non-current | Yes |
| Session snapshot failure | Yes/skip allowed | EOD publish unaffected | snapshot-local only | Yes |

---

## 1. Source timeout / source unavailable

### Typical symptoms
- API timeout
- empty source response
- repeated acquisition retry exhaustion
- incomplete source payload retrieval

### Immediate checks
- verify retry policy outcome
- verify whether failure is ticker-scoped or broad
- inspect acquisition event trail and counts
- determine whether required coverage is still achievable

### Safe retry allowed?
Yes, within locked retry/backoff limits.

### Must hold publication?
Yes, if coverage or mandatory artifact completeness is in doubt.

### Must fail run?
Not always.
- isolated acquisition failure may still permit `SUCCESS`
- broad unrecoverable acquisition failure may lead to `HELD` or `FAILED`

### Can prior publication remain active?
Yes. This is the default safe posture.

### When to escalate
- repeated timeout across full universe
- retry exhaustion with major coverage loss
- unexpected source unavailability pattern

### Evidence to preserve
- source endpoint/source mode
- retry counts
- timeout event trail
- coverage estimate impact
- resulting terminal status decision

---

## 2. Source rate limit

### Typical symptoms
- response indicates throttling
- retries delayed by backoff
- partial acquisition due to request budget exhaustion

### Immediate checks
- verify throttle/backoff behavior
- confirm whether retry exhaustion occurred
- assess coverage impact
- inspect whether run should remain in progress, become `HELD`, or fail

### Safe retry allowed?
Yes, within rate-limit-safe retry policy.

### Must hold publication?
If required coverage/artifact completeness is threatened, yes.

### Must fail run?
Usually not immediately, unless there is a deeper source/config problem.

### Can prior publication remain active?
Yes.

### When to escalate
- rate-limit issue persists across retries and windows
- rate-limit pattern suggests credential/config misuse

### Evidence to preserve
- rate-limit response evidence
- retry exhaustion trail
- coverage impact
- status decision

---

## 3. Source response/schema drift

### Typical symptoms
- normalization/parsing errors
- required source fields missing unexpectedly
- source payload structure changed
- malformed payload count spikes

### Immediate checks
- confirm whether source contract changed
- stop trusting best-effort normalization
- compare current payload shape with locked source mapping contract

### Safe retry allowed?
Not as a blind retry for publication.
Retry may confirm diagnosis, but must not lead to guessed publish.

### Must hold publication?
At minimum yes, until trust is restored.

### Must fail run?
Usually yes.

### Can prior publication remain active?
Yes, and should remain active.

### When to escalate
Immediately.

### Evidence to preserve
- sample offending payload
- parse/normalize errors
- source mapping contract reference
- status decision and reason code

---

## 4. Partial coverage / coverage below threshold

### Typical symptoms
- canonical valid bars written for only part of the universe
- `coverage_ratio < COVERAGE_MIN`
- eligibility heavily blocked for missing bars

### Immediate checks
- verify numerator/denominator calculation
- inspect dominant reason-code distribution
- determine whether degradation is source-wide or localized

### Safe retry allowed?
Yes, if the missing coverage is expected to recover and retry policy allows it.

### Must hold publication?
Yes.

### Must fail run?
Not necessarily. `HELD` is typical.

### Can prior publication remain active?
Yes.

### When to escalate
- repeated coverage failure across dates
- denominator ambiguity
- unexplained systemic missing bars

### Evidence to preserve
- coverage ratio
- numerator/denominator basis
- dominant blocking reasons
- final hold/fail decision

---

## 5. Indicator compute failure

### Typical symptoms
- indicator job exception
- missing mandatory indicator rows
- invalid dependency bars causing compute abort
- indicator table incomplete for requested date

### Immediate checks
- inspect compute stage event trail
- verify whether mandatory indicator artifact is complete
- determine whether issue is warmup/expected invalidity vs true compute failure

### Safe retry allowed?
Only after root-cause is understood and the rerun path is safe.

### Must hold publication?
Yes, if mandatory indicator readiness is not proven.

### Must fail run?
Usually yes if mandatory indicator artifact is incomplete or compute crashed.

### Can prior publication remain active?
Yes.

### When to escalate
- compute bug suspected
- deterministic rerun still fails
- output inconsistency across identical inputs

### Evidence to preserve
- exception/log summary
- affected ticker/date scope
- invalid reason-code pattern
- run-level artifact completeness state

---

## 6. Hash computation failure

### Typical symptoms
- one or more content hashes are NULL
- serialization failed
- hash artifact generation aborted
- replay/hash proof mismatch for candidate publication

### Immediate checks
- verify artifact completeness first
- verify serialization contract inputs
- confirm no mixed-run row set is being hashed
- confirm formatting rules were not violated

### Safe retry allowed?
Yes, after diagnosis.

### Must hold publication?
Yes.

### Must fail run?
`FAILED` or remain non-final/held, but never final `SUCCESS`.

### Can prior publication remain active?
Yes.

### When to escalate
- repeated hash mismatch for same content
- runtime/locale drift suspected
- serialization order ambiguity suspected

### Evidence to preserve
- candidate row counts
- serialization inputs
- missing hash fields
- expected vs actual hash if comparison exists

---

## 7. Seal write failure

### Typical symptoms
- seal metadata missing
- seal write exception
- candidate run looks success-eligible but seal step failed

### Immediate checks
- confirm hashes exist
- confirm candidate artifact set is coherent
- confirm no publication switch occurred accidentally

### Safe retry allowed?
Yes, after diagnosing the seal failure path.

### Must hold publication?
Yes.

### Must fail run?
`FAILED` or `HELD`, but never final `SUCCESS`.

### Can prior publication remain active?
Yes.

### When to escalate
- repeated seal write failure
- seal metadata inconsistency
- evidence that publication switched despite failed seal

### Evidence to preserve
- run summary
- hash presence
- seal write error
- prior current publication state

---

## 8. Finalize before cutoff / cutoff policy violation

### Typical symptoms
- final success attempted too early
- requested date not yet permitted by cutoff contract
- stage sequencing bypassed timing rules

### Immediate checks
- verify cutoff policy and effective config
- verify current platform timezone interpretation
- confirm whether success was merely attempted or actually committed

### Safe retry allowed?
Yes, later, when cutoff condition is satisfied.

### Must hold publication?
Yes, until allowed timing is reached and all prerequisites remain valid.

### Must fail run?
Not necessarily; often non-final or `HELD`.

### Can prior publication remain active?
Yes.

### When to escalate
- repeated timing-rule bypass
- mismatch between config and actual behavior

### Evidence to preserve
- cutoff policy values
- timestamps
- finalization attempt evidence
- status before/after correction

---

## 9. Run ownership conflict / duplicate writer

### Typical symptoms
- two writers processing the same requested date
- hash/seal/finalize collision
- lock conflict or ownership-loss evidence

### Immediate checks
- identify active owner run
- identify which path is non-owner
- confirm no dual publication path exists

### Safe retry allowed?
Only for the proper owner or after safe recovery.

### Must hold publication?
Yes, until one coherent owner path is restored.

### Must fail run?
The conflicting non-owner path usually must fail.

### Can prior publication remain active?
Yes.

### When to escalate
- dual current publication risk
- repeated lock conflict
- ownership cannot be resolved cleanly

### Evidence to preserve
- run IDs involved
- ownership/lock evidence
- publication state before/after
- non-owner path disposition

---

## 10. Replay mismatch

### Typical symptoms
- replay result = `MISMATCH` or `UNEXPECTED`
- identical-input replay no longer reproduces prior expected hash/output
- degraded replay classification differs from expectation

### Immediate checks
- classify mismatch origin:
  - canonical row drift
  - indicator drift
  - eligibility drift
  - formatting drift
  - config drift
  - fixture expectation drift
- compare actual vs expected row outputs
- compare actual vs expected hashes

### Safe retry allowed?
Yes, for diagnosis.
Not a substitute for explanation.

### Must hold publication?
Existing production publication does not automatically need to change, but operational safety must be reassessed if mismatch indicates current reproducibility drift.

### Must fail run?
Replay mismatch itself is not a production run terminal status, but it is an operational defect.

### Can prior publication remain active?
Yes.

### When to escalate
- identical-input mismatch
- unexplained hash drift
- mismatch affecting current production semantics

### Evidence to preserve
- replay config identity
- expected vs actual summaries
- expected vs actual hashes
- mismatch classification

---

## 11. Config drift / wrong effective config

### Typical symptoms
- replay or rerun uses different config behavior unexpectedly
- run config identity missing or inconsistent
- output changed without intentional content-contract change

### Immediate checks
- inspect config identity linked to the run
- compare effective config snapshots
- determine whether change is expected and documented

### Safe retry allowed?
Only after config diagnosis.

### Must hold publication?
If requested-date output trust is uncertain, yes.

### Must fail run?
Depends on impact. Silent undocumented output drift is serious.

### Can prior publication remain active?
Yes.

### When to escalate
- config identity missing
- undocumented output-affecting change
- correction may be needed

### Evidence to preserve
- run config version/hash/reference
- expected vs actual config identity
- output difference summary

---

## 12. Historical correction reseal failure

### Typical symptoms
- correction run produced candidate changed output
- old publication exists
- new seal failed or correction validation failed

### Immediate checks
- confirm prior current publication still intact
- confirm correction did not switch current state
- inspect hash/seal/correction evidence bundle

### Safe retry allowed?
Yes, after diagnosis.

### Must hold publication?
Correction publication must remain non-current.

### Must fail run?
Correction candidate may fail or remain non-published.

### Can prior publication remain active?
Yes, and it should remain active.

### When to escalate
- correction evidence inconsistent
- publication switched despite failed reseal
- prior state not preserved

### Evidence to preserve
- correction request and approval
- old/new hash sets
- candidate seal failure evidence
- publication resolution state

---

## 13. Publication switch failure during correction

### Typical symptoms
- corrected run sealed successfully
- publication switch to new current state fails or becomes ambiguous
- old and new publication both appear current, or neither is clearly current

### Immediate checks
- verify single-current-publication invariant
- restore one and only one current publication state
- determine whether to keep old current or safely complete switch

### Safe retry allowed?
Yes, only through controlled publication-switch logic.

### Must hold publication?
Yes, if current state is ambiguous.

### Must fail run?
The correction publication should remain non-current until ambiguity is resolved.

### Can prior publication remain active?
Yes, and that is the safest default fallback.

### When to escalate
Immediately if dual-current or zero-current state appears.

### Evidence to preserve
- pre-switch state
- attempted switch evidence
- corrected run and prior run IDs
- final resolved current publication

---

## 14. Session snapshot failure

### Typical symptoms
- snapshot source timeout
- partial snapshot capture
- snapshot slot missed
- snapshot source error

### Immediate checks
- record snapshot failure reason
- confirm EOD sealed publication logic is unaffected
- decide retry vs skip according to snapshot policy

### Safe retry allowed?
Yes, if snapshot policy allows.

### Must hold publication?
No, not for EOD publication solely because of snapshot failure.

### Must fail run?
No, unless the failure is actually part of a larger EOD pipeline failure for another reason.

### Can prior publication remain active?
Yes.

### When to escalate
- repeated snapshot failure suggests source or scheduling issue
- snapshot unexpectedly affects EOD logic

### Evidence to preserve
- snapshot slot
- source failure reason
- whether scope was partial
- confirmation EOD readiness was unaffected

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