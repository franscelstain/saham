# Indicator Test Vectors (LOCKED)

## Purpose
Provide proof-level expected numeric vectors for the minimum EOD indicators so implementations can verify exact outputs, not merely formula direction.

This file is not just guidance.
It is a deterministic expected-values layer for indicator proof.

## General vector rules (LOCKED)
1. All vectors use trading-day ordering, not calendar subtraction.
2. Price basis uses per-date `adj_close -> close` fallback.
3. Output precision shown here is normative for test expectation comparison unless the implementation defines stricter numeric handling and documents it explicitly.
4. If a dependency row is missing unexpectedly, dependent output must be invalid, not guessed.

---

## Vector A — ATR14 seed and recursive step

### Input assumption
15 consecutive trading-day bars exist for one ticker.

### True Range sequence (first 14 TR values)
    1.20
    1.10
    1.00
    0.90
    1.30
    1.40
    1.10
    1.00
    1.20
    1.00
    0.80
    1.10
    1.30
    1.20

### Expected ATR14 seed
Sum of TR values:
    15.60

Expected:
    ATR14(seed_date) = 15.60 / 14 = 1.1142857143

### Recursive step
If:
- `TR(next_date) = 1.50`
- `basis_close(next_date) = 48.00`

Expected:
    ATR14(next_date) = ((1.1142857143 * 13) + 1.50) / 14
                     = 1.1418367347

    atr14_pct(next_date) = 1.1418367347 / 48.00
                         = 0.0237882653

### Proof assertions
- first ATR14 output appears only after 14 TR values exist
- first ATR14 output requires 15 canonical bars
- recursive ATR14 follows Wilder smoothing exactly
- `atr14_pct` divides ATR by `basis_close(next_date)`

---

## Vector B — ROC20 using `D[-20]`

### Input assumption
- `basis_close(D) = 105.00`
- `basis_close(D[-20]) = 100.00`

Expected:
    roc20(D) = (105.00 / 100.00) - 1
             = 0.0500000000

### Proof assertions
- uses trading-day `D[-20]`
- output is ratio-scaled, not multiplied by 100

---

## Vector C — Price basis fallback by date

### Input assumption
- on D:
  - `adj_close(D) = NULL`
  - `close(D) = 110.00`
- on `D[-20]`:
  - `adj_close(D[-20]) = 100.00`

Expected basis values:
    basis_close(D)      = 110.00
    basis_close(D[-20]) = 100.00

Expected ROC:
    roc20(D) = (110.00 / 100.00) - 1
             = 0.1000000000

### Proof assertions
- fallback is evaluated per date
- mixed `adj_close` and `close` within one formula window is valid and deterministic

---

## Vector D — vol_ratio using prior-20 excluding D

### Input assumption
- `volume(D) = 1500000`
- average volume over `D[-20] ... D[-1]` = `1200000`

Expected:
    vol_ratio(D) = 1500000 / 1200000
                 = 1.2500000000

### Proof assertions
- denominator excludes D
- prior-20 window is trading-day based

---

## Vector E — hh20 inclusive window

### Input highs over `D[-19] ... D`
    100
    101
    103
    102
    104
    105
    103
    102
    106
    104
    103
    107
    105
    104
    103
    108
    106
    105
    104
    109

Expected:
    hh20(D) = 109.0000

### Proof assertions
- D is included in the window
- highest high is selected exactly

---

## Vector F — dv20_idr over inclusive 20-day window

### Input assumption
For 20 consecutive trading days including D:
- `basis_close(X) = 100.00` for all 20 days
- `volume(X) = 1000000` for all 20 days

Then for every day in the window:
    daily_value(X) = 100.00 * 1000000 = 100000000.00

Expected:
    dv20_idr(D) = 100000000.00

### Proof assertions
- window includes D
- average of 20 identical daily values remains identical

---

## Vector G — insufficient history

### Input assumption
Only 10 trading-day bars exist for target ticker/date D.

Expected:
- `dv20_idr(D) = NULL`
- `atr14_pct(D) = NULL`
- `vol_ratio(D) = NULL`
- `roc20(D) = NULL`
- `hh20(D) = NULL`
- `is_valid = 0`
- `invalid_reason_code = IND_INSUFFICIENT_HISTORY`

### Proof assertions
- no forward-fill
- no zero-fill
- no guessed output for under-warmup rows

---

## Vector H — missing dependency bar

### Input assumption
Target date D exists, but one required dependency bar inside the locked trading-day chain is missing unexpectedly.

Expected:
- dependent indicator outputs become `NULL`
- `is_valid = 0`
- `invalid_reason_code = IND_MISSING_DEPENDENCY_BAR`

### Proof assertions
- missing dependency ≠ ordinary warmup insufficiency
- dependent outputs are explicitly invalidated

---

## Required precision guidance
Unless the implementation documents a stricter rule, expected comparisons should use:
- `dv20_idr`: 2 decimal places minimum
- `atr14_pct`: 10 decimal places minimum
- `vol_ratio`: 10 decimal places minimum
- `roc20`: 10 decimal places minimum
- `hh20`: 4 decimal places minimum

## Cross-contract alignment
This file must remain aligned with:
- `../indicators/EOD_Indicators_Formula_Spec.md`
- `../book/EOD_Indicators_Contract.md`
- `Golden_Fixture_Examples_LOCKED.md`
- `Contract_Test_Matrix_LOCKED.md`

## Anti-ambiguity rule (LOCKED)
If an implementation can “pass” indicator tests without matching explicit numeric outputs in vectors like these, then the proof layer is too weak.