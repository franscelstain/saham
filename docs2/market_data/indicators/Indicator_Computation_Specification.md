# Indicator Computation Specification (EOD)

## Input source
Input comes only from canonical `eod_bars` for valid trading days.
Rows from `eod_invalid_bars` must never participate in indicator computation.

## Trading-day ordering (LOCKED)
For each ticker, bars are ordered by market-calendar trading day ascending.
All windows below are counted in trading days.

Notation:
- `D` = effective trade date being computed
- `D[-1]` = previous trading day
- `D[-N]` = Nth prior trading day relative to D
- `window(D, N)` = `D[-(N-1)] ... D`, inclusive, total N rows

## Price basis (LOCKED)
`P(D)` is defined as:
- `adj_close`, when `PRICE_BASIS_DEFAULT=ADJ_CLOSE` and `adj_close IS NOT NULL`
- otherwise `close`

This fallback is applied independently per date in the required lookback window.
`ATR` and `TR` always use real `high`, `low`, and previous real `close`.

## Baseline formulas (LOCKED)
### 1) Turnover normalization
`turnover_idr(D) = close(D) * volume(D) * LOT_SIZE`
Where `LOT_SIZE` is taken from the config registry. Default for IDX equities is 100.

### 2) `dv20_idr`
`dv20_idr(D) = AVG(turnover_idr(x))` over `window(D, 20)`.
Requires 20 canonical bars including D.

### 3) `TR(D)`
`TR(D) = MAX(high(D) - low(D), ABS(high(D) - close(D[-1])), ABS(low(D) - close(D[-1])))`
Requires current bar and previous canonical bar.

### 4) `ATR14 Wilder`
Seed at the first date with 14 available TR values:
`ATR14_seed = AVG(TR(x))` over the first 14 TR observations.
Recursive form afterward:
`ATR14(D) = ((ATR14(D[-1]) * 13) + TR(D)) / 14`
Requires trading-day continuity per ticker.

Warmup implication:
- because `TR(D)` itself requires `close(D[-1])`, the first computable `ATR14_seed` date is the trade date that has 15 canonical bars available for the ticker
- before that point, `ATR14` and `atr14_pct` are NULL

### 5) `atr14_pct`
`atr14_pct(D) = (ATR14(D) / close(D)) * 100`
If `close(D) <= 0` the source bar is invalid and the indicator must not be computed.

### 6) `vol_ratio`
`vol_ratio(D) = volume(D) / AVG(volume(x))` over `window(D[-1], 20)`
This uses the current day volume divided by the average volume of the **20 prior trading days excluding D**.
Requires 21 canonical bars total: D plus D[-1]..D[-20].

### 7) `roc20`
`roc20(D) = (P(D) / P(D[-20])) - 1`
Requires both `P(D)` and `P(D[-20])`.
This is a pure ratio, not a percentage-multiplied-by-100 field.

### 8) `hh20`
`hh20(D) = MAX(high(x))` over `window(D, 20)`.
This is based on real highs, not adjusted price basis.

## Null policy (LOCKED)
- No forward-fill.
- No zero-fill.
- No calendar-gap interpolation.
- Missing required history => indicator NULL and `is_valid=0`.
- Optional/non-baseline indicators may be NULL without invalidating the row only if explicitly declared in a future registry version.

## Rounding/storage (LOCKED)
Store using output column precision defined in schema.
Hash serialization formatting is governed separately by `Hash_Number_Formatting_LOCKED.md`.