# Policy: DIVIDEND_SWING

Dokumen ini adalah **single source of truth** untuk policy **DIVIDEND_SWING**.
Semua angka/threshold dan **UI reason codes** untuk policy ini harus berasal dari dokumen ini.

Dependensi lintas policy (Data Dictionary, output schema, namespace reason codes, tick rounding) ada di `watchlist.md`.

---

## 0) Intent & scope
Target: mengambil peluang dividen + swing pendek tanpa bunuh diri karena gap/event risk.

---

## 1) Data dependency
### 1.1 Wajib
- Canonical EOD + indikator minimal (MA, RSI, ATR, dv20, liq_bucket)
- Calendar event dividen (minimal):
  - `ex_date`, `cum_date` (atau `record_date`), `cash_dividend`, `dividend_yield_est`

### 1.2 Opsional
- Preopen snapshot untuk gap guard hari eksekusi (lihat watchlist.md)

---

## 2) Hard filters (angka tegas)
- `liq_bucket` harus `A` atau `B` â†’ `DS_LIQ_TOO_LOW`
- `atr_pct <= 0.08` â†’ `DS_VOL_TOO_HIGH`
- Event gate:
  - `cum_date` harus dalam `3..12` trading days ke depan (window entry) â†’ `DS_EVENT_WINDOW_OUTSIDE`
- Yield sanity:
  - `dividend_yield_est >= 0.020` (â‰¥2%) â†’ kalau tidak, tetap boleh tapi confidence turun (lihat soft) atau DROP jika kamu mau ketat.

---

## 3) Soft filters + score override
- Jika `rsi14 >= 75` â†’ score -6, entry_style `Pullback-wait` â†’ `DS_RSI_OVERHEAT`
- Jika gap risk tinggi (gap_pct >= 0.04) â†’ score -6 â†’ `DS_GAP_RISK_EOD`

---

## 4) Setup allowlist
- `Pullback` atau `Continuation` lebih diutamakan.
- `Breakout` boleh jika bukan late-cycle menjelang cum_date.
- `Reversal` hanya jika trend tidak bearish.

---

## 5) Entry rules (anti-chasing/gap)
- DOW bias: entry ideal Selasaâ€“Rabu, Hindari entry Jumat.
- Entry window default: ["09:20-10:30", "13:35-14:30"]
- Anti-chasing:
  - `max_chase_from_close_pct = 0.015` â†’ `DS_CHASE_BLOCK_DISTANCE_TOO_FAR`
- Gap-up guard:
  - `max_gap_up_pct = 0.02` â†’ `DS_GAP_UP_BLOCK`

---

## 6) Exit rules
- Default: exit **sebelum** ex_date jika tujuan utama yield+safe (kecuali strategi hold ex-date memang diaktifkan).
- Time stop:
  - T+2 jika `ret_since_entry_pct < 0.008` â†’ `DS_TIME_STOP_T2`
- Max holding:
  - `max_holding_days = 6` â†’ `DS_MAX_HOLDING_REACHED`

---

## 7) Sizing defaults
- `risk_per_trade_pct = 0.006` (0.60%)
- `max_positions = 2`
- Viability:
  - `min_alloc_idr = 750_000`
  - `min_lots = 1`
  - `min_net_edge_pct = 0.010` â†’ `DS_MIN_TRADE_VIABILITY_FAIL`

---

## 8) Reason codes (UI)
- `DS_LIQ_TOO_LOW`
- `DS_VOL_TOO_HIGH`
- `DS_EVENT_WINDOW_OUTSIDE`
- `DS_RSI_OVERHEAT`
- `DS_GAP_RISK_EOD`
- `DS_CHASE_BLOCK_DISTANCE_TOO_FAR`
- `DS_GAP_UP_BLOCK`
- `DS_TIME_STOP_T2`
- `DS_MAX_HOLDING_REACHED`
- `DS_MIN_TRADE_VIABILITY_FAIL`

