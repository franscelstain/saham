# Dataset Seal and Freeze Contract (LOCKED)

## Purpose
Freeze the published dataset for effective date D so downstream consumers read a stable, non-drifting upstream input.

## Seal preconditions (LOCKED)
Seal may be written only when all conditions hold:
- run is finalized
- eligibility exists for D
- hashes exist for bars, indicators, and eligibility

## Freeze rule (LOCKED)
After seal:
- dataset for D must not change silently
- any correction requires controlled correction flow with new `run_id`, new hashes, and reseal
- prior sealed state must remain auditable

## Consumer dependency (LOCKED)
Any downstream consumer may read only SEALED datasets.
Unsealed datasets are `not ready` even if the run status is otherwise `SUCCESS`.
