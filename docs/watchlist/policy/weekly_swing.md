# Weekly Swing (Policy)

## 1) Tujuan & horizon
- Horizon: 3–7 hari bursa.
- Gaya entry: `setup_type ∈ {BREAKOUT, PULLBACK}` (ditentukan deterministik oleh rules PLAN) pada uptrend yang tradeable.
- Target cuan realistis: 2%–6% (tergantung ATR/likuiditas), dengan stop disiplin.

## 2) Hard Rules (wajib lolos)
### 2.1 Trend / structure gate (locked)
Wajib memenuhi salah satu kondisi berikut:
- Uptrend baseline: `close >= ma20` **dan** `ma20 >= ma50`
atau
- Breakout valid: `close >= resistance_20` dan `ma20 >= ma50`

Definisi:
- `resistance_20 = highest_high(20) + tick` (highest_high sebelum trade_date)

### 2.2 Liquidity / tradeability (locked)
- `dv20_idr >= WS_MIN_DV20_IDR`
- `atr_pct` dalam band policy: `WS_MIN_ATR_PCT <= atr_pct <= WS_MAX_ATR_PCT`
- `spread proxy` wajar (jika ada): `tick_pct <= WS_MAX_TICK_PCT`

### 2.3 Stop validity (locked)
PLAN wajib menghasilkan `R = entry-stop` yang valid:
- jika `R <= 0` → DROP (`WS_R_INVALID_NONPOSITIVE`)
- jika `R < tick` → DROP (`WS_R_INVALID_LT_TICK`)

### 2.4 RR gate (locked, GLOBAL-consistent)
RR gate Weekly Swing memakai **TP1** (bukan TP2).

- `rr_est = (tp1 - entry) / R` (**tanpa** fallback seperti `max(R,1)`)
- Wajib `rr_est >= WS_MIN_RR` → DROP (`WS_RR_TOO_LOW`)

Catatan:
- `plan.tp2` opsional untuk management target, bukan untuk RR gate.

## 3) Soft Rules (naikkan ranking, tidak menggugurkan)
- Volume/participation: `rvol20` lebih tinggi → skor naik
- Breakout “clean”: close dekat high dan tidak over-extended → skor naik
- Risk lebih kecil: `stop_pct` lebih kecil → skor naik
- Likuiditas lebih besar: `dv20_idr` lebih besar → skor naik

## 4) Risk Rules (Avoid/No Trade)
- Avoid jika: `atr_pct` terlalu tinggi (noise), `dv20_idr` rendah, dan/atau stop_pct melewati batas policy.
- Hindari jika candle “climax/distribution” (kalau kamu punya label) + follow-through lemah.
- Jika canonical EOD belum ready: recommendations=[] wajib (global), groups boleh monitoring dengan flag.

## PLAN Entry/Stop/TP (locked, EOD-only)

Weekly Swing harus menghasilkan PLAN deterministik berbasis EOD (trade_date = hari EOD terakhir).

Definisi level bantu (mengacu kontrak global: highest_high/lowest_low memakai data **sebelum** trade_date):
- `resistance_20 = highest_high(20) + tick`
- `ll5 = lowest_low(5)` (sebelum trade_date)
- Semua harga PLAN di-round sesuai tick ladder.

### Setup type (deterministik)
- `setup_type = BREAKOUT` jika `close(trade_date) >= resistance_20 - tick`
- selain itu `setup_type = PULLBACK`

### Entry (plan.entry)
- BREAKOUT: `plan.entry = round_up(resistance_20)`
- PULLBACK: `plan.entry = round_up(close(trade_date))`

### Stop (plan.stop) (DECIDED)
Satu pendekatan final:
- `plan.stop = round_down(min(low(trade_date), ll5) - tick)`

Validasi (kontrak global):
- `R = plan.entry - plan.stop`
- Jika `R <= 0` → DROP (`WS_R_INVALID_NONPOSITIVE`)
- Jika `R < tick` → DROP (`WS_R_INVALID_LT_TICK`)

### TP1 / RR (GLOBAL-consistent)
- `plan.tp1 = round_down(plan.entry + (WS_MIN_RR * R))`
- `rr_est = (plan.tp1 - plan.entry) / R` (GLOBAL)
- Binding check: `rr_est >= WS_MIN_RR`

### TP2 (opsional, management target)
- `plan.tp2 = round_down(plan.entry + (2.0 * R))` (tidak dipakai untuk RR gate)

## 5) Ranking factors (score_total 0..1)
Policy ini memakai komponen berikut untuk scoring (lihat bagian weights di bawah):
- pattern / setup quality
- trend alignment
- momentum
- volume/participation
- risk (stop_pct dan atr_pct)

## Threshold defaults (LOCKED)

- `WS_MIN_DV20_IDR = 5000000000`  (Rp 5B)
- `WS_MIN_RR = 1.3`
- `WS_MIN_ATR_PCT = 0.02`
- `WS_MAX_ATR_PCT = 0.20`   (ikuti global universe cap)
- `WS_MAX_TICK_PCT = 0.015`

Binding checks:
- `dv20_idr >= WS_MIN_DV20_IDR`
- `rr_est >= WS_MIN_RR`
- `WS_MIN_ATR_PCT <= atr_pct <= WS_MAX_ATR_PCT`
- `tick_pct <= WS_MAX_TICK_PCT`

## Score components & weights (LOCKED, binding)

### Default weights (sum = 1.00)
- `s_pattern`  = 0.30
- `s_trend`    = 0.25
- `s_momentum` = 0.20
- `s_volume`   = 0.15
- `s_risk`     = 0.10

### Clamp rules (0..1)
Semua sub-score dihitung 0..1 dan di-clamp.

- `s_trend`: gunakan `close_vs_ma20 = (close/ma20)-1`
  - lo=-0.02, hi=+0.05 → `clamp((x-lo)/(hi-lo),0,1)`
- `s_momentum`: gunakan `roc20` (20d return)
  - lo=-0.03, hi=+0.12
- `s_volume`: gunakan `rvol20`
  - lo=1.0, hi=3.0
- `s_risk`: kombinasi inverse `stop_pct` dan `atr_pct`
  - stop_pct lo=0.01 hi=0.08 (lebih kecil lebih baik)
  - atr_pct lo=0.01 hi=0.12 (lebih kecil lebih baik)
- `s_pattern`: dari pattern classifier kamu (0..1). Jika classifier keluaran 0..100 → bagi 100 lalu clamp.

`score_total = clamp(Σ(w_i*s_i), 0, 1)`

## Mini strategy (Top Picks & Secondary) (EOD-only) (LOCKED, dynamic)

Policy ini memakai rule global **EOD_TRANCHE_RULE_V1** (lihat `watchlist.md` → Mini strategy).
Mapping profile → tranche pct (2 tranche):
- `CONSERVATIVE`: 50/50
- `DEFAULT`: 60/40
- `AGGRESSIVE`: 70/30

Timing hint (LOCKED): tranche1 `09:20`, tranche2 `10:30`.

Catatan:
- `mini_tranches_lots` hanya boleh muncul jika ticker ada di `recommendations` (copy dari `recommendations.tranches`).

## Invalidation & monitoring (EOD-only)
- Setelah entry terjadi, posisi invalid jika daily close <= plan_stop (exit by stop, EOD-only).
- Jika daily close mencapai plan_tp1, boleh tandai partial/exit sesuai execution plan (EOD-only).

## CONFIRM hints (intraday, non-binding)
CONFIRM hanya boleh approve/reject/adjust timing; tidak boleh mengubah PLAN.

- Reject jika open hari ini gap-up terlalu jauh dari plan_entry (hindari chase).
- Reject jika spread/queue tidak wajar (slippage tinggi).
- Jika volume pembukaan sangat rendah vs normal, tunda eksekusi (tanpa ubah PLAN).
