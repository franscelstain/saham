# Coverage Universe Definition (LOCKED)

## Coverage universe
Default coverage universe for trade date D is:
- all ticker identities that are members of the upstream equity universe as-of D

This membership must be derived from temporal master-data state, not current wall-clock state.

Optional refinement is allowed only if driven by stable upstream master-data attributes documented outside this module, for example excluding known non-equity instruments when `ticker_type` is reliable and versioned.

## Locked rules
- universe membership must be evaluated as-of D, not as-of current wall-clock time
- coverage denominator must use the full coverage universe count for D
- coverage numerator must count tickers with a canonical valid bar in `eod_bars` for D
- downstream consumer preferences must never alter coverage metrics
- if the application lacks temporal ticker membership data, it must explicitly document that coverage is current-state only; it must not silently claim historical as-of correctness