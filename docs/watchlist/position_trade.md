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
- Portfolio context untuk manage posisi berjalan (lihat field canonical di `watchlist.md`)

---

## 2) Hard filters
- `liq_bucket` harus `A` atau `B` → `PT_LIQ_TOO_LOW`
- Trend gate:
  - `close > ma200` dan `ma50 > ma200` → `PT_TREND_NOT_OK`
- Volatility gate:
  - `atr_pct <= 0.07` → `PT_VOL_TOO_HIGH`

---

## 3) Soft filters + scoring

### 3.1 Base score (deterministik)
- `base_score = 100`
- Score floor = 0.
- Score dipakai untuk grouping deterministik (Section 5.6).

### 3.2 Penalti (soft)
- RSI terlalu tinggi (`rsi14 >= 78`) → score -6 → `PT_RSI_OVERHEAT`
- Candle distribusi kuat (upper wick dominan / close melemah) → score -6 → `PT_WICK_DISTRIBUTION`

---

## 4) Setup allowlist (deterministik)

### 4.1 Recommended
- `Pullback`
- `Continuation`

### 4.2 Conditional (rule-based)
- `Breakout`:
  - Hanya jika trend gate kuat (lolos Hard filters trend gate).
  - Jika trend gate tidak kuat → WATCH_ONLY → `PT_BREAKOUT_BLOCK_TREND_NOT_OK`
- `Reversal`:
  - Default **disabled** untuk policy ini (lebih cocok untuk weekly_swing / intraday_light).
  - Jika dipaksa (manual override), wajib `watch_only` secara default → `PT_REVERSAL_DISABLED_DEFAULT`

Catatan: allowlist adalah preferensi setup. Eligibility NEW ENTRY tetap dikunci oleh Section 5.6.

---

## 5) Entry rules (anti-chasing)

### 5.1 Entry windows
- Entry windows default: ["09:20-10:30", "13:35-14:30"]
- Avoid windows: ["09:00-09:20", "14:30-close"]

### 5.2 Anti-chasing
- `max_chase_from_close_pct = 0.02` → `PT_CHASE_BLOCK_DISTANCE_TOO_FAR`

### 5.3 Gap-up guard (optional tetapi deterministik)
- Jika `gap_pct` tersedia (lihat definisi gap_pct di `watchlist.md`):
  - `max_gap_up_pct = 0.03` → `PT_GAP_UP_BLOCK`
- Jika `gap_pct` tidak tersedia, rule ini **tidak dievaluasi** (tidak boleh asumsi 0).

### 5.4 Pyramiding / add-on (explicit)
- `allow_pyramiding = false` (default). Tidak ada add-on position untuk policy ini.
  - Alasan: menjaga risk budget & determinisme sizing.
  - Jika diaktifkan di masa depan, harus dibuat policy version baru dengan kontrak output khusus.

---

## 5.6 Final selection & grouping (mechanism)

Bagian ini mengunci **mekanisme seleksi kandidat beli** agar implementasi deterministik.

### 5.6.1 Eligibility untuk NEW ENTRY (hard)
Kandidat eligible NEW ENTRY hanya jika:
- Lolos semua Hard filters (Section 2), dan
- Tidak terkena guard: `PT_CHASE_BLOCK_DISTANCE_TOO_FAR` (dan `PT_GAP_UP_BLOCK` jika dievaluasi), dan
- `timing.trade_disabled == false` (global gates di watchlist.md).

Jika salah satu gagal → kandidat wajib `WATCH_ONLY`.

### 5.6.2 Trade viability (minimum edge)
- Viability minimum:
  - `min_alloc_idr = 1_000_000`
  - `min_lots = 1`
- Jika level tersedia (`levels.entry_trigger_price`, `levels.stop_loss_price`, `levels.tp1_price`):
  - `rr = (tp1 - entry) / max(entry - sl, 1e-9)`
  - Jika `rr < 2.0` → DROP → `PT_MIN_TRADE_VIABILITY_FAIL`
- Jika level tidak lengkap → WATCH_ONLY → `PT_LEVELS_INCOMPLETE`

### 5.6.3 Mapping score → group
Untuk kandidat eligible NEW ENTRY dan lolos viability:
- `top_picks`:
  - `score >= 80`, ambil maksimal `max_positions` kandidat teratas (tie-breaker 5.6.4).
- `secondary`:
  - `65 <= score < 80`
- `watch_only`:
  - `score < 65`

### 5.6.4 Ordering deterministik (tie-breaker)
Sort kandidat eligible berdasarkan:
1) `score desc`
2) `watchlist_score desc` (jika tersedia)
3) `ticker_code asc`

---

## 6) Exit rules (position management)

### 6.1 Max holding (time stop)
- Max holding: `max_holding_days = 40` trading days → `PT_MAX_HOLDING_REACHED` (EXIT/REDUCE)

### 6.2 Partial take profit (profit lock)
- Jika `tp1_price` tersentuh:
  - `scale_out_pct = 0.50` (jual 50%)
  - Pindahkan stop loss ke breakeven (`entry_trigger_price`) → `PT_MOVE_SL_TO_BE`
  - Sisanya dikelola dengan trailing stop (6.3)

### 6.3 Trailing stop
- `trail_atr_mult = 2.5` → `PT_TRAIL_STOP_HIT`

### 6.4 Fail-to-move time stop (optional rule, deterministik)
- Jika sudah `days_held >= 20` dan belum pernah menyentuh TP1:
  - Jika trend melemah (mis. `close < ma50`) → REDUCE/EXIT → `PT_TIME_STOP_T1`

---

## 7) Sizing defaults
- `risk_per_trade_pct = 0.010` (1.0%)
- `max_positions = 3`

---

## 8) Reason codes (UI)
- `PT_LIQ_TOO_LOW`
- `PT_TREND_NOT_OK`
- `PT_VOL_TOO_HIGH`
- `PT_RSI_OVERHEAT`
- `PT_WICK_DISTRIBUTION`
- `PT_BREAKOUT_BLOCK_TREND_NOT_OK`
- `PT_REVERSAL_DISABLED_DEFAULT`
- `PT_CHASE_BLOCK_DISTANCE_TOO_FAR`
- `PT_GAP_UP_BLOCK`
- `PT_LEVELS_INCOMPLETE`
- `PT_MIN_TRADE_VIABILITY_FAIL`
- `PT_MAX_HOLDING_REACHED`
- `PT_MOVE_SL_TO_BE`
- `PT_TIME_STOP_T1`
- `PT_TRAIL_STOP_HIT`
