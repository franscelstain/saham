# Historical Correction and Reseal Contract (LOCKED)

## Purpose
Define the only allowed way to correct already-published historical upstream datasets without silently overwriting prior sealed content.

This contract exists to preserve:
- historical auditability
- consumer safety
- deterministic publication rules
- explicit supersession trail
- replay-verifiable correction evidence

This contract is upstream-only. It does not define downstream trading decisions, scoring, grouping, or signal behavior.

## Core principle (LOCKED)
A sealed dataset for trade date D must never be silently mutated in place.

Any material correction to the consumer-visible dataset for D must happen only through a controlled correction flow that produces:
- a new `run_id`
- a new content hash set
- a new seal event
- an explicit supersession trail
- preserved historical visibility of prior sealed publication state

## What counts as a correction
A correction exists when a rerun for trade date D changes any consumer-visible upstream artifact for D, including:
- canonical bars
- indicators
- eligibility snapshot
- effective-date consumer-visible publication state for D
- any content hash derived from those artifacts

## What does NOT count as a historical correction
The following do not, by themselves, constitute a historical correction:
- adding operator notes
- appending run events
- improving observability artifacts
- attaching extra audit references
- rerunning a date that produces byte-identical consumer-visible content and identical content hashes

If content is unchanged, the result is an audit rerun, not a correction publication.

## Controlled correction flow states (LOCKED)
Each correction request for trade date D must progress through explicit states:

1. `REQUESTED`
2. `APPROVED`
3. `EXECUTING`
4. `RESEALED`
5. `PUBLISHED`
6. `REJECTED`
7. `CANCELLED`

The exact implementation may use additional internal statuses, but these meanings must remain preserved.

## Correction request minimum metadata (LOCKED)
A correction request must record at minimum:
- correction identifier
- target trade date `D`
- reason category
- reason detail
- requested by
- requested at
- approval status
- approved by
- approved at
- target prior published run/seal reference
- target execution run reference once created
- correction outcome note

## Allowed correction reason classes
Examples:
- provider source row error discovered later
- late manual-source fix
- source mapping error
- ticker identity remap error
- market calendar error affecting windows
- config error that incorrectly affected canonical output
- compute implementation defect
- controlled backfill with verified historical source correction

## Forbidden behavior (LOCKED)
The following are forbidden:
- updating sealed consumer-visible rows for D in place without a new run context
- replacing prior hashes without preserving old hash trail
- changing current publication for D without an explicit supersession relation
- allowing consumer readers to mix old and corrected publication rows in the same logical read
- treating a failed correction attempt as the current publication
- allowing correction publication without new seal evidence

## Publication model for corrected dates (LOCKED)
For a trade date D, there may be multiple historical runs and multiple seal events over time, but only one publication state may be the current consumer-visible publication for D.

That current publication must be:
- explicitly identifiable
- linked to exactly one sealed run context
- replaceable only by a later controlled correction publication

## Supersession rule (LOCKED)
When a corrected publication becomes current for D:
- the newly sealed run becomes the current publication for D
- the previously current publication for D becomes superseded
- the prior publication remains queryable in audit/history views
- no historical publication record is deleted merely because it was superseded

## Consumer read rule (LOCKED)
Consumers must always read the current published sealed dataset for D, not an arbitrary historical run for D.

Consumers must not:
- select by latest `run_id`
- select by latest row `updated_at`
- select by arbitrary maximum timestamp
- merge rows across multiple sealed runs for the same D

Consumers must read a single coherent publication state for D.

## Relationship to effective-date fallback
Historical correction for D does not change the general consumer-readiness rule:
- consumers read only sealed datasets
- if requested date T is not readable, consumer fallback still resolves to the latest prior readable effective date
- if D itself later receives a controlled corrected publication, the current published sealed state for D becomes the readable dataset for D

## Reseal rule (LOCKED)
A correction publication for D requires a new seal event and a full recomputation of the content hash set for D.

At minimum, recompute and persist:
- `bars_batch_hash`
- `indicators_batch_hash`
- `eligibility_batch_hash`

The correction may be published only after:
- correction request is approved
- correction execution run completes all required artifacts
- all gates pass
- content hashes exist
- new seal is written

## Unchanged-content rerun rule (LOCKED)
If a rerun for D produces:
- identical consumer-visible content
- identical content hashes
- no change in publication semantics

then the rerun must not create a new corrected publication state.
It may be recorded as an audit rerun, but it must not supersede the current publication.

## Minimum data model requirements (LOCKED)
The schema must be able to represent at least:
- historical run identity
- current publication identity for a date
- superseded publication identity
- correction request trail
- correction-to-run linkage
- seal linkage
- hash linkage

Implementation patterns are allowed to vary, but the semantics above are mandatory.

## Minimum implementation patterns
Any one of the following is acceptable if semantics are preserved:

### Pattern A: run-linked publication
Use `eod_runs` with fields such as:
- `supersedes_run_id`
- `publication_version`
- `is_current_publication`

### Pattern B: separate publication table
Use a separate publication/seal table that records:
- `trade_date`
- `published_run_id`
- `publication_version`
- `is_current`
- `supersedes_publication_id`

### Pattern C: correction registry + publication table
Use:
- correction request table
- publication table
- run table
- seal table

The contract does not force one storage pattern, only the locked semantics.

## Historical replay requirement (LOCKED)
Replay and data-quality verification must be able to prove:
- original publication for D
- corrected publication for D
- prior publication preserved
- new publication current
- identical rerun without content change does not create fake correction publication

## Audit requirement (LOCKED)
For every corrected date D, it must be possible to answer:
1. what was the original current publication?
2. why was correction requested?
3. who approved it?
4. which new run executed the correction?
5. what changed in consumer-visible artifacts?
6. what were the old hashes?
7. what are the new hashes?
8. which publication is current now?
9. which publication was superseded?

## Anti-drift rule (LOCKED)
A correction must be explicit.
A later state that differs from a prior sealed publication without a correction trail is considered a contract violation.