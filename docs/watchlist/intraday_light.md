# Policy: INTRADAY_LIGHT

Dokumen ini adalah **single source of truth** untuk policy **INTRADAY_LIGHT**.
Semua angka/threshold dan **UI reason codes** untuk policy ini harus berasal dari dokumen ini.

Dependensi lintas policy (Data Dictionary, output schema, namespace reason codes, tick rounding) ada di `watchlist.md`.

---

## 0) Intent & scope
Intraday â€œringanâ€ untuk eksekusi cepat berbasis setup EOD kuat, dengan guard spread/liquidity ketat.
Jika snapshot intraday tidak ada â†’ policy ini tidak boleh aktif.

---

## 1) Data dependency
### 1.1 Wajib
- Canonical EOD + indikator minimal (MA, RSI, ATR, dv20, liq_bucket)
- Snapshot intraday minimal (opening range / last price) untuk eksekusi:
  - `preopen_last_price` atau `intraday_last_price` (sesuai implementasi)
  - spread proxy / orderbook quality (jika ada)

---

## 2) Hard filters
- Snapshot intraday **harus ada** â†’ jika tidak ada: `IL_SNAPSHOT_MISSING` (policy tidak dipakai)
- `liq_bucket` harus `A` â†’ `IL_LIQ_TOO_LOW`
- `atr_pct <= 0.06` â†’ `IL_VOL_TOO_HIGH`

---

## 3) Soft filters + scoring
- Jika spread proxy buruk â†’ score -10 â†’ `IL_SPREAD_WIDE`
- RSI overheating (`rsi14 >= 78`) â†’ entry wait â†’ `IL_RSI_OVERHEAT`

---

## 4) Setup allowlist
- `Breakout` dan `Continuation` saja (intraday butuh momentum jelas)
- Setup lain â†’ WATCH_ONLY â†’ `IL_SETUP_NOT_ALLOWED`

---

## 5) Entry rules (anti-chasing/gap)
- Entry windows: ["09:20-10:15", "13:35-14:15"]
- No entry: ["09:00-09:15", "15:15-close"]
- Anti-chasing:
  - `max_chase_from_close_pct = 0.010` â†’ `IL_CHASE_BLOCK_DISTANCE_TOO_FAR`
- Gap-up guard:
  - `max_gap_up_pct = 0.015` â†’ `IL_GAP_UP_BLOCK`

---

## 6) Exit rules (intraday)
- Time stop:
  - jika setelah 90 menit tidak follow-through â†’ exit â†’ `IL_TIME_STOP_90M`
- EOD flat:
  - posisi intraday harus flat sebelum close â†’ `IL_FLAT_BEFORE_CLOSE`

---

## 7) Sizing defaults
- `risk_per_trade_pct = 0.003` (0.30%)
- `max_positions = 1`
- Viability:
  - `min_alloc_idr = 500_000`
  - `min_lots = 1`
  - `min_net_edge_pct = 0.006` â†’ `IL_MIN_TRADE_VIABILITY_FAIL`

---

## 8) Reason codes (UI)
- `IL_SNAPSHOT_MISSING`
- `IL_LIQ_TOO_LOW`
- `IL_VOL_TOO_HIGH`
- `IL_SPREAD_WIDE`
- `IL_RSI_OVERHEAT`
- `IL_SETUP_NOT_ALLOWED`
- `IL_CHASE_BLOCK_DISTANCE_TOO_FAR`
- `IL_GAP_UP_BLOCK`
- `IL_TIME_STOP_90M`
- `IL_FLAT_BEFORE_CLOSE`
- `IL_MIN_TRADE_VIABILITY_FAIL`

