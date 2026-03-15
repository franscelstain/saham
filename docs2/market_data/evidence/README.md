# Evidence Archive

## Purpose
This folder stores archived actual execution evidence.

It is separate from `../examples/`, which may contain illustrative or representative structures. This archive is a repository of produced evidence, not a normative owner of new domain behavior. Any behavioral rule referenced by archived evidence must trace back to the authoritative contracts in `../book/`, `../ops/`, `../tests/`, and other normative companion folders.

## Evidence classes
- `runs/` for actual executed run evidence
- `replays/` for actual executed replay evidence
- `corrections/` for actual executed correction evidence
- `tests/` for actual executed test evidence

## Admission rule
Artifacts in this folder should satisfy:
- real execution identity
- real produced values
- traceable origin
- no placeholder-only bundles

See:
- `../ops/Archived_Actual_Execution_Evidence_Contract_LOCKED.md`
- `../ops/Executed_Run_Admission_Criteria_LOCKED.md`
- `../tests/Executed_Proof_Admission_Criteria_LOCKED.md`
- `../examples/ARCHIVED_EVIDENCE_FOLDER_STRUCTURE_LOCKED.md`