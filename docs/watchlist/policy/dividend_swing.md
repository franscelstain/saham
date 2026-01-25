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

### 1.2 Opsional (input)
- Preopen snapshot untuk gap guard hari eksekusi (lihat watchlist.md).
  - Jika **tidak tersedia**, aturan deterministik ada di Section 5.3: NEW ENTRY wajib `WATCH_ONLY` (`DS_PREOPEN_PRICE_MISSING`).
---

## 2) Hard filters (angka tegas)
- `liq_bucket` harus `A` atau `B` → `DS_LIQ_TOO_LOW`
- `atr_pct <= 0.08` → `DS_VOL_TOO_HIGH`

### 2.1 Event gate (dividend window)
- `cum_date` harus dalam `3..12` trading days ke depan (window entry) → `DS_EVENT_WINDOW_OUTSIDE`

### 2.2 Yield sanity (deterministik)
- `dividend_yield_est >= 0.020` (≥2%) adalah preferensi.
- Jika `dividend_yield_est < 0.020` **tidak DROP**, tetapi:
  - apply soft penalty (lihat Section 3) + reason `DS_YIELD_LOW`
  - confidence maksimum menjadi `Medium` (tidak boleh `High`)


---

## 3) Soft filters + score override

### 3.1 Base score
- `base_score = 100`
- Score minimum = 0 (floor). Score dipakai untuk grouping deterministik (Section 5.6).

### 3.2 Penalti (soft)
- Jika `dividend_yield_est < 0.020` → score -10 → `DS_YIELD_LOW`
- Jika `rsi14 >= 75` → score -6, entry_style `Pullback-wait` → `DS_RSI_OVERHEAT`
- Jika gap risk tinggi (`gap_pct >= 0.04`, definisi lihat `watchlist.md` Section 2.5.1) → score -6 → `DS_GAP_RISK_EOD`


---

## 4) Setup allowlist (deterministik)

### 4.1 Recommended
- `Pullback`
- `Continuation`

### 4.2 Conditional (rule-based)
- `Breakout`:
  - Hanya eligible NEW ENTRY jika `days_to_cum > 4` (lihat Section 5.6), selain itu WATCH_ONLY → `DS_LATE_CYCLE_BREAKOUT_BLOCK`.
  - Minimal trend: `close > ma20` dan `ma20 > ma50`, jika tidak → WATCH_ONLY → `DS_TREND_NOT_ALIGNED`.
- `Reversal`:
  - Hanya untuk trend tidak bearish: `close >= ma50` atau (`close > ma20` dan `rsi14 >= 45`)
  - Jika bearish → WATCH_ONLY → `DS_REVERSAL_BLOCK_BEARISH`


---

## 5) Entry rules (anti-chasing/gap)

### 5.1 DOW bias (deterministik)
- Entry ideal Selasa–Rabu.
- Jika `dow == Fri` → NEW ENTRY dimatikan (WATCH_ONLY) → `DS_DOW_NO_ENTRY`.

### 5.2 Entry windows
- Entry window default: ["09:20-10:30", "13:35-14:30"]
- Avoid windows: ["09:00-09:20", "14:30-close"]

### 5.3 Preopen snapshot requirement (deterministik)
- Jika tidak ada `preopen_price` / snapshot harga preopen saat hari eksekusi:
  - Kandidat tetap boleh dipublish sebagai monitoring, tetapi NEW ENTRY harus WATCH_ONLY → `DS_PREOPEN_PRICE_MISSING`.

### 5.4 Anti-chasing
- `max_chase_from_close_pct = 0.015` → `DS_CHASE_BLOCK_DISTANCE_TOO_FAR`

### 5.5 Gap-up guard
- `max_gap_up_pct = 0.02` → `DS_GAP_UP_BLOCK`

### 5.6 Late-cycle breakout block (dividend-specific)
- Definisi `days_to_cum` = trading days dari `exec_trade_date` ke `cum_date`.
- Jika `days_to_cum <= 4` (late-cycle):
  - setup `Breakout` tidak boleh untuk NEW ENTRY (WATCH_ONLY) → `DS_LATE_CYCLE_BREAKOUT_BLOCK`
  - setup `Pullback/Continuation` masih boleh (dengan anti-chasing & gap guard di atas).


---


## 5.7 Final selection & grouping (mechanism)

Bagian ini mengunci **mekanisme seleksi kandidat beli** agar implementasi deterministik.

### 5.7.1 Eligibility untuk NEW ENTRY (hard)
Kandidat eligible NEW ENTRY hanya jika:
- Lolos semua Hard filters (Section 2), dan
- Tidak terkena guard: `DS_DOW_NO_ENTRY`, `DS_PREOPEN_PRICE_MISSING`, `DS_CHASE_BLOCK_DISTANCE_TOO_FAR`, `DS_GAP_UP_BLOCK`, `DS_LATE_CYCLE_BREAKOUT_BLOCK`, dan
- `timing.trade_disabled == false` (global gates di watchlist.md), dan
- `max_positions_today > 0`.

Jika salah satu gagal → kandidat wajib `WATCH_ONLY`.

### 5.7.2 Trade viability (minimum RR)
Untuk kandidat eligible NEW ENTRY:
- Jika `levels.entry_trigger_price`, `levels.stop_loss_price`, dan `levels.tp1_price` tersedia:
  - `rr = (tp1 - entry) / max(entry - sl, tick_size)`
  - Jika `rr < 1.8` → DROP → `DS_MIN_TRADE_VIABILITY_FAIL`
- Jika salah satu level tidak tersedia → WATCH_ONLY → `DS_LEVELS_INCOMPLETE`

### 5.7.3 Mapping score → group
Untuk kandidat eligible NEW ENTRY dan lolos viability:
- `top_picks`:
  - `score >= 80`, lalu ambil maksimal `max_positions_today` kandidat teratas (tie-breaker 5.7.4).
- `secondary`:
  - `65 <= score < 80`
- `watch_only`:
  - `score < 65`

Catatan confidence:
- Jika terkena `DS_YIELD_LOW`, `confidence` maksimum `Medium`.

### 5.7.4 Ordering deterministik (tie-breaker)
Sort kandidat eligible berdasarkan:
1) `score desc`
2) `watchlist_score desc` (jika tersedia)
3) `ticker_code asc`


## 6) Exit rules
- Default: exit **sebelum** ex_date jika tujuan utama yield+safe (kecuali strategi hold ex-date memang diaktifkan).
- Time stop:
  - T+2 jika `ret_since_entry_pct < 0.008` → `DS_TIME_STOP_T2`
- Max holding:
  - `max_holding_days = 6` → `DS_MAX_HOLDING_REACHED`

---

## 7) Sizing defaults
- `risk_per_trade_pct = 0.006` (0.60%)
- `max_positions = 2`
- Viability:
  - `min_alloc_idr = 750_000`
  - `min_lots = 1`
  - `min_net_edge_pct = 0.010` → `DS_MIN_TRADE_VIABILITY_FAIL`

---

## 8) Reason codes (UI)
- `DS_LIQ_TOO_LOW`
- `DS_VOL_TOO_HIGH`
- `DS_EVENT_WINDOW_OUTSIDE`
- `DS_YIELD_LOW`
- `DS_RSI_OVERHEAT`
- `DS_GAP_RISK_EOD`
- `DS_CHASE_BLOCK_DISTANCE_TOO_FAR`
- `DS_GAP_UP_BLOCK`
- `DS_DOW_NO_ENTRY`
- `DS_PREOPEN_PRICE_MISSING`
- `DS_LATE_CYCLE_BREAKOUT_BLOCK`
- `DS_TREND_NOT_ALIGNED`
- `DS_REVERSAL_BLOCK_BEARISH`
- `DS_LEVELS_INCOMPLETE`
- `DS_TIME_STOP_T2`
- `DS_MAX_HOLDING_REACHED`
- `DS_MIN_TRADE_VIABILITY_FAIL`
