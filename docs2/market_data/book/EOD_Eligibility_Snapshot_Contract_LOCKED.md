# EOD Eligibility Snapshot Contract (LOCKED)

## Purpose
Define the authoritative eligibility snapshot produced by Market Data Platform for one trade date D.

This snapshot is an upstream readiness artifact for consumers.
It does not encode ranking, scoring, picks, or trading decisions.

## Output definition
For each trade date D, the platform must produce exactly one live current eligibility row per coverage-universe ticker for D.

Minimum fields:
- `trade_date`
- `ticker_id`
- `publication_id`
- `eligible`
- `reason_code`
- `run_id`

## Row cardinality rule (LOCKED)
For one trade date D in the live current readable table:
- every ticker in coverage universe for D must have exactly one eligibility row
- tickers outside coverage universe for D must not appear in the eligibility snapshot for D
- `publication_id` must be populated on every live current row

`publication_id` is mandatory publication context for the current readable row set, but it is not a second competing primary key for the live current table.
Historical publication-bound snapshots belong in `eod_eligibility_history` or equivalent audit storage.

## Eligibility meaning
- `eligible = 1` means the ticker is readable for downstream consumers under upstream readiness rules
- `eligible = 0` means the ticker is not readable for downstream use on D and must carry a blocking `reason_code`

## Upstream-only rule (LOCKED)
Eligibility here means upstream dataset readiness only.
It must not be interpreted as:
- a buy/sell signal
- a ranking result
- a watchlist group
- a strategy approval

## Minimum blocking reasons (LOCKED)
Use only reason codes that exist in the official reason-code registry.

Minimum standard blocking reasons:
- `ELIG_MISSING_BAR`
- `ELIG_MISSING_INDICATORS`
- `ELIG_INVALID_INDICATORS`
- `ELIG_INSUFFICIENT_HISTORY`
- `ELIG_UNIVERSE_DEPENDENCY_MISSING`
- `ELIG_FETCH_FAILURE` when optional per-ticker fetch-failure tracking is implemented and the ticker could not be built safely from source acquisition failure

## Validity rules
A ticker may be `eligible = 1` only if all required upstream conditions hold for D:
- ticker is in coverage universe for D
- canonical valid bar exists for D
- mandatory indicators exist for D
- mandatory indicators are valid
- no blocking fetch-failure condition applies
- no locked rule denies readiness for that ticker/date

## Missing-bar rule
If a coverage-universe ticker has no canonical valid bar for D:
- `eligible = 0`
- `reason_code = ELIG_MISSING_BAR`

## Missing-indicators rule
If mandatory indicators for D do not exist:
- `eligible = 0`
- `reason_code = ELIG_MISSING_INDICATORS`

## Invalid-indicators rule
If indicator row exists but mandatory indicator readiness is invalid:
- `eligible = 0`
- `reason_code = ELIG_INVALID_INDICATORS`

## Insufficient-history rule
If the blocking cause is insufficient required history for mandatory indicators:
- `eligible = 0`
- `reason_code = ELIG_INSUFFICIENT_HISTORY`

## Universe-dependency rule
If eligibility cannot be built safely because required universe dependency is unavailable:
- `eligible = 0`
- `reason_code = ELIG_UNIVERSE_DEPENDENCY_MISSING`

## Optional fetch-failure rule
If optional per-ticker fetch-failure tracking is implemented and a ticker could not be safely produced because source acquisition failed after retries/exhaustion:
- `eligible = 0`
- `reason_code = ELIG_FETCH_FAILURE`

This code must be used only if it exists in the official registry and is supported by the implementation.

## One-blocking-reason rule (LOCKED)
Each eligibility row stores one blocking `reason_code` only.

If multiple blocking conditions exist, the implementation must select the most specific dominant blocking reason according to locked precedence documented elsewhere.

## Consumer rule (LOCKED)
Consumers must use the eligibility snapshot as published.
Consumers must not reconstruct eligibility by guessing from bars or indicators independently of the published eligibility artifact.

## Anti-ambiguity rule (LOCKED)
The following are forbidden:
- multiple live eligibility rows for the same `(trade_date, ticker_id)`
- `eligible = 0` with empty blocking reason
- `eligible = 1` when mandatory upstream readiness conditions are not satisfied
- live readable eligibility rows with `publication_id IS NULL`
