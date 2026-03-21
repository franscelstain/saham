# Archived Evidence Folder Structure (LOCKED)

## Purpose
Show the recommended structure for storing archived actual execution evidence separately from illustrative examples.

This file describes archived actual evidence organization, not example-only files.

## Recommended top-level structure

    evidence/
      runs/
      replays/
      corrections/
      tests/

## Recommended structure by class

### A. Runs

    evidence/
      runs/
        run_8124/
          run_summary.json
          publication_manifest.json
          eligibility_export.csv
          run_event_summary.json
          anomaly_report.md

### B. Replays

    evidence/
      replays/
        replay_3001/
          replay_result.json
          replay_reason_code_counts.json
          mismatch_summary.json

### C. Corrections

    evidence/
      corrections/
        correction_9001/
          correction_request.json
          correction_diff.json
          prior_publication_manifest.json
          new_publication_manifest.json
          publication_switch_result.json

### D. Tests

    evidence/
      tests/
        hash_same_content_same_hash/
          test_result.json
          actual_hashes.json
          expected_hashes.json

## Required labeling
Archived actual evidence artifacts should clearly indicate:
- source execution identity
- actual execution timestamp
- whether values are real or redacted
- whether redaction changed semantic meaning

## Redaction rule
Sensitive values may be redacted if needed, but:
- structural meaning must remain intact
- execution identity must remain clear enough for audit
- placeholder-only bundles do not qualify as archived actual evidence

## Relationship to examples
The `./` folder may still contain:
- illustrative shapes
- representative examples
- template bundles

But archived actual evidence should live in a distinct archive area such as `../evidence/`.

## Cross-contract alignment
This file must remain aligned with:
- `../ops/Archived_Actual_Execution_Evidence_Contract_LOCKED.md`
- `../ops/Run_Execution_Evidence_Pack_Contract_LOCKED.md`
- `../ops/Executed_Run_Admission_Criteria_LOCKED.md`
- `../tests/Executed_Proof_Admission_Criteria_LOCKED.md`

## Anti-ambiguity rule (LOCKED)
If archived actual evidence and illustrative examples are mixed without clear distinction, the proof layer becomes misleading.
## Canonical artifact-name rule
- `run_summary.json`, `publication_manifest.json`, `run_event_summary.json`, `correction_evidence.json`, and `replay_result.json` are the canonical artifact filenames used by this domain when those artifacts are materialized as files.
- Equivalent storage mechanisms are allowed, but companion documentation must not invent alternate canonical filenames for the same artifact family.
