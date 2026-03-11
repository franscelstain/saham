# Indicator Registry — Baseline (LOCKED)

This registry defines only the upstream baseline indicator set that downstream consumers may expect.
It does **not** define downstream screening, scoring, grouping, ranking, or portfolio logic.

## Mandatory baseline indicators
- `dv20_idr` using 20-day inclusive turnover average
- `atr14_pct` using 14-day Wilder ATR on real OHLC
- `vol_ratio` using current-day volume divided by average of prior 20 trading-day volumes
- `roc20` using `P(D)` versus `P(D[-20])`
- `hh20` using real high over the last 20 trading days inclusive of D

## Validity rule
If any mandatory baseline indicator is NULL because required history is unavailable, then:
- `eod_indicators.is_valid=0`
- `invalid_reason_code=IND_INSUFFICIENT_HISTORY`
- `eod_eligibility.eligible=0`
- `reason_code=ELIG_INSUFFICIENT_HISTORY`