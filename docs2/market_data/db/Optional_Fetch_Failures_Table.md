# Optional Fetch Failures Table

## Purpose
Define an optional per-ticker audit table for source-acquisition failures that occur during upstream data collection for trade date D.

This table is optional.
It exists to improve row-level explainability and diagnostics when some tickers fail source retrieval after retry exhaustion.

It is not a substitute for:
- run-level terminal status
- eligibility snapshot
- reason-code registry
- run events

## When to use
Use this optional table when the implementation wants to preserve per-ticker fetch-failure evidence such as:
- source timeout after retries
- source rate-limit exhaustion
- malformed per-ticker payload
- ticker-scoped source response failure

## Minimum semantic role
If this table is implemented:
- it records ticker-scoped source retrieval failure evidence
- it may feed eligibility blocking decisions for the affected ticker
- it must remain auditable and append-only for the relevant run context

## Recommended columns
Minimum recommended fields:
- `trade_date`
- `ticker_id`
- `run_id`
- `source`
- `failure_reason_code`
- `failure_note`
- `retry_count`
- `created_at`

## Reason-code rule (LOCKED)
If this table is implemented and a ticker cannot be safely produced because source acquisition failed, then the eligibility row for that ticker/date may use:
- `ELIG_FETCH_FAILURE`

This code must exist in the official reason-code registry and seed.

If the implementation chooses not to support `ELIG_FETCH_FAILURE`, then eligibility must still map to another valid registered blocking reason based on the artifact that ended up missing or invalid.

## Non-blocking-table rule
This table itself is optional.
Its absence must not break the rest of the market-data contracts.

However, if the table is implemented, its semantics must be consistent with:
- reason-code registry
- eligibility snapshot contract
- partial-data eligibility behavior

## Run-level distinction
Per-ticker fetch failures do not automatically force run-level `FAILED`.

Run-level terminal status still depends on:
- coverage thresholds
- required artifact existence
- quality gates
- hash/seal/finalization rules

Therefore:
- some per-ticker fetch failures may coexist with terminal `SUCCESS`
- broader fetch failure patterns may lead to `HELD` or `FAILED`

## Anti-ambiguity rule
Do not use this optional table as a hidden alternative to eligibility.
If the table is present, blocked ticker readability must still be reflected explicitly in the eligibility snapshot.