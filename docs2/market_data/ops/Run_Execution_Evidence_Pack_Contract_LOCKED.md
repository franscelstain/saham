# Run Execution Evidence Pack Contract (LOCKED)

## Purpose
Define the minimum evidence pack that must come from a real executed run, not just from proof-spec or illustrative examples.

This contract exists to move the platform from:
- proof design
to
- proof by execution

## Core rule (LOCKED)
At least one executed evidence pack must be preservable for each major operational class:

1. normal readable success
2. held or failed requested-date outcome
3. historical correction publication
4. replay match or replay mismatch
5. executed contract-test run summary

## Minimum executed evidence pack for one requested-date run
A real run evidence pack must include at minimum:
- real `run_summary.json`
- real `publication_manifest.json` or publication resolution output
- real hash values
- real `run_event_summary.json` or run-event trail reference
- real eligibility export or reason-code distribution
- real config identity
- real timestamps
- real terminal outcome

## Minimum executed correction evidence pack
Must include at minimum:
- real correction request reference
- real prior publication reference
- real new publication reference
- real old/new hash values
- real publication switch result
- real correction diff artifact

## Minimum executed replay evidence pack
Must include at minimum:
- real `replay_result.json` object
- real comparison result
- real config identity
- real actual hashes
- real mismatch or match summary

## Minimum executed test evidence pack
Must include at minimum:
- real test ID
- real fixture family
- real pass/fail result
- real assertion evidence for at least one layer:
  - row
  - run
  - hash
  - publication
  - replay

## Executed-evidence distinction (LOCKED)
Illustrative examples are useful, but do not count as executed evidence unless they are explicitly marked as:
- sourced from an actual run, replay, correction, or test execution
- carrying actual produced values
- not manually fabricated placeholders

## Recommended storage locations
Executed evidence may be preserved through:
- artifact storage
- versioned evidence examples
- exported JSON/CSV bundles
- release-ready evidence appendix

## Cross-contract alignment
This contract must remain aligned with:
- `Audit_Evidence_Pack_Contract_LOCKED.md`
- `Run_Artifacts_Format_LOCKED.md`
- `../tests/Executed_Proof_Admission_Criteria_LOCKED.md`
- examples folder contracts/examples

## Anti-ambiguity rule (LOCKED)
If a platform claims strong proof-by-execution but cannot show at least one real evidence bundle for the major operational classes above, then the proof layer is not fully saturated.