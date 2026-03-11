# Watchlist Consumer Read Model Contract (LOCKED)

Official read sequence:
1) resolve effective date D from eod_runs
2) load eligible tickers from eod_eligibility (D, eligible=1)
3) join eod_indicators (D, is_valid=1)
4) optional join eod_bars for display

Never:
- infer dates by max(trade_date)
- compute indicators at read-time
- include eligible=0