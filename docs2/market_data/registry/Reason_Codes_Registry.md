# Reason Codes Registry (LOCKED)

## Purpose
Define the canonical reason-code vocabulary used by Market Data Platform across:
- `eod_invalid_bars.invalid_reason_code`
- `eod_indicators.invalid_reason_code`
- `eod_eligibility.reason_code`
- `eod_run_events.reason_code`

This registry is intentionally upstream-only. It does not encode watchlist scores, groups, picks, or strategy actions.

## Registry rules (LOCKED)
1. Codes are stable identifiers and must be uppercase snake case.
2. One code has one meaning only.
3. Description text may be clarified over time, but code semantics must not drift silently.
4. Deprecated codes must not be physically reused for a different meaning.
5. Severity is the default registry severity; actual run outcome is still decided by the locked decision table.

## Canonical registry
| code | category | severity | description |
|---|---|---:|---|
| `RUN_COVERAGE_LOW` | RUN | HARD | Coverage ratio for requested date is below locked minimum threshold. |
| `RUN_INDICATORS_MISSING` | RUN | HARD | Mandatory indicator artifact or row set is missing for requested date. |
| `RUN_ELIGIBILITY_MISSING` | RUN | HARD | Eligibility snapshot missing for requested date. |
| `RUN_HASH_MISSING` | RUN | HARD | One or more mandatory content hashes are absent at finalize time. |
| `RUN_HASH_FAILED` | RUN | HARD | Hash computation failed or produced unusable output. |
| `RUN_SEAL_PRECONDITION_FAILED` | RUN | HARD | Seal attempted before all locked preconditions were satisfied. |
| `RUN_SEAL_WRITE_FAILED` | RUN | HARD | Seal metadata could not be written successfully. |
| `RUN_FINALIZE_BEFORE_CUTOFF` | RUN | HARD | Final success attempted before cutoff policy allowed it. |
| `RUN_LOCK_CONFLICT` | RUN | HARD | Hash/seal/finalize ownership conflict or duplicate writer detected. |
| `RUN_SOURCE_TIMEOUT` | RUN | WARN | Source timeout occurred and retry policy was invoked or exhausted. |
| `RUN_SOURCE_RATE_LIMIT` | RUN | WARN | Source rate limiting occurred and affected acquisition progress. |
| `RUN_SOURCE_AUTH_ERROR` | RUN | HARD | Source authentication or credential/config failure blocked acquisition. |
| `RUN_SOURCE_RESPONSE_CHANGED` | RUN | HARD | Source schema/response contract drift detected. |
| `RUN_SOURCE_PARTIAL_COVERAGE` | RUN | WARN | Source returned incomplete symbol coverage for requested date. |
| `RUN_SOURCE_MALFORMED_PAYLOAD` | RUN | HARD | Source payload could not be normalized safely. |
| `BAR_DUPLICATE_SOURCE_ROW` | BAR | WARN | Multiple source rows mapped to the same `(trade_date, ticker_id)` and required deterministic winner selection. |
| `BAR_INVALID_OHLC_ORDER` | BAR | HARD | Observed OHLC violates canonical ordering rules. |
| `BAR_NON_POSITIVE_PRICE` | BAR | HARD | Observed price field is zero or negative where positive value is required. |
| `BAR_NEGATIVE_VOLUME` | BAR | HARD | Observed volume is negative. |
| `BAR_MISSING_REQUIRED_FIELD` | BAR | HARD | One or more mandatory source fields were missing. |
| `IND_INSUFFICIENT_HISTORY` | INDICATOR | WARN | Required trading-day history window not available for deterministic compute. |
| `IND_MISSING_DEPENDENCY_BAR` | INDICATOR | HARD | Required canonical dependency bar is missing in the trading-day chain. |
| `IND_INVALID_BAR_INPUT` | INDICATOR | HARD | Indicator compute input derived from canonical bars is invalid. |
| `IND_COMPUTE_ERROR` | INDICATOR | HARD | Indicator computation failed because of logic/runtime error. |
| `ELIG_MISSING_BAR` | ELIGIBILITY | WARN | Coverage-universe ticker has no canonical valid bar for requested date. |
| `ELIG_MISSING_INDICATORS` | ELIGIBILITY | HARD | Eligibility cannot be determined because mandatory indicators are absent. |
| `ELIG_INVALID_INDICATORS` | ELIGIBILITY | WARN | Indicator row exists but is marked invalid for mandatory fields. |
| `ELIG_INSUFFICIENT_HISTORY` | ELIGIBILITY | WARN | Eligibility denied because mandatory history-dependent indicators are not yet available. |
| `ELIG_UNIVERSE_DEPENDENCY_MISSING` | ELIGIBILITY | HARD | Upstream dependency required to build universe membership was unavailable. |
| `SNAP_SOURCE_TIMEOUT` | INTRADAY | WARN | Session snapshot source timeout occurred. |
| `SNAP_SOURCE_RATE_LIMIT` | INTRADAY | WARN | Session snapshot source rate limit occurred. |
| `SNAP_PARTIAL_SCOPE` | INTRADAY | WARN | Session snapshot captured only part of the intended scope. |
| `SNAP_SOURCE_ERROR` | INTRADAY | WARN | Session snapshot source failed for non-blocking operational reasons. |

## Locked usage notes
- `ELIG_MISSING_BAR` and `ELIG_INSUFFICIENT_HISTORY` may coexist as different row outcomes on different dates/tickers, but one row stores only the single most specific blocking reason.
- `RUN_SOURCE_TIMEOUT` and `RUN_SOURCE_RATE_LIMIT` do not automatically force `FAILED`; terminal status still follows the decision table and gate results.
- `RUN_HASH_MISSING`, `RUN_HASH_FAILED`, `RUN_SEAL_PRECONDITION_FAILED`, and `RUN_SEAL_WRITE_FAILED` are always incompatible with final `SUCCESS`.
- Session snapshot reason codes must never be used to justify fallback of sealed EOD datasets.