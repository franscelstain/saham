# EOD Eligibility Snapshot Contract (LOCKED)

## Output table
`eod_eligibility` with PK `(trade_date, ticker_id)` where `trade_date` is the effective date D.

Columns:
- `eligible` (1/0)
- `reason_code` (required if `eligible=0`; NULL if `eligible=1`)
- `asof_run_id`
- `created_at`

## Coverage rule (LOCKED)
There must be exactly one eligibility row per ticker in the coverage universe for D.
Consumers must not infer absence as ineligibility.

## Minimum eligibility rule (LOCKED)
`eligible=1` iff all conditions hold:
- canonical bar exists in `eod_bars(D, ticker)`
- indicator row exists in `eod_indicators(D, ticker)`
- indicator row has `is_valid=1`

Otherwise `eligible=0` with a registered reason code.

## Minimum reason codes
- `ELIG_MISSING_BAR`
- `ELIG_MISSING_INDICATORS`
- `ELIG_INVALID_BAR`
- `ELIG_INVALID_INDICATORS`
- `ELIG_INSUFFICIENT_HISTORY`
- `ELIG_SOURCE_ERROR`

## Determinism rule (LOCKED)
Eligibility must be built from upstream canonical artifacts only. No downstream policy filter may participate in this table.
