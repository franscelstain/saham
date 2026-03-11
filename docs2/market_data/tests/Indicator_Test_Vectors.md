# Indicator Test Vectors

Provide explicit expected values for:
- `TR`
- `ATR14` seed and recursive next steps
- `dv20_idr`
- `vol_ratio`
- `roc20`
- `hh20`

Vectors must be based on trading-day windows and must include at least one case with:
- `adj_close` present
- `adj_close` missing so fallback to `close` is exercised
- insufficient-history case