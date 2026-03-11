# Downstream Consumer Read Model Contract (LOCKED)

This document states the generic downstream read contract for any consumer of Market Data Platform output.
It does not define downstream screening, scoring, grouping, ranking, or execution policy.

## Official read sequence
1) resolve effective sealed date D from `eod_runs`
2) load readable universe from `eod_eligibility(D, eligible=1)`
3) join `eod_indicators(D, is_valid=1)`
4) optionally join `eod_bars(D)` for display-only fields

## Never
- infer dates by `MAX(trade_date)`
- compute indicators at read-time
- include `eligible=0`
- read unsealed datasets
- reinterpret upstream reason codes as downstream decision logic