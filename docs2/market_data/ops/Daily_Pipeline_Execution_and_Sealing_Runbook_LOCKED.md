# Daily Pipeline Execution and Sealing Runbook (LOCKED)

## Purpose
Ensure daily runs produce a frozen upstream dataset that downstream consumers can read safely.

## Daily order (LOCKED)
1) acquire and publish canonical EOD bars for requested date T
2) compute indicators for T using locked semantics and version
3) build eligibility for requested date T
4) compute audit hashes for the candidate consumer-visible date
5) write seal metadata for that date only if all seal preconditions pass
6) finalize run status and resolve `trade_date_effective`
7) generate run artifacts after final status is committed

## Readiness rule (LOCKED)
Downstream consumers may only use:
- `trade_date_effective = D` that resolves to a finalized safe date
- and a dataset that is SEALED

If requested date is `HELD` / `FAILED` or unsealed:
- effective date falls back
- downstream readers must use fallback D

## Sealing storage (LOCKED default)
Recommended storage is on `eod_runs`:
- `sealed_at`
- `sealed_by`
- `seal_note`

## Controlled correction
If provider correction occurs for a sealed date:
- create new run
- recompute artifacts
- recompute hashes
- reseal
- preserve old run for audit

## Operator checklist
- final status appropriate?
- coverage ratio passes?
- hashes non-null?
- `sealed_at` non-null for consumable date?
If not, requested date is not ready.