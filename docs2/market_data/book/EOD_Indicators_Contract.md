# EOD Indicators Contract (LOCKED)

## Purpose
Define the authoritative upstream indicator artifact for one trade date D.

This contract governs:
- indicator row identity
- minimum fields
- publication-context semantics
- validity semantics
- dependency semantics
- null/invalid behavior
- deterministic interpretation

This document complements:
- `../indicators/EOD_Indicators_Formula_Spec.md`
- indicator test fixtures
- eligibility contracts

## Output identity
For the live current readable indicators table, there must be at most one indicator row per `(trade_date, ticker_id)`.

Minimum logical row identity:
- `trade_date`
- `ticker_id`

`publication_id` is mandatory publication context for the current readable row, but it is not a second competing live-table identity.
Historical publication-bound snapshots belong in history tables or publication evidence, not as duplicate live current rows.

## Minimum fields
Required minimum fields:
- `trade_date`
- `ticker_id`
- `publication_id`
- `is_valid`
- `invalid_reason_code`
- `indicator_set_version`
- `dv20_idr`
- `atr14_pct`
- `vol_ratio`
- `roc20`
- `hh20`
- `run_id`

Equivalent naming is allowed only if semantics remain identical.

## Current-state publication-context rule (LOCKED)
For the live readable table `eod_indicators`:
- each row must belong to exactly one sealed publication context
- `publication_id` must be non-null
- the row must represent the current readable state for `(trade_date, ticker_id)`
- superseded publication row sets must not remain side-by-side in the live current table

## Upstream-only rule (LOCKED)
These indicators are upstream derived data.
They are not:
- signals
- rankings
- watchlist groups
- portfolio actions
- execution instructions

## Validity semantics
- `is_valid = 1` means all mandatory indicator fields required by the active upstream contract are valid for that row
- `is_valid = 0` means the row exists, but one or more mandatory readiness conditions failed

When invalid:
- `invalid_reason_code` must be populated
- blocked downstream readiness must be explainable without guessing

## One-row rule (LOCKED)
The live current artifact must emit at most one row per `(trade_date, ticker_id)`.
Duplicate live indicator rows for the same key are forbidden.

## Dependency summary table (LOCKED)

| Indicator | Input dependency | Window traversal | Warmup rule | Null rule | Blocking invalid reason |
|---|---|---|---|---|---|
| `dv20_idr` | `basis_close(X)`, `volume(X)` for `D[-19] ... D` | trading-day | 20 valid bars including D | `NULL` if required history missing | `IND_INSUFFICIENT_HISTORY`, `IND_MISSING_DEPENDENCY_BAR`, `IND_INVALID_BAR_INPUT` |
| `atr14_pct` | `high(X)`, `low(X)`, `basis_close(prev(X))`, `basis_close(D)` | trading-day | 15 bars for first ATR14 output | `NULL` if seed or dependency chain invalid | `IND_INSUFFICIENT_HISTORY`, `IND_MISSING_DEPENDENCY_BAR`, `IND_INVALID_BAR_INPUT` |
| `vol_ratio` | `volume(D)` and `volume(D[-20] ... D[-1])` | trading-day | 21 bars total | `NULL` if prior-20 unavailable | `IND_INSUFFICIENT_HISTORY`, `IND_MISSING_DEPENDENCY_BAR`, `IND_INVALID_BAR_INPUT` |
| `roc20` | `basis_close(D)`, `basis_close(D[-20])` | trading-day | 21 bars total | `NULL` if `D[-20]` unavailable | `IND_INSUFFICIENT_HISTORY`, `IND_MISSING_DEPENDENCY_BAR`, `IND_INVALID_BAR_INPUT` |
| `hh20` | `high(X)` for `D[-19] ... D` | trading-day | 20 valid bars including D | `NULL` if required dependency unavailable | `IND_INSUFFICIENT_HISTORY`, `IND_MISSING_DEPENDENCY_BAR`, `IND_INVALID_BAR_INPUT` |

## Price basis rule (LOCKED)
Where closing-price basis is required, use per-date fallback:
- `adj_close`
- otherwise `close`

This must be applied separately on each dependency date.

## Trading-day rule (LOCKED)
Lookbacks and windows must be evaluated on ordered trading-day sequence.
Calendar subtraction is forbidden.

## Invalid reason semantics
Preferred meanings:
- `IND_INSUFFICIENT_HISTORY`:
  history not yet long enough for the indicator’s locked warmup
- `IND_MISSING_DEPENDENCY_BAR`:
  a required trading-day dependency row should exist but is missing
- `IND_INVALID_BAR_INPUT`:
  required bar input exists but is invalid for computation
- `IND_COMPUTE_ERROR`:
  compute logic/runtime failed unexpectedly

## Row existence rule (LOCKED)
If implementation chooses to materialize indicator rows even when invalid:
- the row must remain uniquely keyed by `(trade_date, ticker_id)` in the live current table
- `publication_id` must still be populated
- `is_valid = 0`
- `invalid_reason_code` must explain why

Implementation must not silently omit rows if downstream contracts expect explicit invalid-state rows.

## Determinism rule (LOCKED)
Given identical canonical bars, calendar ordering, config semantics, and indicator-set version, the indicator row for `(trade_date, ticker_id)` must be identical across reruns within the same publication outcome.

## Eligibility interaction
Eligibility consumers must use this indicator artifact as published.
They must not recompute indicators ad hoc from bars at read time.

## Anti-ambiguity rule (LOCKED)
The following are forbidden:
- multiple live indicator rows for the same `(trade_date, ticker_id)`
- invalid row with empty invalid reason
- non-`NULL` output produced through guessed or missing dependencies
- downstream read logic inferring validity from field non-nullness alone while ignoring `is_valid` and `invalid_reason_code`
- live readable indicator rows with `publication_id IS NULL`
