# Intraday Date Alignment with Effective Trade Date (LOCKED)

- `intraday_snapshots.trade_date` must equal the resolved `trade_date_effective` D, not the wall-clock calendar date of capture.
- `captured_at` stores the actual capture timestamp.
- primary key is `(trade_date, snapshot_slot, ticker_id)` so all snapshot data is aligned to the same effective reference date used by downstream readers.
