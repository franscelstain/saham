# Contract Tests Specification

## Purpose
Define the minimum automated contract tests required to keep Market Data Platform deterministic and safe for downstream consumers.

## Required test groups (LOCKED)
### 1) Canonical bar validation
- valid bar accepted into `eod_bars`
- invalid bar rejected from `eod_bars` and recorded in `eod_invalid_bars`
- duplicate `(trade_date, ticker_id)` prevented
- duplicate provider rows for the same `(trade_date, ticker_id)` resolve by the locked precedence chain and produce deterministic winner/loser audit outcomes

### 2) Indicator correctness
- ATR14 Wilder seed and recursion
- ATR14 warmup starts only when 14 TR values exist, which implies 15 canonical bars
- `roc20` uses `D[-20]`
- `roc20` stays ratio-scaled and is not multiplied by 100
- `vol_ratio` uses prior-20 average excluding D
- `hh20` uses inclusive 20-day real-high window
- price-basis fallback is per-date `adj_close -> close`

### 3) Null and warmup policy
- insufficient history produces NULL mandatory indicator + invalid reason
- no forward-fill or zero-fill

### 4) Effective date and readiness
- unsealed `SUCCESS` does not become consumable effective date
- `HELD` / `FAILED` requested date falls back to prior sealed SUCCESS date
- if no prior sealed SUCCESS date exists, `trade_date_effective` stays NULL
- consumer read model never uses `MAX(trade_date)` behavior

### 5) Eligibility determinism
- one row per coverage-universe ticker/date
- missing canonical bar => `ELIG_MISSING_BAR`
- invalid indicators => `ELIG_INVALID_INDICATORS`

### 6) Hash determinism
- fixed ordering and formatting yield stable hashes across reruns
- locale and trailing-zero behavior do not change hashes
- only rows for the effective date being sealed are hashed
- field order in serialized payload exactly matches the locked hash contract

### 7) Controlled correction
- reseal after correction creates new run/hash trail
- prior run remains auditable