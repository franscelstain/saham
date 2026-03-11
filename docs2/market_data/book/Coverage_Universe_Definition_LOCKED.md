# Coverage Universe Definition (LOCKED)

## Coverage universe
Default coverage universe for trade date D is:
- all rows in `tickers` with `is_active=1` on D

Optional refinement is allowed only if driven by stable upstream master-data attributes documented outside this module, for example excluding known non-equity instruments when `ticker_type` is reliable and versioned.

## Locked rules
- Universe membership must be evaluated as-of D, not as-of current wall-clock time.
- Coverage denominator must use the full coverage universe count for D.
- Coverage numerator must count tickers with a canonical valid bar in `eod_bars` for D.
- Downstream consumer preferences must never alter coverage metrics.