# Indicator Expected Output Oracle (LOCKED)

## Purpose
Provide a compact oracle-style expected-output table for indicator proof so implementations can compare actual outputs to one canonical expected result set.

This file is the compact expected-results counterpart to:
- formula spec
- test vectors
- fixture examples

## Oracle rules (LOCKED)
1. The oracle gives expected outputs, not just formula guidance.
2. If actual implementation output differs from the oracle without an intentional versioned contract change, the implementation is wrong or drifted.
3. Precision in this file is normative for proof comparison unless a stricter documented rule exists.

## Oracle table

| Case ID | trade_date | ticker_id | dv20_idr | atr14_pct | vol_ratio | roc20 | hh20 | is_valid | invalid_reason_code |
|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| `ORACLE_ATR_SEED_NEXT` | `2026-03-03` | 101 | `150000000.00` | `0.0237882653` | `1.2500000000` | `0.0500000000` | `109.0000` | 1 | |
| `ORACLE_ROC_FALLBACK` | `2026-03-04` | 101 | `150000000.00` | `0.0237882653` | `1.2500000000` | `0.1000000000` | `109.0000` | 1 | |
| `ORACLE_SHORT_HISTORY` | `2026-03-05` | 101 | `NULL` | `NULL` | `NULL` | `NULL` | `NULL` | 0 | `IND_INSUFFICIENT_HISTORY` |
| `ORACLE_MISSING_DEP_BAR` | `2026-03-06` | 101 | `NULL` | `NULL` | `NULL` | `NULL` | `NULL` | 0 | `IND_MISSING_DEPENDENCY_BAR` |

## Notes per case

### `ORACLE_ATR_SEED_NEXT`
Assumes:
- ATR14 seed already established on prior eligible date
- next recursive ATR computed from `TR(next_date)=1.50`
- `basis_close(next_date)=48.00`

### `ORACLE_ROC_FALLBACK`
Assumes:
- `adj_close(D)=NULL`
- `close(D)=110.00`
- `adj_close(D[-20])=100.00`

### `ORACLE_SHORT_HISTORY`
Assumes:
- only 10 trading-day bars exist
- all history-dependent outputs must remain NULL

### `ORACLE_MISSING_DEP_BAR`
Assumes:
- target date exists
- one required dependency bar in the trading-day chain is unexpectedly missing

## Precision guidance
Expected comparisons should use at minimum:
- `dv20_idr`: 2 decimal places
- `atr14_pct`: 10 decimal places
- `vol_ratio`: 10 decimal places
- `roc20`: 10 decimal places
- `hh20`: 4 decimal places

## Required usage
A serious proof implementation should compare actual output rows against this oracle or an equivalent fixture-bound oracle with the same semantics.

## Cross-contract alignment
This file must remain aligned with:
- `Indicator_Test_Vectors_LOCKED.md`
- `EOD_Indicators_Formula_Spec.md`
- `EOD_Indicators_Contract.md`
- `Contract_Test_Matrix_LOCKED.md`

## Anti-ambiguity rule (LOCKED)
If an implementation can claim “indicator correctness” without matching an explicit oracle like this for representative cases, the proof layer is not strong enough for 10/10 verification.