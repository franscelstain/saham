# Evidence Archive

## Purpose
This folder stores archived actual execution evidence only.

It is separate from `../examples/`, which may contain illustrative or representative structures. This archive is a repository of produced evidence, not a normative owner of new domain behavior. Any behavioral rule referenced by archived evidence must trace back to the authoritative contracts in `../book/`, `../ops/`, `../tests/`, and other normative companion folders.

## Evidence classes
- `runs/` for actual executed run evidence
- `replays/` for actual executed replay evidence
- `corrections/` for actual executed correction evidence
- `tests/` for actual executed test evidence

## Admission rule
Artifacts in this folder must satisfy:
- real execution identity
- real produced values
- traceable origin
- no placeholder-only bundles

## Actual archived evidence only
The `evidence/` tree is reserved for archived actual evidence bundles.

The following are invalid under `evidence/`:
- future-dated artifacts
- fabricated or symbolic timestamps
- shape-only examples
- representative demo bundles
- redacted bundles that no longer preserve execution traceability

If a bundle is illustrative, simulated, representative, or otherwise non-admissible as actual archived proof, it must live under `../examples/`, not under `evidence/`.

## Required bundle metadata
Each bundle under `evidence/` must carry `evidence_admission.json` with at least:
- `bundle_type`
- `bundle_id`
- `actuality_status`
- `admission_status`
- execution identity such as `run_id`, `replay_id`, `correction_id`, or `test_id`
- `archived_at` or equivalent executed timestamp
- `traceability_ref`
- `source_contract_refs`
- `redaction_status`

## Evidence placement rule
Use this decision rule:
- actual + traceable + historically valid timestamp -> keep under `evidence/`
- illustrative/simulated/example-only/non-traceable -> move to `../examples/`

## Cross-contract alignment
See:
- `../ops/Archived_Actual_Execution_Evidence_Contract_LOCKED.md`
- `../ops/Executed_Run_Admission_Criteria_LOCKED.md`
- `../ops/Run_Execution_Evidence_Pack_Contract_LOCKED.md`
- `../tests/Executed_Proof_Admission_Criteria_LOCKED.md`
- `../examples/ARCHIVED_EVIDENCE_FOLDER_STRUCTURE_LOCKED.md`
