# Indicator Computation Specification (EOD)

Inputs: eod_bars
Windows: trading-day order

Price basis:
- P(T)=adj_close if default ADJ_CLOSE and available else close
ATR uses real OHLC.

Baseline formulas:
- dv20_idr: avg(turnover_idr over 20 trading days)
- TR: max(high-low, abs(high-prev_close), abs(low-prev_close))
- ATR14 Wilder: seed avg(TR14), then (prev*13+TR)/14
- atr14_pct = ATR14/close*100
- vol_ratio = vol(T)/avg(vol over N)
- roc20 = P(T)/P(D[-20]) - 1
- hh20 = max(high over last 20 trading days incl today)

Versioning: indicator_set_version bump when semantics change.