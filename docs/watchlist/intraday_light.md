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
  - `preopen_last_price` atau `open_or_last_exec` (sesuai implementasi)
  - spread proxy / orderbook quality (jika ada)

---

## 2) Hard filters
- Snapshot intraday **harus ada** → jika tidak ada: `IL_SNAPSHOT_MISSING` (policy tidak dipakai)
- `liq_bucket` harus `A` → `IL_LIQ_TOO_LOW`
- `atr_pct <= 0.06` → `IL_VOL_TOO_HIGH`

---

## 3) Soft filters + scoring

### 3.1 Base score
- `base_score = 100`
- Score floor = 0.
- Score dipakai untuk grouping deterministik (Section 5.6).

### 3.2 Penalti (soft)
- Jika spread proxy buruk → score -10 → `IL_SPREAD_WIDE`
- RSI overheating (`rsi14 >= 78`) → score -6, entry wait → `IL_RSI_OVERHEAT`
---

## 4) Setup allowlist
- `Breakout` dan `Continuation` saja (intraday butuh momentum jelas)
- Setup lain → WATCH_ONLY → `IL_SETUP_NOT_ALLOWED`

---

## 5) Entry rules (anti-chasing/gap)
- Entry windows: ["09:20-10:15", "13:35-14:15"]
- No entry: ["09:00-09:15", "11:30-13:30", "15:15-close"]  # hindari lunch lull + auction noise
- Snapshot intraday **wajib** untuk NEW ENTRY:
  - jika snapshot tidak ada → kandidat wajib `WATCH_ONLY` → `IL_SNAPSHOT_MISSING`
- Anti-chasing:
  - `max_chase_from_close_pct = 0.010` → `IL_CHASE_BLOCK_DISTANCE_TOO_FAR`
- Gap-up guard:
  - `max_gap_up_pct = 0.015` → `IL_GAP_UP_BLOCK`
---

## 5.6 Final selection & grouping (mechanism)

Bagian ini mengunci **mekanisme seleksi kandidat beli intraday** agar output deterministik.

### 5.6.1 Eligibility untuk NEW ENTRY (hard)
Kandidat eligible NEW ENTRY hanya jika:
- Snapshot intraday tersedia (Section 2), dan
- Lolos Hard filters (liq_bucket, atr_pct), dan
- Setup termasuk allowlist (Section 4), dan
- Tidak terkena guard `IL_CHASE_BLOCK_DISTANCE_TOO_FAR` / `IL_GAP_UP_BLOCK`, dan
- `timing.trade_disabled == false` (global gates di watchlist.md), dan
- `max_positions > 0`.

Jika salah satu gagal → kandidat wajib `WATCH_ONLY`.

### 5.6.2 Trade viability (minimum RR, tanpa fee model)
Untuk kandidat eligible NEW ENTRY:
- Jika `levels.entry_trigger_price`, `levels.stop_loss_price`, dan `levels.tp1_price` tersedia:
  - `rr = (tp1 - entry) / max(entry - sl, 1e-9)`
  - Jika `rr < 1.6` → DROP → `IL_MIN_TRADE_VIABILITY_FAIL`
- Jika salah satu level tidak tersedia → WATCH_ONLY → `IL_LEVELS_INCOMPLETE`

### 5.6.3 Mapping score → group
- `top_picks`: `score >= 80`, ambil maksimal `max_positions`
- `secondary`: `65 <= score < 80`
- `watch_only`: `score < 65`

### 5.6.4 Ordering deterministik (tie-breaker)
Sort kandidat eligible berdasarkan:
1) `score desc`
2) `watchlist_score desc` (jika tersedia)
3) `ticker_code asc`

## 6) Exit rules (intraday)
- Time stop:
  - jika setelah 90 menit tidak follow-through → exit → `IL_TIME_STOP_90M`
- EOD flat:
  - posisi intraday harus flat sebelum close → `IL_FLAT_BEFORE_CLOSE`

---

## 7) Sizing defaults
- `risk_per_trade_pct = 0.003` (0.30%)
- `max_positions = 1`
- `max_holding_minutes = 180` (3 jam)  # tetap intraday-light, bukan daytrade berat
- Viability:
  - `min_alloc_idr = 500_000`
  - `min_lots = 1`
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
- `IL_LEVELS_INCOMPLETE`
- `IL_MIN_TRADE_VIABILITY_FAIL`
- `IL_TIME_STOP_90M`
- `IL_FLAT_BEFORE_CLOSE`
