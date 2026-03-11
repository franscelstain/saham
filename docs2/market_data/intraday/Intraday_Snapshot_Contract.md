# Intraday Snapshot Contract (for Consumer CONFIRM)

Snapshot is event-based, not streaming.
Default slots: OPEN_CHECK 09:10, optional 13:30, 14:45.
Scope: picks-only OR eligibility set (never all tickers by default).
Fields: last_price, prev_close, chg_pct, volume, day_high/low + audit columns.
Retention: 14–30 days (default locked elsewhere to 30).
Failure must not block EOD PLAN generation.