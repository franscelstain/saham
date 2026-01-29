# Policy: POSITION_TRADE (SOP)

Dokumen ini hanya mendefinisikan aturan spesifik policy. Aturan universal wajib lihat `watchlist.md` bagian **Global Contract**.

Kontrak lintas-policy ada di `watchlist.md`.

---

## 0) Tujuan & horizon
- Horizon: 2–8 minggu.
- Target cuan realistis: 8%–25%.
- Entry: setup_type deterministik (BREAKOUT / PULLBACK) di uptrend kuat.

## A) Definisi setup (SOP, objektif)

### A.1 Breakout_N (EOD)
Breakout dianggap valid jika semua terpenuhi:
1. `close > highest_high(N)`  (highest high dari N trading days **sebelum** trade_date)
2. `close_pos >= BREAKOUT_MIN_CLOSE_POS`  (default 0.70)
3. `vol_ratio >= BREAKOUT_MIN_VOL_RATIO` (policy-specific default)

Jika (1) terpenuhi tapi (2) gagal → dianggap breakout “lemah” (boleh jadi Soft/Risk sesuai policy).

### A.2 Pullback_to_MA (EOD)
Pullback dianggap valid jika semua terpenuhi:
1. Trend context terpenuhi (policy-specific, mis. ma50_vs_ma200 trend_ok)
2. Jarak ke MA target kecil:
   - `abs(close - MAx) <= PULLBACK_MAX_MA_DIST_ATR * atr14` (default 0.5)
3. Ada reversal candle bullish:
   - `close > open`
   - `lower_wick / range >= PULLBACK_MIN_LOWER_WICK_RATIO` (default 0.30)
   - `close_pos >= PULLBACK_MIN_CLOSE_POS` (default 0.60)

### A.3 Resistance/Support (EOD proxy)
- `resistance_N = highest_high(N)` (N trading days sebelum trade_date)
- `support_N = lowest_low(N)` (N trading days sebelum trade_date)

Dipakai untuk membatasi TP dan mendeteksi “near resistance” secara deterministik.

---

## 1) Input minimum (PLAN, EOD-only)
Wajib:
- OHLCV
- `ma50`, `ma200`
- `atr14`, `atr_pct`
- `highest_high(50)`, `lowest_low(50)`

Jika missing → DROP `PT_DATA_INCOMPLETE`.

---

## 2) Hard Rules
### 2.1 Trend gate (wajib)
Wajib:
- `close > ma200` **dan** `ma50 > ma200`
Jika gagal → DROP `PT_TREND_NOT_OK`.

### 2.2 Setup gate
Salah satu:
- Breakout_50 (A.1 N=50, dengan vol_ratio policy default)
- Pullback_to_MA50 (A.2 dengan MA=ma50)
Jika gagal → DROP `PT_NO_SETUP`.

### 2.3 Volatility feasibility
Wajib:
- `atr_pct <= PT_MAX_ATR_PCT` (default 0.12)
Jika gagal → DROP `PT_VOL_TOO_HIGH`.

### 2.4 Stop feasibility
Wajib:
- `stop_distance_pct <= PT_MAX_STOP_PCT` (default 0.12)
Jika gagal → DROP `PT_STOP_TOO_WIDE`.

### 2.5 RR gate (LOCKED)

RR gate memakai hasil dari **PLAN Entry/Stop/TP (locked, EOD-only)**.

Definisi:
- `R = plan.entry - plan.stop`
- `rr_est = (plan.tp1 - plan.entry) / R`  (kontrak global)

Binding check:
- Wajib `rr_est >= PT_MIN_RR`, jika gagal → DROP `PT_RR_TOO_LOW`.

Catatan:
- Policy ini **tidak** mendefinisikan formula TP1 terpisah di hard rules. Satu-satunya sumber kebenaran untuk `plan.tp1` adalah PLAN locked.

## 3) Soft Rules
- Trend strength: close jauh di atas ma200 → `PT_TREND_STRONG`
- Breakout close_pos tinggi → `PT_CLOSE_STRONG`
- Penalti overheat (rsi tinggi + vol ekstrem) → `PT_OVERHEAT`

---

## 4) Risk Rules (Avoid)
Avoid jika:
- blow-off proxy: rsi14 >= 80 dan vol_ratio >= 2.0 dan close_pos >= 0.8 → `PT_BLOWOFF_RISK`
- near resistance_50 <= 2% → `PT_NEAR_RESISTANCE`

---

## 5) Ranking Factors
1. Trend strength (ma50-ma200, close-ma200)
2. Setup quality (breakout/pullback cleanliness)
3. RR quality (rr_est)
4. Stability (atr_pct)
5. Liquidity

---

## 7) CONFIRM
Default guard:
- PT_MAX_GAP_PCT = 0.05
- PT_MAX_CHASE_PCT = 0.03
Snapshot missing → PENDING.

---

## 8) Threshold defaults (LOCKED)

- `PT_MIN_RR = 2.0`
- `PT_MAX_ATR_PCT = 0.12`
- `PT_MAX_ATR_PCT_SHOCK = 0.15`
- `PT_MAX_STOP_PCT = 0.12`
- `PT_NEAR_RESIST_PCT = 0.02`
- `PT_BLOWOFF_RSI = 80`
- `PT_BLOWOFF_VOL_RATIO = 2.0`
- `PT_CONFIRM_MAX_GAP_PCT = 0.05`
- `PT_CONFIRM_MAX_CHASE_PCT = 0.03`

---

## 6) Invalidation & Trade Management (minimal SOP)

Tujuan: definisikan kapan setup dianggap **gagal** setelah entry (supaya tidak nyangkut).

- **Horizon:** 2–8 minggu (trend following)
- **Time stop (default):** 20 trading days sejak entry. Time stop adalah exit **terpisah** dari trend gate (lihat invalidation).

### Invalidation rules (hard) (LOCKED)

- Jika `close_eod <= plan.stop` → exit (reason: `STOP_HIT`).
- Trend broken: jika `close_eod < ma200` **dua hari berturut-turut** → exit (reason: `TREND_BROKEN`).
- Volatility shock: jika `atr_pct > PT_MAX_ATR_PCT_SHOCK` → **Avoid** untuk entry baru (bukan exit otomatis), reason `PT_ATR_SHOCK`.

### Management (minimal)
- Jika harga bergerak +1R, boleh set stop ke **breakeven** (opsional; tidak mengubah PLAN, hanya eksekusi).
- Jika gap turun melewati stop (slippage), catat `GAP_THROUGH_STOP`.

### CONFIRM note
Confirm lebih longgar; fokus pada trend dan stop.
---

## Setup Type & Recommendation Execution (policy-specific)
Policy ini wajib mengisi `setup_type` untuk setiap kandidat:
- `setup_type ∈ {BREAKOUT, PULLBACK}`

Default execution mapping (dipakai oleh engine Recommendations):
- BREAKOUT → 2_TRANCHE (60/40)
- PULLBACK → 3_TRANCHE (50/30/20)

Catatan: mapping ini **tidak** mengubah hard rules; hanya menentukan bentuk tranche eksekusi untuk ticker yang sudah qualified.

---

### s_trend (LOCKED)

Input:
- `ma50`, `ma200`

Definisi:
- `trend_ratio = ma50 / ma200`

Mapping (clamp 0..1):
- `s_trend = clamp((trend_ratio - 0.95) / (1.10 - 0.95), 0, 1)`

Interpretasi:
- `trend_ratio <= 0.95` → 0
- `trend_ratio >= 1.10` → 1

## Score components & weights (LOCKED, binding)

### Default weights (sum = 1.00)
- `s_trend`     = 0.30
- `s_structure` = 0.25
- `s_pattern`   = 0.15
- `s_volume`    = 0.15
- `s_risk`      = 0.15

### Clamp rules (0..1)
- `s_trend`: gunakan structural trend `ma50_vs_ma200` (LOCKED).
  - lo=-0.01, hi=+0.04
- `s_structure`: jarak entry ke resistance_50 (lebih dekat ke breakout level lebih baik)
  - `dist = (resistance_50 - close)/close` → lo=-0.02, hi=+0.03 (clamp; negatif berarti sudah di atas)
- `s_pattern`: classifier 0..1. Jika sumber 0..100 → konversi `x/100` lalu clamp 0..1.
- `s_volume`: `rvol20` lo=1.0 hi=3.0
- `s_risk`: inverse `stop_pct` (lo=0.01 hi=0.12) dan `atr_pct` (lo=0.01 hi=0.12)

`score_total = clamp(Σ(w_i*s_i), 0, 1)`

---

## PLAN Entry/Stop/TP (locked, EOD-only)

Position Trade harus menghasilkan PLAN level deterministik agar RR gate + sizing konsisten.

Definisi level bantu:
- `resistance_50 = highest_high(50) + tick` (sebelum trade_date)
- `support_10 = lowest_low(10)` (sebelum trade_date)
- Semua harga PLAN di-round sesuai tick ladder.

### Setup type
- `setup_type = BREAKOUT` jika `close >= resistance_50 - tick`
- selain itu `setup_type = PULLBACK`

### Entry (plan.entry)
- BREAKOUT: `plan.entry = round_up(resistance_50)`
- PULLBACK: `plan.entry = round_up(close(trade_date))`

### Stop (plan.stop) (DECIDED)
Satu pendekatan final:
- `plan.stop = round_down(min(low(trade_date), support_10) - tick)`

Validasi:
- `R = plan.entry - plan.stop` harus lulus kontrak global (`R > 0` dan `R >= tick`), jika tidak → DROP (`PT_R_INVALID_*`).

### TP1 / RR (GLOBAL-consistent) (LOCKED)

Kontrak global: `rr_est` selalu dihitung dari TP1.

Definisi resistance:
- `resistance_50 = highest_high(50) + tick` (sebelum trade_date)

TP1 raw:
- `tp1_raw = plan.entry + (PT_MIN_RR * R)`  (sehingga `rr_est` target = `PT_MIN_RR`)

Cap TP1 (anti over-optimistic) **hanya untuk PULLBACK**:
- Jika `setup_type = PULLBACK` → `plan.tp1 = round_down(min(tp1_raw, resistance_50))`
- Jika `setup_type = BREAKOUT` → `plan.tp1 = round_down(tp1_raw)` (tanpa cap)

Validasi:
- Wajib `plan.tp1 > plan.entry` (kalau tidak → DROP `PT_TP1_NOT_ABOVE_ENTRY`)
- `rr_est = (plan.tp1 - plan.entry) / R`
- Binding check: `rr_est >= PT_MIN_RR` (kalau tidak → DROP `PT_RR_TOO_LOW`)
### TP2 (opsional, management target) (LOCKED jika diisi)
- `plan.tp2 = round_down(plan.entry + (PT_TP2_R_MULT * R))` (opsional; bukan untuk RR gate)

Default parameter:
- `PT_TP2_R_MULT = 3.0`

## Invalidation & monitoring (EOD-only)
- Setelah entry: invalid jika daily close <= plan_stop (EOD-only).
- Jika trend gate memakai MA200, dan daily close < ma200 setelah entry, tandai `TREND_BREAK` (monitoring).

## CONFIRM hints (intraday, non-binding)
CONFIRM hanya boleh approve/reject/adjust timing; tidak boleh mengubah PLAN.

- Reject jika open hari ini gap-up membuat stop terlalu jauh vs risk budget.
- Reject jika spread tinggi / depth tipis (slippage).
- Jika terjadi halt/reopen intraday, jangan entry (CONFIRM reject).
