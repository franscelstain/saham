# Coverage Universe Definition (LOCKED)

Coverage universe:
- all tickers with tickers.is_active=1

Optional refinement only if ticker_type reliable (exclude non-equities).

coverage_ratio:
- numerator: count of universe tickers with VALID bar for D
- denominator: count of universe tickers