# Snapshot Slot Tolerances and Session Rules (LOCKED)

- slot tolerance is ±3 minutes by default
- if market calendar marks half-day and close time is known, pre-close slot = session close minus 15 minutes
- slot miss or provider miss must be recorded as intraday error/partial state, not treated as EOD pipeline failure
