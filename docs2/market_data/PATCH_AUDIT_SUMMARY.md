# Patch Audit Summary — Market Data Platform (EOD)

## What was fixed
1) Re-centered the documentation on upstream market-data responsibilities only.
2) Tightened core contracts for canonical bars, indicators, effective date, hashes, and sealing.
3) Filled mandatory but previously empty specs for contract tests and historical replay.
4) Removed schema/contract ambiguity around invalid bars by separating `eod_invalid_bars` from canonical `eod_bars`.
5) Added required schema support for seal metadata and structured run-event logging.
6) Reduced downstream-policy leakage by rewriting watchlist references as example consumer dependencies only.

## Key gaps closed
- contract tests spec
- historical replay / data-quality backtest spec
- seal metadata in schema
- logging schema
- invalid-bar audit storage model
- exact indicator semantics for `D[-20]`, trading-day windows, null policy, and price-basis fallback
- hash serialization / formatting details