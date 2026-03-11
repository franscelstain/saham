# Historical Correction Runbook (LOCKED)

## Purpose
Provide the minimum operator flow for handling historical corrections safely, without silently mutating prior sealed upstream publications.

This runbook complements:
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `Commands_and_Runbook.md`
- `Historical_Replay_and_Data_Quality_Backtest.md`

## Operator goals
When correcting trade date D, the operator must ensure:
- the prior published sealed state remains preserved
- the corrected state is produced through a fresh run context
- corrected content is hashed and resealed
- the new publication becomes current only after validation
- consumers never read mixed old/new artifacts

## When this runbook must be used
Use this runbook when:
- a sealed historical date D contains incorrect canonical bars
- indicators for D were computed from invalid dependencies
- eligibility for D was wrong because upstream data or config was wrong
- symbol/ticker mapping or market calendar issues altered consumer-visible artifacts for D
- a prior publication for D must be replaced with a corrected publication

Do not use this runbook merely to add notes, logs, or non-consumer-visible audit metadata.

## Required preconditions
Before correction starts, the following must exist:
- identified target trade date `D`
- identified current publication for D
- correction reason documented
- approval recorded
- planned source of truth for corrected input documented
- operator confirmed this is a content correction, not just an audit rerun

## High-level flow (LOCKED)
1. create correction request
2. review and approve correction
3. identify current publication for D
4. execute correction run for D
5. rebuild bars -> indicators -> eligibility
6. recompute hashes
7. validate outputs versus expectation
8. reseal corrected dataset
9. mark corrected publication current
10. mark prior publication superseded
11. archive audit evidence

## Step-by-step operator flow

### Step 1 — Register correction request
Record at minimum:
- correction ID
- target trade date D
- reason
- scope of expected change
- requester
- request timestamp

### Step 2 — Approve correction
Approval must record:
- approver identity
- approval timestamp
- approval note
- whether downstream communication or maintenance note is required

No correction publication may proceed without approval.

### Step 3 — Identify current state
Before execution, identify and record:
- current published run for D
- current hashes for D
- current seal timestamp for D
- current publication version for D if applicable

This snapshot is the baseline for comparison after correction.

### Step 4 — Execute correction run
Run the normal upstream pipeline for D under a new run context:
- ingest corrected bars
- compute indicators
- build eligibility
- compute hashes
- validate gates

The operator must not mutate the old published state in place.

### Step 5 — Compare old vs new outputs
Minimum comparison:
- row counts
- reason-code counts
- hash set
- dominant changed rows/artifacts
- expected vs actual scope of change

If outputs are byte-identical and hashes are identical, treat as audit rerun, not a correction publication.

### Step 6 — Validate correction outcome
The operator must verify:
- required artifacts exist
- gates pass
- hashes exist
- new seal preconditions pass
- changed output is consistent with correction intent

### Step 7 — Reseal corrected output
Only after successful validation:
- write new seal metadata
- link seal to correction execution run
- preserve prior seal trail

### Step 8 — Publish corrected state
Mark corrected publication as current for D.
Mark prior current publication as superseded.

This publication switch must be atomic at the logical level: consumer reads must resolve to exactly one current publication.

### Step 9 — Archive evidence
Store or reference:
- correction request
- approval
- old hashes
- new hashes
- old publication reference
- new publication reference
- comparison result
- run summary
- seal evidence

## Decision outcomes

### Outcome A — Approved and published correction
Use when:
- content changed as intended
- all gates pass
- hashes exist
- reseal succeeded

Effect:
- new publication becomes current for D
- prior publication becomes superseded

### Outcome B — Approved execution but unchanged content
Use when:
- rerun completed
- content hashes unchanged
- no consumer-visible change occurred

Effect:
- keep current publication unchanged
- record audit rerun only

### Outcome C — Correction rejected
Use when:
- reason invalid
- proposed change unsupported
- evidence insufficient
- correction would violate upstream contracts

Effect:
- no new correction run becomes current

### Outcome D — Correction failed during execution
Use when:
- run failed
- hashes missing
- reseal failed
- outputs inconsistent with expected correction scope

Effect:
- keep prior current publication
- record failure trail
- do not partially publish

## Forbidden operator shortcuts (LOCKED)
Operators must not:
- directly edit sealed canonical rows in place
- overwrite historical hashes
- manually flip current publication without new seal evidence
- skip comparison and publish based on guess
- merge rows from old publication and new run
- publish correction because of timestamp recency alone

## Minimum evidence checklist
For every completed historical correction flow, ensure evidence exists for:
- correction request ID
- approval metadata
- target trade date D
- old current publication reference
- new execution run ID
- old hash set
- new hash set
- comparison result
- new seal evidence
- supersession relation
- final publication status

## Rollback rule (LOCKED)
If corrected publication switch cannot be completed safely:
- do not leave ambiguous current publication state
- keep prior current publication active
- record correction run as failed or non-published
- preserve all evidence for investigation

## Consumer safety check
Before final publication switch, verify:
- corrected state is sealed
- corrected state is internally coherent
- only one publication will resolve as current for D
- fallback behavior for other dates remains unaffected

## Replay proof requirement
Every correction flow should be replay-verifiable later through:
- old publication state
- corrected publication state
- supersession metadata
- old/new hash comparison