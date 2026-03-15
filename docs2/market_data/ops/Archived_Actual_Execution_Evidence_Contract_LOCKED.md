# Archived Actual Execution Evidence Contract (LOCKED)

## Purpose
Define the minimum archived evidence set that must come from real executions, not illustrative examples, so proof-by-execution is preserved as auditable artifacts.

This contract exists to distinguish:
- example evidence shape
from
- archived actual execution evidence

## Core rule (LOCKED)
A platform claiming strong operational proof should preserve archived actual execution evidence for at least the following classes:

1. one normal readable success run
2. one held or failed requested-date run
3. one historical correction publication
4. one replay execution
5. one executed contract-test result

Illustrative examples do not satisfy this requirement by themselves.

## Required archived evidence classes

### A. Archived actual run evidence
Minimum contents:
- actual `run_summary.json`
- actual `publication_manifest.json` or publication resolution artifact
- actual hash values
- actual `run_event_summary.json` or reference
- actual config identity
- actual timestamps

### B. Archived actual correction evidence
Minimum contents:
- actual correction request reference
- actual prior publication reference
- actual new publication reference
- actual correction diff artifact
- actual old/new hash values
- actual publication switch result

### C. Archived actual replay evidence
Minimum contents:
- actual `replay_result.json` artifact
- actual comparison result
- actual config identity
- actual hash outputs
- actual mismatch or match summary

### D. Archived actual test execution evidence
Minimum contents:
- actual test ID
- actual fixture family
- actual pass/fail result
- actual assertion evidence for one or more applicable layers

## Archived evidence admission rule (LOCKED)
An artifact counts as archived actual execution evidence only if:
1. it contains real execution identifiers
2. it contains real produced values
3. it is traceable to an actual execution context
4. it is stored in the designated archived evidence area or equivalent official evidence repository

## Minimum retention expectation
At least one archived actual evidence bundle should be preserved for each major class above.

If the platform evolves, additional archived bundles are encouraged, but these minimum classes should never drop to zero.

## Relationship to examples
Files in `../examples/` may:
- show expected shape
- show illustrative structure
- show redacted or representative bundles

But archived actual execution evidence should exist separately from examples and be clearly labeled as actual.

## Recommended archive locations
Archived actual evidence may be stored in a folder such as:
- `../evidence/runs/`
- `../evidence/replays/`
- `../evidence/corrections/`
- `../evidence/tests/`

or an equivalent official evidence repository.

## Cross-contract alignment
This contract must remain aligned with:
- `Run_Execution_Evidence_Pack_Contract_LOCKED.md`
- `Executed_Run_Admission_Criteria_LOCKED.md`
- `../tests/Executed_Proof_Admission_Criteria_LOCKED.md`
- executed evidence examples
- audit evidence pack contracts

## Anti-ambiguity rule (LOCKED)
If a platform claims proof-by-execution but stores only examples and no archived actual evidence bundles, the claim is overstated.