# Policy: INTRADAY_LIGHT

Dokumen ini adalah **single source of truth** untuk policy **INTRADAY_LIGHT**.
Semua angka/threshold dan **UI reason codes** untuk policy ini harus berasal dari dokumen ini.

Dependensi lintas policy (Data Dictionary, output schema, namespace reason codes, tick rounding) ada di `watchlist.md`.

---

## 0) Intent & scope
Intraday “ringan” untuk eksekusi cepat berbasis setup EOD kuat, dengan guard spread/liquidity ketat.
Jika snapshot intraday tidak ada → policy ini tidak boleh aktif.

---

## 1) Data dependency
### 1.1 Wajib
- Canonical EOD + indikator minimal (MA, RSI, ATR, dv20, liq_bucket)
- Snapshot intraday minimal (opening range / last price) untuk eksekusi:
  - `preopen_last_price` atau `intraday_last_price` (sesuai implementasi)
  - spread proxy / orderbook quality (jika ada)

---

## 2) Hard filters
- Snapshot intraday **harus ada** → jika tidak ada: `IL_SNAPSHOT_MISSING` (policy tidak dipakai)
- `liq_bucket` harus `A` → `IL_LIQ_TOO_LOW`
- `atr_pct <= 0.06` → `IL_VOL_TOO_HIGH`

---

## 3) Soft filters + scoring
- Jika spread proxy buruk → score -10 → `IL_SPREAD_WIDE`
- RSI overheating (`rsi14 >= 78`) → entry wait → `IL_RSI_OVERHEAT`

---

## 4) Setup allowlist
- `Breakout` dan `Continuation` saja (intraday butuh momentum jelas)
- Setup lain → WATCH_ONLY → `IL_SETUP_NOT_ALLOWED`

---

## 5) Entry rules (anti-chasing/gap)
- Entry windows: ["09:20-10:15", "13:35-14:15"]
- No entry: ["09:00-09:15", "15:15-close"]
- Anti-chasing:
  - `max_chase_from_close_pct = 0.010` → `IL_CHASE_BLOCK_DISTANCE_TOO_FAR`
- Gap-up guard:
  - `max_gap_up_pct = 0.015` → `IL_GAP_UP_BLOCK`

---

## 6) Exit rules (intraday)
- Time stop:
  - jika setelah 90 menit tidak follow-through → exit → `IL_TIME_STOP_90M`
- EOD flat:
  - posisi intraday harus flat sebelum close → `IL_FLAT_BEFORE_CLOSE`

---

## 7) Sizing defaults
- `risk_per_trade_pct = 0.003` (0.30%)
- `max_positions = 1`
- Viability:
  - `min_alloc_idr = 500_000`
  - `min_lots = 1`
  - `min_net_edge_pct = 0.006` → `IL_MIN_TRADE_VIABILITY_FAIL`

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
