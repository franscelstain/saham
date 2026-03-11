# Historical Correction and Reseal Contract (LOCKED)

## Purpose
Define the only allowed way to correct an already-published historical upstream dataset without silently mutating prior sealed content.

This contract preserves:
- historical auditability
- consumer safety
- deterministic publication rules
- explicit supersession trail
- replay-verifiable correction evidence

This contract is upstream-only.
It does not define downstream scoring, ranking, grouping, or signal behavior.

## Core principle (LOCKED)
A sealed publication for trade date D must never be silently mutated in place.

Any material correction to the consumer-readable upstream dataset for D must happen only through a controlled correction flow that produces:
- a new run context
- a new content hash set
- a new seal event
- an explicit supersession relation
- preserved historical visibility of prior published state

## What counts as a correction
A correction exists when a rerun for trade date D changes any consumer-visible upstream artifact for D, including:
- canonical bars
- indicators
- eligibility snapshot
- content hashes derived from those artifacts
- current publication state for D

## What does not count as a correction
The following do not by themselves create a correction publication:
- appending operator notes
- appending run events
- adding non-consumer-visible audit evidence
- rerunning a date and producing byte-identical consumer-visible content and identical hashes

If content is unchanged, the result is an audit rerun, not a new published correction.

## Controlled correction flow states (LOCKED)
At minimum, a correction request must progress through explicit states:
- `REQUESTED`
- `APPROVED`
- `EXECUTING`
- `RESEALED`
- `PUBLISHED`
- `REJECTED`
- `CANCELLED`

Implementation may have extra internal states, but these meanings must remain preserved.

## Correction request minimum metadata
A correction request must record at minimum:
- correction identifier
- target trade date D
- reason category / reason code
- reason detail
- requested by / requested at
- approval metadata
- target prior current publication reference
- target execution run reference once created
- final correction outcome note

## Publication resolution rule after correction (LOCKED)
For one trade date D:
- multiple historical publications may exist over time
- only one publication may be current
- only the current sealed publication is consumer-readable
- superseded publications are audit-only

When a corrected publication becomes current:
- the newly sealed corrected publication becomes the only readable state for D
- the prior current publication becomes superseded
- the prior publication remains queryable for audit and replay

## Publication version rule (LOCKED)
A publication version for D may increase only when:
- consumer-visible content for D has changed in a materially relevant way
- a new seal has been written
- the corrected publication is formally published as a new current state

A publication version must not increase merely because:
- the run is newer
- the rerun occurred later in time
- extra audit notes were added
- unchanged content was recomputed

## Unchanged-content rerun rule (LOCKED)
If a rerun for D produces:
- identical consumer-visible content
- identical content hashes
- no change in publication semantics

then:
- it must not create a new corrected publication
- it must not increment publication version
- it may be recorded as an audit rerun only

## Supersession rule (LOCKED)
When a corrected publication becomes current for D:
- new current publication must explicitly supersede prior current publication
- prior publication must remain queryable
- prior publication must not remain current
- silent in-place replacement is forbidden

## Consumer read rule (LOCKED)
Consumers must always read the current sealed publication for D.
They must not:
- select by latest `run_id`
- select by latest timestamp
- merge rows across multiple publications
- select superseded publication for normal read flow

## Reseal rule (LOCKED)
A correction publication for D requires:
- new coherent consumer-visible artifact set
- recomputed content hashes
- new seal event
- successful validation before publication switch

At minimum recompute:
- `bars_batch_hash`
- `indicators_batch_hash`
- `eligibility_batch_hash`

## Minimum comparison evidence for correction (LOCKED)
Every correction candidate must preserve enough evidence to compare:
- prior current publication reference
- new candidate publication reference
- old hash set
- new hash set
- changed artifact scope
- approval metadata
- reseal result
- publication switch result

## Minimum questions correction evidence must answer
1. What was the old current publication?
2. What changed?
3. What were the old hashes?
4. What are the new hashes?
5. Did publication version increase?
6. Did publication switch happen?
7. Is the prior publication preserved?
8. Which publication is current now?

## Failed correction rule (LOCKED)
If correction execution fails before safe publication:
- prior current publication remains current
- candidate correction must not become readable
- partial candidate state must remain non-current
- evidence of failure must remain auditable

## Historical replay requirement (LOCKED)
Replay and data-quality verification must be able to prove:
- original publication for D
- corrected publication for D
- prior publication preserved
- new current publication resolved correctly
- unchanged rerun does not create fake correction publication

## Audit requirement (LOCKED)
For every corrected date D it must be possible to answer:
- why correction was requested
- who approved it
- what prior publication was current
- what new run executed the correction
- what changed
- which publication is current now
- which publication was superseded

## Anti-drift rule (LOCKED)
If a later readable state for D differs from a prior sealed publication without explicit correction trail, supersession trail, and reseal evidence, it is a contract violation.

## See also
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `../ops/Historical_Correction_Runbook_LOCKED.md`
- `../ops/Audit_Evidence_Pack_Contract_LOCKED.md`