# EOD Bars Contract (Canonical OHLCV)

## Output table
eod_bars with PK (trade_date, ticker_id)

Columns:
- trade_date DATE
- ticker_id INT
- open/high/low/close DECIMAL(18,4)
- volume BIGINT
- adj_close DECIMAL(18,4) NULL (optional)
- source VARCHAR(32)
- ingested_at DATETIME
- run_id BIGINT (FK eod_runs)

## Bar validation rules (LOCKED)
Valid if:
1) open/high/low/close > 0
2) high >= max(open, close)
3) low <= min(open, close)
4) high >= low
5) volume >= 0
6) no duplicate PK

Invalid bars may be stored for audit but must be ineligible.