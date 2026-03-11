# Historical Correction Runbook (LOCKED)

## Purpose
Provide the minimum operator flow for handling historical corrections safely, without silently mutating prior sealed upstream publications.

This runbook complements:
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `Commands_and_Runbook.md`
- replay and publication contracts

## Operator goals
When correcting trade date D, the operator must ensure:
- prior current publication remains preserved
- corrected state is produced through a fresh run context
- corrected content is hashed and resealed
- new publication becomes current only after validation
- consumers never read mixed old/new publication state

## When this runbook must be used
Use this runbook when:
- a sealed historical date D contains incorrect canonical bars
- indicators for D were computed from incorrect dependencies
- eligibility for D was wrong because upstream data or config was wrong
- symbol mapping, calendar logic, or config semantics changed consumer-visible output for D
- a prior publication for D must be replaced with a corrected publication

Do not use this runbook merely to:
- add notes
- append logs
- add non-consumer-visible audit references
- rerun a date that produces identical content and identical hashes

## Required preconditions
Before correction starts, the following must exist:
- identified target trade date D
- identified current publication for D
- correction reason documented
- approval recorded
- planned corrected source-of-truth path documented
- operator confirmed this is a content correction, not just an audit rerun

## High-level flow (LOCKED)
1. create correction request
2. approve correction
3. record current publication baseline
4. execute correction run
5. rebuild bars -> indicators -> eligibility
6. recompute hashes
7. validate candidate output
8. reseal candidate publication
9. switch publication only if safe
10. preserve evidence pack

---

## Step-by-step operator flow

### Step 1 — Register correction request
Record at minimum:
- correction ID
- target trade date D
- reason code / reason note
- scope of expected change
- requester
- request timestamp

### Step 2 — Approve correction
Approval must record:
- approver identity
- approval timestamp
- approval note
- whether downstream communication is needed

No correction publication may proceed without approval.

### Step 3 — Record baseline current publication
Before execution, record:
- current publication identity for D
- current publication version
- current run reference
- current hash set
- current seal timestamp

This baseline is mandatory comparison evidence.

### Step 4 — Execute correction run
Run the normal upstream pipeline for D under a new run context:
- ingest corrected bars
- compute indicators
- build eligibility
- compute hashes
- validate gates

Never mutate the old publication in place.

### Step 5 — Compare old vs new candidate
Minimum comparison must cover:
- row counts
- changed artifact scope
- old/new hash sets
- expected vs actual correction scope
- whether content is actually different

If outputs are byte-identical and hashes are identical:
- treat as audit rerun
- do not create a new publication
- do not increment publication version

### Step 6 — Validate correction candidate
Verify:
- required artifacts exist
- gates pass
- hashes exist
- seal preconditions pass
- changed output matches correction intent
- candidate publication is internally coherent

### Step 7 — Reseal corrected candidate
Only after validation:
- write new seal metadata
- link new seal to the correction execution run
- preserve prior seal and publication trail

### Step 8 — Publish corrected state
Switch current publication only if:
- new candidate is sealed
- publication switch can be performed without ambiguity
- prior publication will remain preserved and non-current

### Step 9 — Preserve correction evidence pack
Store or reconstruct:
- correction request
- approval
- baseline publication
- old/new hash sets
- comparison summary
- publication switch result
- final current publication resolution

---

## Pre-publication switch checklist (LOCKED)
Before switching current publication, verify all below are true:
- correction request is approved
- candidate run completed required artifacts
- candidate hashes exist
- candidate seal exists
- candidate output differs materially from prior current publication
- prior current publication baseline is recorded
- publication switch can produce exactly one current publication
- prior publication will remain preserved

If any item above is false:
- do not switch publication

## Post-publication switch checklist (LOCKED)
After switching current publication, verify:
- exactly one publication is current for D
- new publication is current
- prior publication is preserved and non-current
- publication version is correct
- correction evidence pack is complete
- downstream read model resolves the new current publication for D

## Failed correction recovery checklist (LOCKED)
If correction fails before safe publication:
- confirm prior current publication remains current
- confirm candidate correction is non-current
- confirm no ambiguous publication state exists
- preserve failure evidence
- decide whether to retry, reject, or cancel the correction request

---

## Decision outcomes

### Outcome A — Approved and published correction
Use when:
- content changed as intended
- all gates pass
- hashes exist
- reseal succeeded
- publication switch succeeded safely

Effect:
- new publication becomes current for D
- prior publication becomes superseded and audit-only

### Outcome B — Approved execution but unchanged content
Use when:
- rerun completed
- content hashes unchanged
- no consumer-visible change occurred

Effect:
- keep current publication unchanged
- record audit rerun only
- do not increase publication version

### Outcome C — Correction rejected
Use when:
- reason invalid
- evidence insufficient
- proposed change unsupported
- correction would violate locked upstream contracts

Effect:
- no new correction run becomes current

### Outcome D — Correction failed during execution or reseal
Use when:
- candidate run failed
- hashes missing
- reseal failed
- outputs inconsistent with expected correction scope
- publication switch could not be made safely

Effect:
- prior current publication remains current
- correction candidate remains non-current
- failure trail remains auditable

---

## Forbidden operator shortcuts (LOCKED)
Operators must not:
- directly edit current sealed publication rows in place
- overwrite historical hashes
- switch current publication based only on recency timestamp
- skip comparison and publish by assumption
- merge rows from old publication and new candidate
- publish correction without new seal evidence
- increment publication version for unchanged-content rerun

## Minimum evidence checklist
For every completed correction flow, preserve at minimum:
- correction request ID
- approval metadata
- target trade date D
- prior publication identity
- new execution run ID
- old hash set
- new hash set
- comparison result
- new seal evidence
- publication switch result
- final current publication resolution

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
- exactly one current publication will resolve for D
- fallback behavior for other dates remains unaffected

## Replay proof requirement
Every correction flow should later be replay-verifiable through:
- baseline publication state
- corrected publication state
- old/new hash comparison
- publication switch evidence
- preserved prior trail

## Anti-ambiguity rule (LOCKED)
If the correction flow cannot explain exactly which publication is current, which one was superseded, and why the switch was safe, then the correction is not complete.

## See also
- `../book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Failure_Playbook.md`
- `Run_Ownership_and_Recovery_LOCKED.md`
- `Run_Artifacts_Format.md`
- `Audit_Evidence_Pack_Contract_LOCKED.md`