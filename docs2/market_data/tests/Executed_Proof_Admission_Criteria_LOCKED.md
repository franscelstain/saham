# Executed Proof Admission Criteria (LOCKED)

## Purpose
Define the minimum conditions under which a test, replay, or correction evidence artifact may be claimed as executed proof rather than proof-spec or example-only material.

## Core rule (LOCKED)
Executed proof requires:
- real execution identity
- real produced outputs
- real pass/fail or match/mismatch result
- traceable origin

## Required conditions

### For executed test proof
Must include:
- actual `test_id`
- actual fixture family
- actual execution result
- actual observed values or summaries

### For executed replay proof
Must include:
- actual `replay_id`
- actual trade_date
- actual comparison result
- actual produced hashes or mismatch evidence

### For executed correction proof
Must include:
- actual correction context
- actual old/new publication references
- actual old/new hashes
- actual switch result

## Prohibited weak claims
The following must not be described as executed proof:
- shape-only examples
- symbolic placeholder outputs
- manually constructed pseudo-pass outputs
- evidence without traceable execution identity

## Relationship to examples
Examples may still be kept in docs, but they must be distinguishable from executed proof artifacts.

## Cross-contract alignment
This contract must remain aligned with:
- `Test_Implementation_Guidance_LOCKED.md`
- `Run_Execution_Evidence_Pack_Contract_LOCKED.md`
- examples folder evidence docs

## Anti-ambiguity rule (LOCKED)
If an artifact can be mistaken for real executed proof when it is only illustrative, the proof labeling layer is incomplete.