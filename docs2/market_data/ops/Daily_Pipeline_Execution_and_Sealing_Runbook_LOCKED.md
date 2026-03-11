# Daily Pipeline Execution and Sealing Runbook (LOCKED)

## Purpose
Ensure daily runs produce a sealed dataset so Watchlist PLAN reads a frozen input.

## Daily order (LOCKED)
1) Acquire + publish canonical EOD bars for requested date T
2) Compute indicators for T (indicator_set_version locked)
3) Build eligibility for effective date D
4) Finalize run status + effective date
5) Compute audit hashes
6) Seal dataset for effective date D

## Rule (LOCKED)
Watchlist PLAN may only use:
- trade_date_effective D that is SUCCESS
- and the dataset must be SEALED

If run is HELD/FAILED:
- effective date falls back
- watchlist must use the fallback D (and ideally show it)

## Sealing storage (LOCKED default)
Use fields on eod_runs (recommended):
- sealed_at DATETIME
- sealed_by VARCHAR(64)
- seal_note VARCHAR(255) (optional)

Alternative (allowed):
- separate eod_dataset_seals table

## Seal conditions (LOCKED)
- status is finalized
- eligibility exists for D
- hashes exist (bars/indicators/eligibility)

## Controlled correction
If provider correction happens for a sealed date:
- new run_id
- recompute indicators
- new hashes
- reseal
Old sealed dataset remains auditable via old run_id/hashes.

## Operator checklist (daily)
- run status SUCCESS?
- coverage_ratio ok?
- hashes non-null?
- sealed_at non-null?
If any missing => treat as not ready for Watchlist.