# Performance SLO and Limits (LOCKED)

## Purpose
Define the minimum operational limits, time expectations, and alertable thresholds for Market Data Platform so the system is operationally transparent, not just functionally correct.

This document is upstream-operational only.
It does not define business SLAs to end users or trading-performance guarantees.

## Core principle (LOCKED)
SLO and limit values here are not cosmetic.
They define when a run is considered:
- timely
- stale
- degraded
- alert-worthy
- at risk of unsafe publication timing

## 1. Source acquisition limits

### Throttle spacing
Recommended request spacing for non-paid/public API acquisition:
- minimum throttle interval: `300 ms`
- maximum normal throttle interval: `800 ms`

### Retry policy
Maximum retries per acquisition attempt:
- `3`

### Retry backoff schedule
Recommended backoff sequence:
- retry 1: `2 seconds`
- retry 2: `5 seconds`
- retry 3: `12 seconds`

### Circuit breaker threshold
If source-error rate reaches:
- `25%` or more across the active acquisition window

then the source should be treated as degraded and operator visibility must increase.

## 2. Stale run thresholds

### Requested-date stale threshold
A run for requested date T should be considered stale if it remains non-terminal beyond:
- `60 minutes` after the expected EOD processing window opens for that date

### Non-owner stale lock threshold
A run ownership/lock should be considered suspect if:
- the owning run shows no meaningful event progress for `15 minutes` during active critical stages

This does not force takeover automatically, but must trigger inspection.

## 3. Seal latency targets

### Normal seal latency target
For one requested date T that passes gates successfully, target seal completion:
- within `15 minutes` after indicator and eligibility artifacts are complete

### Seal delay alert threshold
Alert-worthy if:
- candidate readable dataset remains unsealed for more than `30 minutes` after all success preconditions appear satisfied

## 4. Correction publication latency targets

### Approved correction execution target
Once a correction request for historical date D is approved, target completion of correction execution and evidence preparation:
- within `1 business day`

### Correction publication alert threshold
If approved correction remains in non-published execution state for more than:
- `2 business days`

then operator review/escalation is required.

## 5. Replay performance targets

### Normal replay target
For replay over one date or one small controlled fixture scenario:
- target completion within `15 minutes`

### Replay alert threshold
Replay becomes operationally concerning if:
- expected single-date or small-scope replay exceeds `30 minutes`
- or repeated replay mismatch remains unresolved for `2 consecutive investigation cycles`

## 6. Artifact completeness thresholds

### Required readable-publication completeness
A requested date may only become readable if all required artifact classes are present:
- bars
- indicators
- eligibility
- hashes
- seal/publication state

Required completeness for readable publication:
- `100%` of required artifact classes present

### Required held/failed evidence completeness
Even if requested date is not readable, minimum evidence should still exist for:
- run summary
- reason-code summary or equivalent anomaly explanation
- lifecycle/terminal state
- publication/fallback implication

Target evidence completeness:
- `100%` of minimum evidence classes present

## 7. Coverage thresholds
Coverage threshold itself is controlled elsewhere by config/contracts, but operationally:

### Coverage alert
If actual coverage ratio approaches the locked minimum by less than:
- `2 percentage points`

the run should be marked operationally fragile even if it still passes.

### Coverage failure
If actual coverage falls below the locked minimum:
- requested date must not become readable
- fallback logic applies

## 8. Publication safety alert conditions
Immediate operator visibility is required if any of the following occurs:
- hash missing when candidate publication appears success-eligible
- seal missing after success preconditions appear satisfied
- ambiguous current publication state
- replay mismatch on identical controlled inputs
- config identity missing for a run that produced candidate output
- correction candidate appears current without explicit switch evidence

## 9. Minimum alertable conditions
At minimum, the platform should surface an operator-visible alert when:
- source schema drift detected
- broad source timeout/retry exhaustion occurs
- coverage below threshold
- indicator compute hard failure
- hash computation failure
- seal write failure
- ambiguous publication switch
- current publication cannot be resolved uniquely
- replay mismatch classified as `MISMATCH` or `UNEXPECTED`

## 10. Session snapshot limits
Session snapshot is supplemental, but still should have minimum expectations.

### Snapshot lateness tolerance
Allowed slot tolerance:
- `15 minutes`

### Snapshot failure handling
Snapshot failure is not an EOD publication blocker by itself, but repeated snapshot failure should still be operator-visible.

## 11. Escalation timing guidance
Recommended escalation timing:
- source schema drift: immediate
- ambiguous publication state: immediate
- hash reproducibility drift: immediate
- approved correction stuck > 2 business days: escalate
- stale owner lock > 15 minutes with no progress: investigate immediately
- non-terminal requested-date run > 60 minutes beyond expected processing window: investigate immediately

## 12. Cross-contract alignment
This file must remain aligned with:
- `Failure_Playbook_LOCKED.md`
- `Commands_and_Runbook_LOCKED.md`
- `Historical_Correction_Runbook_LOCKED.md`
- `Audit_Evidence_Pack_Contract_LOCKED.md`
- readiness/seal/publication contracts

## Anti-ambiguity rule (LOCKED)
If a run can become stale, degraded, or operationally unsafe without crossing any defined threshold in this file, then the SLO/limits layer is too weak and must be hardened further.