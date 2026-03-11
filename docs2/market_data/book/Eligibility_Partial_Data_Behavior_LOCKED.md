# Eligibility Behavior on Partial Data (LOCKED)

Eligibility is a global minimum-readability gate, not a downstream ranking filter.

## Rules
- missing canonical bar => `eligible=0`, `ELIG_MISSING_BAR`
- provider row rejected as invalid => `eligible=0`, `ELIG_INVALID_BAR`
- missing indicator row => `eligible=0`, `ELIG_MISSING_INDICATORS`
- invalid indicator row => `eligible=0`, `ELIG_INVALID_INDICATORS`
- mandatory baseline indicator NULL because of warmup/insufficient history => `eligible=0`, `ELIG_INSUFFICIENT_HISTORY`
- known provider fetch failure covering ticker/date => `eligible=0`, `ELIG_SOURCE_ERROR`

## Run-level interaction
Requested date T may still become `SUCCESS` when per-ticker failures exist, provided coverage and other global gates pass.
Per-ticker ineligibility must not be hidden by omitting rows from `eod_eligibility`.
