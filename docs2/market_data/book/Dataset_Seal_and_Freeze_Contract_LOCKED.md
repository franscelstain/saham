# Dataset Seal and Freeze Contract (LOCKED)

## Purpose
Freeze the published dataset for effective date D so downstream consumers read a stable, non-drifting upstream input.

## Seal preconditions (LOCKED)
Seal may be written only when all conditions hold:
- run is success-eligible for requested date T and candidate effective date D
- eligibility exists for D
- hashes exist for bars, indicators, and eligibility
- seal is being written by the same run context that produced the candidate artifacts

Seal is a precondition to final `SUCCESS`; it is not something written after an already-finalized `SUCCESS`.

## Freeze rule (LOCKED)
After seal:
- dataset for D must not change silently
- any correction requires controlled correction flow with new `run_id`, new hashes, and reseal
- prior sealed state must remain auditable

## Consumer dependency (LOCKED)
Any downstream consumer may read only SEALED datasets.
Unsealed datasets are `not ready` even if the run status is otherwise marked as technically complete.

## Anti-drift rule (LOCKED)
For one sealed effective date D, the content hashes, eligibility snapshot, and row set of bars/indicators used for publication must remain frozen.
Operational metadata may grow later through extra run events or operator notes, but the sealed content itself must not drift.