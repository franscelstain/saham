# EOD Eligibility Snapshot Contract (LOCKED)

## Output table
eod_eligibility with PK (trade_date, ticker_id) where trade_date is effective date.

Columns:
- eligible (1/0)
- reason_code (required if eligible=0)
- asof_run_id
- created_at

Eligibility rules (LOCKED minimum):
eligible=1 iff:
- bar exists for (D,ticker)
- indicator exists for (D,ticker)
- indicators is_valid=1

Reason code minimum:
ELIG_MISSING_BAR
ELIG_MISSING_INDICATORS
ELIG_INVALID_BAR
ELIG_INVALID_INDICATORS
ELIG_INSUFFICIENT_HISTORY
ELIG_PROVIDER_ERROR