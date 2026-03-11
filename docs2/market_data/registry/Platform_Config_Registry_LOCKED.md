# Platform Configuration Registry (LOCKED)

Defines output-affecting configuration that must be versioned and effective-dated.

## Registry principles (LOCKED)
- each config key has one authoritative meaning
- output-affecting keys require effective date and change note
- historical replay must use the config effective for the replayed trade date unless explicitly testing an alternate scenario
- undocumented runtime overrides are forbidden for production output

## Minimum keys
### Core data production
- `COVERAGE_MIN`
- `PRICE_BASIS_DEFAULT`
- `LOT_SIZE`
- `CUT_OFF_GRACE_MINUTES`
- `PLATFORM_EOD_CUTOFF_TIME`
- `SEAL_REQUIRED_FOR_CONSUMERS` (default `true`)
- `PLATFORM_TIMEZONE`

### Indicator windows
- `DV_WINDOW_DAYS` = 20
- `ATR_WINDOW_DAYS` = 14
- `VOL_RATIO_LOOKBACK_DAYS` = 20 (prior days, excluding D)
- `ROC_LOOKBACK_DAYS` = 20
- `HH_WINDOW_DAYS` = 20

### Hash / serialization
- `HASH_ALGORITHM` = `SHA-256`
- `HASH_DELIMITER` = `|`
- `HASH_LINE_SEPARATOR` = `\n`

### Provider/runtime
- `API_RETRY_MAX`
- `API_BACKOFF_MS`
- `API_THROTTLE_QPS`
- `CIRCUIT_BREAKER_ERROR_RATE`

### Intraday
- `INTRADAY_RETENTION_DAYS`
- `INTRADAY_SCOPE_DEFAULT`
- `SNAPSHOT_SLOT_TOLERANCE_MINUTES`

## Required registry metadata
For each key/value change, record at minimum:
- config key
- value
- effective start date
- changed by / change ticket
- whether replay output is affected