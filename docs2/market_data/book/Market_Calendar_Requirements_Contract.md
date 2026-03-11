# Market Calendar Requirements Contract (Global Dependency)

Requires:
- trade_date
- is_trading_day
- prev_trading_day
- next_trading_day
Optional:
- session open/close time, half-day flag

Latest trading day resolution:
- if today is trading day and after close => today
- else => prev trading day