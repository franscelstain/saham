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
| `RUN_COVERAGE_LOW` | RUN | HARD | Coverage ratio for the requested date is below the locked minimum threshold. |
| `RUN_INDICATORS_MISSING` | RUN | HARD | Required indicator artifact or required indicator row set for the requested date is not available. |
| `RUN_ELIGIBILITY_MISSING` | RUN | HARD | Eligibility snapshot for the requested date is not available. |
| `RUN_HASH_MISSING` | RUN | HARD | One or more mandatory content hashes are missing at finalization time. |
| `RUN_HASH_FAILED` | RUN | HARD | Hash computation failed or produced unusable output. |
| `RUN_SEAL_PRECONDITION_FAILED` | RUN | HARD | Seal execution was attempted before all locked preconditions were satisfied. |
| `RUN_SEAL_WRITE_FAILED` | RUN | HARD | Seal metadata could not be written successfully. |
| `RUN_FINALIZE_BEFORE_CUTOFF` | RUN | HARD | Final success was attempted before the cutoff policy allowed it. |
| `RUN_LOCK_CONFLICT` | RUN | HARD | Run-ownership conflict or duplicate writer activity occurred during hash, seal, or finalize stages. |
| `RUN_SOURCE_TIMEOUT` | RUN | WARN | The source timed out and retry policy was already applied or exhausted. |
| `RUN_SOURCE_RATE_LIMIT` | RUN | WARN | The source hit rate limiting and affected data acquisition. |
| `RUN_SOURCE_AUTH_ERROR` | RUN | HARD | Source authentication failure or credential/config error blocked data acquisition. |
| `RUN_SOURCE_RESPONSE_CHANGED` | RUN | HARD | A source schema or response-contract change was detected. |
| `RUN_SOURCE_PARTIAL_COVERAGE` | RUN | WARN | The source returned incomplete symbol coverage for the requested date. |
| `RUN_SOURCE_MALFORMED_PAYLOAD` | RUN | HARD | The source payload could not be normalized safely. |
| `BAR_DUPLICATE_SOURCE_ROW` | BAR | WARN | More than one source row mapped to the same `(trade_date, ticker_id)`, requiring deterministic winner selection. |
| `BAR_INVALID_OHLC_ORDER` | BAR | HARD | Received OHLC values violated canonical ordering rules. |
| `BAR_NON_POSITIVE_PRICE` | BAR | HARD | Received price value was zero or negative in a field that must be positive. |
| `BAR_NEGATIVE_VOLUME` | BAR | HARD | Received volume value was negative. |
| `BAR_MISSING_REQUIRED_FIELD` | BAR | HARD | One or more mandatory source fields were missing. |
| `IND_INSUFFICIENT_HISTORY` | INDICATOR | WARN | Required trading-day history is not yet sufficient for deterministic indicator computation. |
| `IND_MISSING_DEPENDENCY_BAR` | INDICATOR | HARD | A required canonical bar in the trading-day dependency chain is missing. |
| `IND_INVALID_BAR_INPUT` | INDICATOR | HARD | A canonical bar input required for indicator computation is invalid. |
| `IND_COMPUTE_ERROR` | INDICATOR | HARD | Indicator computation failed because of logic or runtime error. |
| `ELIG_MISSING_BAR` | ELIGIBILITY | WARN | A ticker in the coverage universe does not have a canonical valid bar for the requested date. |
| `ELIG_MISSING_INDICATORS` | ELIGIBILITY | HARD | Eligibility cannot be determined because required indicators are unavailable. |
| `ELIG_INVALID_INDICATORS` | ELIGIBILITY | WARN | An indicator row exists but required indicators are marked invalid. |
| `ELIG_INSUFFICIENT_HISTORY` | ELIGIBILITY | WARN | Eligibility is blocked because required indicator history is still insufficient. |
| `ELIG_UNIVERSE_DEPENDENCY_MISSING` | ELIGIBILITY | HARD | An upstream dependency required to determine universe membership is unavailable. |
| `ELIG_FETCH_FAILURE` | ELIGIBILITY | WARN | Eligibility is blocked because ticker-level source acquisition failed and required upstream artifacts could not be formed safely. |
| `SNAP_SOURCE_TIMEOUT` | INTRADAY | WARN | The session-snapshot source timed out. |
| `SNAP_SOURCE_RATE_LIMIT` | INTRADAY | WARN | The session-snapshot source hit rate limiting. |
| `SNAP_PARTIAL_SCOPE` | INTRADAY | WARN | The session snapshot captured only part of the planned scope. |
| `SNAP_SOURCE_ERROR` | INTRADAY | WARN | The session-snapshot source failed for an operational reason that does not block EOD. |

## Locked usage notes
- `ELIG_MISSING_BAR` and `ELIG_INSUFFICIENT_HISTORY` may coexist as different row outcomes on different dates/tickers, but one row stores only the single most specific blocking reason.
- `RUN_SOURCE_TIMEOUT` and `RUN_SOURCE_RATE_LIMIT` do not automatically force `FAILED`; terminal status still follows the decision table and gate results.
- `RUN_HASH_MISSING`, `RUN_HASH_FAILED`, `RUN_SEAL_PRECONDITION_FAILED`, and `RUN_SEAL_WRITE_FAILED` are always incompatible with final `SUCCESS`.
- Session snapshot reason codes must never be used to justify fallback of sealed EOD datasets.