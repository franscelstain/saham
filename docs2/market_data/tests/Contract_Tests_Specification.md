# Contract Tests Specification

## Purpose
Define the minimum automated contract tests required to keep Market Data Platform deterministic and safe for downstream consumers.

## Required test groups (LOCKED)
### 1) Canonical bar validation
- valid bar accepted into `eod_bars`
- invalid bar rejected from `eod_bars` and recorded in `eod_invalid_bars`
- duplicate `(trade_date, ticker_id)` prevented

### 2) Indicator correctness
- ATR14 Wilder seed and recursion
- `roc20` uses `D[-20]`
- `vol_ratio` uses prior-20 average excluding D
- `hh20` uses inclusive 20-day real-high window
- price-basis fallback `adj_close -> close`

### 3) Null and warmup policy
- insufficient history produces NULL mandatory indicator + invalid reason
- no forward-fill or zero-fill

### 4) Effective date and readiness
- unsealed `SUCCESS` does not become consumable effective date
- `HELD` / `FAILED` requested date falls back to prior sealed SUCCESS date
- consumer read model never uses `MAX(trade_date)` behavior

### 5) Eligibility determinism
- one row per coverage-universe ticker/date
- missing canonical bar => `ELIG_MISSING_BAR`
- invalid indicators => `ELIG_INVALID_INDICATORS`

### 6) Hash determinism
- fixed ordering and formatting yield stable hashes across reruns
- locale and trailing-zero behavior do not change hashes

### 7) Controlled correction
- reseal after correction creates new run/hash trail
- prior run remains auditable