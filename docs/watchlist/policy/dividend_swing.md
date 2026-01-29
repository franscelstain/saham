# Policy: DIVIDEND_SWING (SOP)

Dokumen ini hanya mendefinisikan aturan spesifik policy. Aturan universal wajib lihat `watchlist.md` bagian **Global Contract**.

Kontrak lintas-policy ada di `watchlist.md`.

---

## 0) Tujuan & horizon
- Horizon: 3–10 trading days di sekitar dividend event (entry sebelum ex-date).
- Target cuan realistis: 1%–4% (konservatif), fokus risk control (gap).
- Entry style: pullback/trend-follow, hindari chasing.

## A) Definisi setup (SOP, objektif)

### A.1 Breakout_N (EOD)
Breakout dianggap valid jika semua terpenuhi:
1. `close > highest_high(N)`  (highest high dari N trading days **sebelum** trade_date)
2. `close_pos >= BREAKOUT_MIN_CLOSE_POS`  (default 0.70)
3. `vol_ratio >= BREAKOUT_MIN_VOL_RATIO` (policy-specific default)

Jika (1) terpenuhi tapi (2) gagal → dianggap breakout “lemah” (boleh jadi Soft/Risk sesuai policy).

### A.2 Pullback_to_MA (EOD)
Pullback dianggap valid jika semua terpenuhi:
1. Trend context terpenuhi (policy-specific, mis. close>=MA20 atau MA20>=MA50)
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
- `ma20`, `ma50`
- `atr14`, `atr_pct`
- dividend events: minimal `ex_date`
Opsional:
- `cash_dividend` / `yield_est` (ranking)

Jika event tidak tersedia → DROP `DS_EVENT_MISSING`.

---

## 2) Hard Rules (gagal = gugur)
### 2.1 Event window gate
`days_to_ex = trading_days_between(exec_trade_date, ex_date)  // require exec_trade_date < ex_date else DROP DS_TOO_LATE_EXDATE`
Wajib:
- `DS_MIN_DAYS_TO_EX <= days_to_ex <= DS_MAX_DAYS_TO_EX`
Default:
- `DS_MIN_DAYS_TO_EX = 2`
- `DS_MAX_DAYS_TO_EX = 12`
Jika gagal → DROP `DS_OUTSIDE_EVENT_WINDOW`.

### 2.2 Trend sanity
Wajib:
- `trend_ok = (close >= ma20) OR (ma20 >= ma50)`
Jika gagal → DROP `DS_TREND_WEAK`.

### 2.3 Not-extended gate
Wajib:
- `(close - ma20) <= DS_MAX_EXTEND_ATR * atr14`
Default `DS_MAX_EXTEND_ATR = 1.0`
Jika gagal → DROP `DS_PRICE_EXTENDED`.

### 2.4 Stop feasibility
Wajib:
- `stop_distance_pct <= DS_MAX_STOP_PCT` (default 0.06)
Jika gagal → DROP `DS_STOP_TOO_WIDE`.

### 2.5 Setup gate (minimal harus ada)
Salah satu:
- Pullback_to_MA20 (A.2)
- Breakout_20 (A.1) **hanya jika** tidak extended (2.3 tetap wajib)
Jika tidak ada → DROP `DS_NO_SETUP`.

---

## 3) Soft Rules (boost score)
- Stability: atr_pct rendah → `DS_STABLE`
- Liquidity tinggi → `DS_LIQ_STRONG`
- Yield_est tinggi (jika tersedia) → `DS_YIELD_GOOD`

---

## 4) Risk Rules (Avoid)
Masuk Avoid jika:
- atr_pct >= DS_AVOID_ATR_PCT (default 0.12) → `DS_VOL_CHAOS`
- “Event run-up risk”: days_to_ex <= 2 **dan** vol_ratio >= 1.8 **dan** rsi14 >= 75 → `DS_EVENT_RUNUP_RISK`
- Near resistance: (resistance_20 - entry)/entry <= 0.02 → `DS_NEAR_RESISTANCE`

---

## 5) Ranking Factors (deterministik)
1. Event proximity (lebih dekat di dalam window, tapi tidak terlalu mepet)
2. Stability (atr_pct)
3. Trend/cleanliness (wick kecil, close_pos tinggi)
4. Liquidity
5. (Opsional) yield_est

Tie-breaker: risk lebih rendah → liquidity lebih tinggi → setup lebih clean.

---

## 7) CONFIRM (opsional)
Sama prinsip global: gap/spread/chase.
Default guard lebih ketat:
- DS_MAX_GAP_PCT = 0.03
- DS_MAX_CHASE_PCT = 0.015
Snapshot missing → PENDING.

---

## 8) Threshold defaults (LOCKED)

- `DS_MIN_DAYS_TO_EX = 2`
- `DS_MAX_DAYS_TO_EX = 12`
- `DS_MIN_RR = 1.2`
- `DS_MAX_STOP_PCT = 0.06`
- `DS_MAX_EXTEND_ATR = 1.0`
- `DS_AVOID_ATR_PCT = 0.12`
- `DS_CONFIRM_MAX_GAP_PCT = 0.03`
- `DS_CONFIRM_MAX_CHASE_PCT = 0.015`

---

## 6) Invalidation & Trade Management (minimal SOP)

Tujuan: definisikan kapan setup dianggap **gagal** setelah entry (supaya tidak nyangkut).

- **Horizon:** 3–10 hari sekitar ex-date/cum-date window
- **Time stop (default):** Exit paling lambat H-1 ex-date jika tujuan hanya dividend-run (konfigurabel)

### Invalidation rules (hard)
- Jika **gap down** melampaui stop pada open → exit (reason: `GAP_RISK`).
- Jika close EOD < stop → exit.
- Jika harga sudah terlalu extended (extend > 1.5 ATR dari ma20) sebelum ex-date → hindari / treat invalid.

### Management (minimal)
- Jika harga bergerak +1R, boleh set stop ke **breakeven** (opsional; tidak mengubah PLAN, hanya eksekusi).
- Jika gap turun melewati stop (slippage), catat `GAP_THROUGH_STOP`.

### CONFIRM note
Dividend swing sensitif gap; guard gap/chase lebih ketat dari weekly.
---

## Setup Type & Recommendation Execution (policy-specific)
Policy ini wajib mengisi `setup_type` untuk setiap kandidat:
- `setup_type ∈ {BREAKOUT, PULLBACK}`

Default execution mapping (dipakai oleh engine Recommendations):
- default → ONE_SHOT (100%)
- staging dinonaktifkan by default (window event sempit + gap risk).

Catatan: mapping ini **tidak** mengubah hard rules; hanya menentukan bentuk tranche eksekusi untuk ticker yang sudah qualified.

---

## Event day counting (locked, no ambiguity)

Dividend Swing membutuhkan entry **sebelum** `ex_date`.

### Hard gates
- Wajib: `exec_trade_date < ex_date`
  - Jika `exec_trade_date >= ex_date` → **DROP** (`DS_TOO_LATE_EXDATE`)
- Hitung:
  - `days_to_ex = trading_days_between(exec_trade_date, ex_date)  // require exec_trade_date < ex_date else DROP DS_TOO_LATE_EXDATE`
  - Definisi global: `trading_days_between(a,b)` menghitung trading day `a < d <= b`
  - Jadi jika `exec_trade_date` sehari bursa sebelum `ex_date`, `days_to_ex = 1`
  - Jika `exec_trade_date == ex_date`, `days_to_ex = 0` (dan ini sudah DROP oleh rule di atas)

### Window rule
- Lolos window jika: `DS_MIN_DAYS_TO_EX <= days_to_ex <= DS_MAX_DAYS_TO_EX`

### Audit reasons
- `DS_TOO_LATE_EXDATE`
- `DS_OUTSIDE_EVENT_WINDOW`

---

## PLAN Entry/Stop/TP (locked, EOD-only)

Dividend Swing harus menghasilkan PLAN level deterministik agar sizing/reco tidak berbeda antar versi.

### Setup type (untuk eksekusi)
- Default: `setup_type = PULLBACK`
- `setup_type = BREAKOUT` jika close(trade_date) >= resistance_20, selain itu PULLBACK

Definisi level bantu:
- `resistance_20 = highest_high(20) + tick` (highest_high sebelum trade_date, sesuai kontrak global)
- `support_5 = lowest_low(5)` (sebelum trade_date)
- Semua harga PLAN harus di-round sesuai tick ladder.

### Entry (plan.entry)
- PULLBACK: `plan.entry = round_up(close(trade_date))`
- BREAKOUT: `plan.entry = round_up(resistance_20)`

### Stop (plan.stop)
Diputuskan satu pendekatan (no “atau”):
- `plan.stop = round_down(min(low(trade_date), support_5) - tick)`

Validasi:
- `R = plan.entry - plan.stop` harus lulus kontrak global (`R > 0` dan `R >= tick`), jika tidak → DROP (`DS_R_INVALID_*`).

### TP1 / RR (GLOBAL-consistent) (LOCKED)

Kontrak global: `rr_est` selalu dihitung dari TP1.

Definisi resistance:
- `resistance_20 = highest_high(20) + tick` (sebelum trade_date)
- `resistance_50 = highest_high(50) + tick` (sebelum trade_date)

TP1 raw:
- `tp1_raw = plan.entry + (DS_MIN_RR * R)`

TP1 cap (anti over-optimistic, **tidak boleh membuat RR = 0**):
- Jika `setup_type = BREAKOUT` → `tp1_cap = resistance_50`
- Jika `setup_type = PULLBACK` → `tp1_cap = resistance_20`

Aturan:
- `plan.tp1 = round_down(min(tp1_raw, tp1_cap))`
- Wajib `plan.tp1 > plan.entry` (kalau tidak → DROP `DS_TP1_NOT_ABOVE_ENTRY`)
- `rr_est = (plan.tp1 - plan.entry) / R`
- Binding check: `rr_est >= DS_MIN_RR` (kalau tidak → DROP `DS_RR_TOO_LOW`)
### TP2 (opsional, management target) (LOCKED jika diisi)
- `plan.tp2 = round_down(plan.entry + (DS_TP2_R_MULT * R))` (opsional, tidak dipakai untuk RR gate)

Default parameter:
- `DS_TP2_R_MULT = 2.5`
- `DS_MIN_RR = 1.2`

---

## Score components & weights (LOCKED, binding)

### Default weights (LOCKED, sum = 1.00)

Score_total adalah weighted sum dari sub-scores berikut (masing-masing sudah di-clamp ke [0..1]):

- `w_event = 0.35`      untuk `s_event`
- `w_liquidity = 0.20`  untuk `s_liquidity`
- `w_trend = 0.15`      untuk `s_trend`
- `w_vol_risk = 0.15`   untuk `s_vol_risk`
- `w_gap_risk = 0.15`   untuk `s_gap_risk`

Total = 0.35 + 0.20 + 0.15 + 0.15 + 0.15 = 1.00

`score_total = (w_event*s_event) + (w_liquidity*s_liquidity) + (w_trend*s_trend) + (w_vol_risk*s_vol_risk) + (w_gap_risk*s_gap_risk)`

### Clamp rules (0..1)
- `s_event`: berdasarkan `days_to_ex` (lebih dekat tapi tidak terlalu mepet lebih baik)
  - `d = days_to_ex`
  - `d_min = DS_MIN_DAYS_TO_EX`, `d_max = DS_MAX_DAYS_TO_EX`
  - `d_ideal = floor((d_min + d_max)/2)`
  - Piecewise linear (triangular peak):
    - jika `d < d_min` atau `d > d_max` → `s_event = 0`
    - jika `d <= d_ideal` → `s_event = (d - d_min) / (d_ideal - d_min)`  (clamp 0..1)
    - jika `d > d_ideal` → `s_event = (d_max - d) / (d_max - d_ideal)`  (clamp 0..1)

- `s_liquidity`: `dv20_idr` (IDR)
  - lo=1e9, hi=2e10
- `s_trend`: `close_vs_ma20 = (close/ma20)-1`
  - lo=-0.03, hi=+0.04
- `s_vol_risk`: inverse `atr_pct`
  - atr_pct lo=0.01 hi=0.10 (lebih kecil lebih baik)
- `s_gap_risk`: proxy via `gap_pct_open` jika ada, kalau tidak gunakan `atr_pct` sebagai fallback
  - lo=0.00, hi=0.05 (lebih kecil lebih baik)

`score_total = clamp(Σ(w_i*s_i), 0, 1)`

## Invalidation & monitoring (EOD-only)
- Sebelum ex-date: jika exec_trade_date >= ex_date → invalid (sudah di-cover hard rule).
- Setelah entry: invalid jika daily close <= plan_stop (EOD-only).
- Jika ex-date lewat dan masih hold, tandai risk `POST_EVENT_HOLDING` untuk monitoring (EOD-only).

## CONFIRM hints (intraday, non-binding)
CONFIRM hanya boleh approve/reject/adjust timing; tidak boleh mengubah PLAN.

- Reject jika open hari ini membuat RR plan tidak feasible (chase).
- Reject jika antrian padat dan slippage estimasi tinggi.
- Jika ada news spike intraday, boleh hold off (CONFIRM reject), tanpa ubah PLAN.
