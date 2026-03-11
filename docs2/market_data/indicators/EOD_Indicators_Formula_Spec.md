# EOD Indicators Formula Specification (LOCKED)

## Purpose
Define the exact deterministic formula rules for the minimum EOD indicators produced by Market Data Platform.

This document is upstream-only.
It does not define downstream scoring, ranking, signal generation, or strategy decisions.

## General formula rules (LOCKED)
1. All indicator computation uses trading-day sequence, not wall-clock calendar subtraction.
2. Canonical input source is `eod_bars`.
3. Where price basis is required, use per-date fallback:
   - `adj_close` if present
   - otherwise `close`
4. Missing required dependency rows must invalidate dependent outputs.
5. Insufficient history must not be hidden by forward-fill, zero-fill, or guessed backfill.
6. Indicator output for one `(trade_date, ticker_id)` must be deterministic for identical upstream inputs.

## Canonical price basis (LOCKED)
For any formula requiring closing-price basis on a date `X`:

    basis_close(X) =
      adj_close(X)   if adj_close(X) is present
      close(X)       otherwise

This fallback is evaluated per date, not once for the whole window.

## Trading-day traversal (LOCKED)
References such as:
- `D[-1]`
- `D[-20]`
- prior 20 days
- window size 14
- window size 20

must be interpreted using ordered trading-day sequence from the market calendar, not calendar-day subtraction.

---

## Indicator definitions

### 1. `dv20_idr`

#### Meaning
20-day average traded value in IDR using 20 trading days including D.

#### Formula
For target date `D`:

    daily_value(X) = basis_close(X) * volume(X)

    dv20_idr(D) = average( daily_value(X) ) over trading days X in [D[-19] ... D]

#### Dependencies
- 20 trading-day bars including D
- valid `basis_close(X)`
- valid `volume(X)`

#### Warmup rule
Requires 20 valid trading-day rows including D.

#### Invalid rule
If any required dependency row is missing or invalid within the required locked input set:
- output field is `NULL`
- row may be marked invalid depending on mandatory-indicator contract
- preferred blocking reason:
  - `IND_INSUFFICIENT_HISTORY` if history is not yet long enough
  - `IND_MISSING_DEPENDENCY_BAR` if a required trading-day dependency row is missing unexpectedly
  - `IND_INVALID_BAR_INPUT` if required bar input exists but is invalid

---

### 2. `atr14_pct`

#### Meaning
14-period Average True Range using Wilder smoothing, expressed as ratio to `basis_close(D)`.

#### Step A — True Range
For each trading date `X` after the first required dependency row:

    TR(X) = max(
      high(X) - low(X),
      abs(high(X) - basis_close(prev(X))),
      abs(low(X)  - basis_close(prev(X)))
    )

where `prev(X)` is the immediately prior trading date.

#### Step B — ATR14 seed
First ATR14 becomes available only when 14 TR values exist.
That implies 15 canonical bars are required.

If target date `S` is the first date where 14 TR values are available:

    ATR14(S) = average( TR values for the first 14 eligible trading dates )

#### Step C — Wilder recursion
For any later trading date `D`:

    ATR14(D) = ((ATR14(D[-1]) * 13) + TR(D)) / 14

#### Step D — Percentage form

    atr14_pct(D) = ATR14(D) / basis_close(D)

#### Warmup rule
Requires 15 trading-day bars to produce first ATR14 percentage output.

#### Invalid rule
If a required dependency bar in the chain is missing:
- `atr14_pct = NULL`
- invalid reason should reflect missing dependency or insufficient history

---

### 3. `vol_ratio`

#### Meaning
Volume on D divided by average volume over the prior 20 trading days excluding D.

#### Formula

    vol_ratio(D) = volume(D) / average( volume(X) ) over X in [D[-20] ... D[-1]]

#### Warmup rule
Requires 21 trading-day bars total: D plus 20 prior trading days.

#### Invalid rule
If prior-20 history is not available:
- `vol_ratio = NULL`
- invalid reason: `IND_INSUFFICIENT_HISTORY`

If one of the required prior bars is missing unexpectedly:
- invalid reason: `IND_MISSING_DEPENDENCY_BAR`

---

### 4. `roc20`

#### Meaning
20-trading-day rate of change using `basis_close(D)` and `basis_close(D[-20])`.

#### Formula

    roc20(D) = ( basis_close(D) / basis_close(D[-20]) ) - 1

#### Important interpretation (LOCKED)
- Uses trading date `D[-20]`
- Expressed as ratio, not multiplied by 100
- Uses per-date fallback `adj_close -> close`

#### Warmup rule
Requires D and 20 prior trading days.

#### Invalid rule
If `D[-20]` is unavailable or invalid:
- `roc20 = NULL`
- invalid reason according to dependency failure cause

---

### 5. `hh20`

#### Meaning
Highest high over the inclusive 20-trading-day window ending on D.

#### Formula

    hh20(D) = max( high(X) ) over X in [D[-19] ... D]

#### Important interpretation (LOCKED)
Window is inclusive of D.

#### Warmup rule
Requires 20 trading-day bars including D.

#### Invalid rule
If one or more required dependency rows are missing from the trading-day chain:
- `hh20 = NULL`
- invalid reason according to dependency failure cause

---

## Worked Examples (LOCKED)

### A. ATR14 Wilder — Seed Example
Assume target ticker has 15 consecutive trading-day bars.
Suppose the first 14 TR values are:

    1.20, 1.10, 1.00, 0.90, 1.30, 1.40, 1.10,
    1.00, 1.20, 1.00, 0.80, 1.10, 1.30, 1.20

Sum:

    15.60

Seed ATR14 on the first eligible date `S`:

    ATR14(S) = 15.60 / 14 = 1.1142857143

If on date `S+1`, `TR(S+1) = 1.50`, then:

    ATR14(S+1) = ((1.1142857143 * 13) + 1.50) / 14
               = (14.4857142859 + 1.50) / 14
               = 15.9857142859 / 14
               = 1.1418367347

If `basis_close(S+1) = 48.00`, then:

    atr14_pct(S+1) = 1.1418367347 / 48.00
                   = 0.0237882653

### B. ROC20 using D[-20]
Assume:
- `basis_close(D) = 105.00`
- `basis_close(D[-20]) = 100.00`

Then:

    roc20(D) = (105.00 / 100.00) - 1
             = 1.05 - 1
             = 0.05

This is ratio-scaled output, not 5.00 percent text.

### C. Price Basis Fallback (`adj_close -> close`)
Assume:
- on D, `adj_close(D)` is `NULL`, `close(D)=110.00`
- on `D[-20]`, `adj_close(D[-20])=100.00`

Then:

    basis_close(D)      = 110.00
    basis_close(D[-20]) = 100.00

    roc20(D) = (110.00 / 100.00) - 1 = 0.10

This proves fallback is evaluated per date.

### D. vol_ratio using Prior-20 Excluding D
Assume:
- `volume(D)=1,500,000`
- average volume over prior 20 trading days excluding D = `1,200,000`

Then:

    vol_ratio(D) = 1,500,000 / 1,200,000 = 1.25

### E. hh20 Inclusive Window
Assume the highs over `D[-19] ... D` are:

    100, 101, 103, 102, 104, 105, 103, 102, 106, 104,
    103, 107, 105, 104, 103, 108, 106, 105, 104, 109

Then:

    hh20(D) = 109

Because D is included in the window.

### F. Insufficient History Example
Assume only 10 trading-day bars exist for ticker/date D.

Then:
- `dv20_idr(D) = NULL`
- `vol_ratio(D) = NULL`
- `roc20(D) = NULL`
- `hh20(D) = NULL`
- `atr14_pct(D)` may also be `NULL` if ATR warmup not met

Preferred invalid reason:
- `IND_INSUFFICIENT_HISTORY`

### G. Missing Dependency Bar Example
Assume the calendar requires `D[-20]`, but that dependency bar is unexpectedly missing from canonical storage while surrounding dates exist.

Then:
- `roc20(D) = NULL`
- `vol_ratio(D)` may also be invalid if the missing date is inside prior-20 window
- invalid reason:
  - `IND_MISSING_DEPENDENCY_BAR`

---

## Null and invalid policy summary (LOCKED)

| Indicator   | Minimum history | Uses D | Uses prior-only window | Typical insufficient-history code |
|-------------|-----------------|--------|------------------------|-----------------------------------|
| `dv20_idr`  | 20 bars         | Yes    | No                     | `IND_INSUFFICIENT_HISTORY`        |
| `atr14_pct` | 15 bars         | Yes    | Yes                    | `IND_INSUFFICIENT_HISTORY`        |
| `vol_ratio` | 21 bars         | Yes    | Yes                    | `IND_INSUFFICIENT_HISTORY`        |
| `roc20`     | 21 bars         | Yes    | Yes                    | `IND_INSUFFICIENT_HISTORY`        |
| `hh20`      | 20 bars         | Yes    | No                     | `IND_INSUFFICIENT_HISTORY`        |

---

## Anti-ambiguity rule (LOCKED)
The following are forbidden:
- calendar-day subtraction instead of trading-day traversal
- multiplying `roc20` by 100 unless a separate downstream presentation layer explicitly does so
- forward-filling missing dependency bars
- using one global choice of `adj_close` vs `close` for a whole window instead of per date
- generating non-NULL indicator values when locked warmup/dependency requirements are not satisfied