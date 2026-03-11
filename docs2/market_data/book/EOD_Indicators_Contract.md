# EOD Indicators Contract

## Output table
eod_indicators with PK (trade_date, ticker_id)

Meta columns:
- is_valid (1/0)
- invalid_reason_code
- indicator_set_version
- computed_at
- run_id

Rules (LOCKED):
- trading-day windows (not calendar)
- insufficient history => NULL
- ATR/TR uses real OHLC only
- versioning required when semantics change

Minimum indicator set baseline:
- see registry/Indicator_Registry_Weekly_Swing_Baseline.md