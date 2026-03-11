# Run Artifacts Format (LOCKED)

## Purpose
Define the minimum operator-facing artifacts generated per requested date T so a finalized or held run can be audited without querying raw tables manually.

## Minimum artifacts
### 1) run summary JSON
Must be generated for every terminal run outcome (`SUCCESS`, `HELD`, `FAILED`).
Minimum fields:
- `run_id`
- `trade_date_requested`
- `trade_date_effective`
- `status`
- `final_stage`
- `source`
- `coverage_ratio`
- `bars_rows_written`
- `indicators_rows_written`
- `eligibility_rows_written`
- `invalid_bar_count`
- `invalid_indicator_count`
- `warning_count`
- `hard_reject_count`
- `bars_batch_hash`
- `indicators_batch_hash`
- `eligibility_batch_hash`
- `seal_state`
- `sealed_at`
- `config_identity` (version/hash or immutable reference)
- `started_at`
- `finished_at`

### 2) eligibility export
- format: CSV
- scope: only `eod_eligibility` rows for resolved effective date D
- minimum columns: `trade_date,ticker_id,eligible,reason_code`
- generated only when eligibility artifact exists

### 3) invalid-bar sample export
- format: CSV
- scope: requested date T invalid-bar audit rows
- minimum columns: `trade_date,ticker_id,source,source_row_ref,invalid_reason_code`
- may be full export or bounded sample, but summary JSON must state which mode was used

### 4) anomaly report
- machine-readable or markdown/plaintext summary allowed
- must summarize gate failures, dominant reason-code counts, and whether consumer fallback was expected

## Locked artifact rules
- artifacts are audit companions; they must not redefine terminal status outside `eod_runs`
- no secrets, tokens, or credentials may appear in artifacts
- content hashes shown in artifacts must exactly match persisted run hashes
- artifact timestamps must use platform timezone consistently
- if a run is `SUCCESS`, summary artifact must show seal state as `SEALED`
- if a run is `HELD` or `FAILED`, artifact must never imply consumer-readiness for requested date T