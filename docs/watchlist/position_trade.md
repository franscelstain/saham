# Policy: POSITION_TRADE

Dokumen ini adalah **single source of truth** untuk policy **POSITION_TRADE**.
Semua angka/threshold dan **UI reason codes** untuk policy ini harus berasal dari dokumen ini.

Dependensi lintas policy (Data Dictionary, output schema, namespace reason codes, tick rounding) ada di `watchlist.md`.

---

## 0) Intent & scope
Trend-follow 2–8 minggu. Lebih sedikit trade, lebih fokus kualitas trend dan risk control.

---

## 1) Data dependency
- Canonical EOD + indikator lengkap (MA20/50/200, RSI, ATR, dv20, liq_bucket)
- Portfolio context untuk mode MANAGE (posisi berjalan)

---

## 2) Hard filters
- `liq_bucket` harus `A` atau `B` → `PT_LIQ_TOO_LOW`
- Trend gate:
  - `close > ma200` dan `ma50 > ma200` → `PT_TREND_NOT_OK`
- Volatility gate:
  - `atr_pct <= 0.07` → `PT_VOL_TOO_HIGH`

---

## 3) Soft filters + scoring
- RSI terlalu tinggi (`rsi14 >= 78`) → score -6 → `PT_RSI_OVERHEAT`
- Candle distribusi kuat → score -6 → `PT_WICK_DISTRIBUTION`

---

## 4) Setup allowlist
- `Pullback` dan `Continuation` prioritas
- `Breakout` hanya jika trend gate kuat
- `Reversal` jarang dipakai

---

## 5) Entry rules
- Entry windows default: ["09:20-10:30", "13:35-14:30"]
- Anti-chasing:
  - `max_chase_from_close_pct = 0.02` → `PT_CHASE_BLOCK_DISTANCE_TOO_FAR`

---

## 6) Exit rules
- Max holding: `max_holding_days = 40` trading days → `PT_MAX_HOLDING_REACHED`
- Trailing stop:
  - `trail_atr_mult = 2.5` → `PT_TRAIL_STOP_HIT`

---

## 7) Sizing defaults
- `risk_per_trade_pct = 0.010` (1.0%)
- `max_positions = 3`
- Viability:
  - `min_alloc_idr = 1_000_000`
  - `min_lots = 1`
  - `min_net_edge_pct = 0.012` → `PT_MIN_TRADE_VIABILITY_FAIL`

---

## 8) Reason codes (UI)
- `PT_LIQ_TOO_LOW`
- `PT_TREND_NOT_OK`
- `PT_VOL_TOO_HIGH`
- `PT_RSI_OVERHEAT`
- `PT_WICK_DISTRIBUTION`
- `PT_CHASE_BLOCK_DISTANCE_TOO_FAR`
- `PT_MAX_HOLDING_REACHED`
- `PT_TRAIL_STOP_HIT`
- `PT_MIN_TRADE_VIABILITY_FAIL`
