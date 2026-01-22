# Policy: WEEKLY_SWING

Dokumen ini adalah **single source of truth** untuk policy **WEEKLY_SWING**.
Semua angka/threshold dan **UI reason codes** untuk policy ini harus berasal dari dokumen ini.

Dependensi lintas policy (Data Dictionary, output schema, namespace reason codes, tick rounding) ada di `watchlist.md`.

---

## 0) Intent & scope
Target: swing **mingguan** (holding 2–7 trading days), entry utama Selasa–Rabu, exit disiplin sebelum risiko weekend bila perlu.
Policy ini **tidak memaksa** harus BUY. Jika guard/viability gagal → WATCH_ONLY.

---

## 1) Data dependency
### 1.1 Wajib (required)
- Canonical EOD: `ticker_ohlc_daily` untuk `trade_date` (effective_end_date)
- Indicators: MA20/MA50/MA200, RSI14, ATR14, `atr_pct`, `dv20`, `liq_bucket`, `vol_sma20`, `vol_ratio`
- Setup signals: `signal_code`, `signal_age_days` (atau minimal derived setup dari EOD)
- Candle derived: `candle_body_pct`, `upper_wick_pct`, `lower_wick_pct`, `close_near_high (bool)`

### 1.2 Opsional (kalau ada, dipakai untuk guard eksekusi)
- `preopen_last_price` → `open_or_last_exec` (lihat Data Dictionary di watchlist.md)

### 1.3 Portfolio context (opsional; dipakai untuk manage posisi berjalan)
Untuk tiap position yang sedang berjalan:
- `position.has_position`, `position.position_avg_price`, `position.position_lots`, `position.days_held`, `position.days_held`
- `atr14`, r_multiple (jika tersedia), mfe_r (opsional), ret_since_entry_pct (derived)

---

## 2) Hard filters (angka tegas)
Jika gagal → DROP (kandidat tidak ditampilkan sebagai kandidat entry).

### 2.1 Data complete gate
- Required fields non-null. Jika ada indikator NULL → DROP.
- reason: `WS_DATA_INCOMPLETE`

### 2.2 Liquidity gate
- `liq_bucket` harus `A` atau `B`.
- reason: `WS_LIQ_TOO_LOW`

### 2.3 Volatility gate
- `atr_pct <= 0.10`
- reason: `WS_VOL_TOO_HIGH`

### 2.4 Setup freshness gate
- `signal_age_days <= 5` (untuk setup yang “event-driven”; jika tidak punya signal_age, skip gate ini)
- reason: `WS_SIGNAL_STALE`

### 2.5 Price sanity gate (tick/CA outlier)
- Jika ada deteksi outlier (mis. sudden jump > 40% tanpa volume memadai / corporate action belum adjusted) → DROP.
- reason: `WS_PRICE_OUTLIER_CA_RISK`

---

## 3) Soft filters + score weight override (angka tegas)
Soft filter tidak DROP, tapi menurunkan confidence atau memaksa entry_style.

### 3.1 RSI overheating
- Jika `rsi14 >= 75` → entry_style wajib `Pullback-wait`, score -6, size_multiplier * 0.8
- reason: `WS_RSI_OVERHEAT`

### 3.2 Distribution candle
- Jika `upper_wick_pct >= 0.55` dan `close_near_high == false` → score -8
- reason: `WS_WICK_DISTRIBUTION`

### 3.3 Volatility tinggi tapi masih lolos hard gate
- Jika `0.08 < atr_pct <= 0.10` → score -5, size_multiplier * 0.8
- reason: `WS_VOL_HIGH`

### 3.4 Gap risk (EOD)
- Jika `gap_pct >= 0.04` → score -6, entry_windows geser (hindari open)
- reason: `WS_GAP_RISK_EOD`

---

## 4) Setup allowlist (setup_type yang boleh recommended)
Hanya setup berikut yang boleh jadi **recommended** (top picks):
- `Breakout`
- `Pullback`
- `Continuation`
- `Reversal` (hanya jika trend tidak bearish)

Jika setup lain (Base/Sideways) → kandidat boleh tampil tetapi default WATCH_ONLY.
- reason: `WS_SETUP_NOT_ALLOWED`

---

## 5) Entry rules (termasuk anti-chasing/gap)
### 5.1 Day-of-week lifecycle
- Senin: **tidak** entry baru (kecuali exceptional manual). size_multiplier = 0.0
- Selasa: entry utama. size_multiplier = 1.0
- Rabu: entry selektif. size_multiplier = 0.8
- Kamis: entry sangat selektif. size_multiplier = 0.6
- Jumat: **no new entry**. size_multiplier = 0.0
- reason (no entry): `WS_DOW_NO_ENTRY`

### 5.2 Entry windows (default)
- `entry_windows`: ["09:20-10:30", "13:35-14:30"]
- `avoid_windows`: ["09:00-09:15", "15:50-close"]
- reason (timing): `WS_ENTRY_WINDOW_DEFAULT`

### 5.3 Anti-chasing guard (berbasis EOD)
Kontrak: jangan beli jauh di atas basis EOD.
- `max_chase_from_close_pct = 0.02`
- Jika `open_or_last_exec > close * (1 + 0.02)` → WATCH_ONLY hari itu.
- reason: `WS_CHASE_BLOCK_DISTANCE_TOO_FAR`

`open_or_last_exec` ditentukan:
- jika `open_or_last_exec` ada → gunakan itu
- jika tidak ada → `preopen_guard = PENDING` dan automated NEW ENTRY ditahan
- reason: `WS_PREOPEN_PRICE_MISSING`

### 5.4 Gap-up guard (hari eksekusi)
- `max_gap_up_pct = 0.03` (vs `close`)
- Jika `open_or_last_exec > close * (1 + 0.03)` → WATCH_ONLY hari itu
- reason: `WS_GAP_UP_BLOCK`

### 5.5 Setup-specific entry style
- Breakout: `Breakout-confirm` (tunggu follow-through; jangan entry di 09:00-09:15)
- Pullback: `Pullback-wait` (entry hanya jika pullback tidak merusak trend)
- Continuation: `Breakout-confirm` atau `Pullback-wait` tergantung candle
- Reversal: `Reversal-confirm` (wajib konfirmasi, size_multiplier * 0.8)

UI reason codes positif (opsional):
- `WS_SETUP_BREAKOUT`, `WS_SETUP_PULLBACK`, `WS_SETUP_CONTINUATION`, `WS_SETUP_REVERSAL`

---

## 6) Exit rules (time stop / max holding / trailing)
### 6.1 Time stop (disiplin; angka tegas)
- Jika T+2 (days_held >= 2) dan performa tidak menunjukkan follow-through:
  - kondisi: `ret_since_entry_pct < 0.010` (kurang dari +1.0%)
  - aksi: exit pada window exit berikutnya
  - reason: `WS_TIME_STOP_T2`
- Jika T+3 (days_held >= 3) dan masih lemah:
  - kondisi: `ret_since_entry_pct < 0.015`
  - reason: `WS_TIME_STOP_T3`

### 6.2 Max holding
- `max_holding_days = 7` trading days
- Jika `days_held >= 7` → wajib exit (kecuali policy override manual)
- reason: `WS_MAX_HOLDING_REACHED`

### 6.3 Friday risk control
- Jika hari Jumat dan posisi belum mencapai target minimal:
  - kondisi: `ret_since_entry_pct < 0.020`
  - aksi: exit/trim untuk mengurangi weekend risk
  - reason: `WS_FRIDAY_EXIT_BIAS`

### 6.4 Trailing stop (ATR-based; angka tegas)
- `trail_atr_mult = 2.0`
- `trail_sl = max(trail_sl, highest_close_since_entry - 2.0 * atr14)`
- Jika close menembus trailing SL → exit
- reason: `WS_TRAIL_STOP_HIT`

---

## 7) Sizing defaults (risk per trade, max positions, multiplier)
### 7.1 Defaults
- `risk_per_trade_pct = 0.0075` (0.75% dari capital_total)
- `max_positions = 2`
- `max_positions_today = 2` (Selasa/Rabu), 1 (Kamis), 0 (Jumat/Senin)

### 7.2 Minimum trade viability (modal kecil; angka tegas)
Guard ini mencegah trade “habis fee/spread”.
- `min_alloc_idr = 500_000`
- `min_lots = 1`
- `min_net_edge_pct = 0.008` (0.8% setelah estimasi fee+spread)
Kontrak:
- jika `capital_total` tidak tersedia → skip evaluasi viability + reason `WS_VIABILITY_NOT_EVAL_NO_CAPITAL`
- jika sizing engine menghasilkan `lots_recommended < 1` → WATCH_ONLY
- reason: `WS_MIN_TRADE_VIABILITY_FAIL`

---

## 8) Reason codes (khusus policy; UI)
### 8.1 Fail / guard / risk
- `WS_DATA_INCOMPLETE`
- `WS_LIQ_TOO_LOW`
- `WS_VOL_TOO_HIGH`
- `WS_VOL_HIGH`
- `WS_SIGNAL_STALE`
- `WS_PRICE_OUTLIER_CA_RISK`
- `WS_RSI_OVERHEAT`
- `WS_WICK_DISTRIBUTION`
- `WS_GAP_RISK_EOD`
- `WS_DOW_NO_ENTRY`
- `WS_PREOPEN_PRICE_MISSING`
- `WS_CHASE_BLOCK_DISTANCE_TOO_FAR`
- `WS_GAP_UP_BLOCK`
- `WS_TIME_STOP_T2`
- `WS_TIME_STOP_T3`
- `WS_MAX_HOLDING_REACHED`
- `WS_FRIDAY_EXIT_BIAS`
- `WS_TRAIL_STOP_HIT`
- `WS_VIABILITY_NOT_EVAL_NO_CAPITAL`
- `WS_MIN_TRADE_VIABILITY_FAIL`
- `WS_SETUP_NOT_ALLOWED`

### 8.2 Positive (optional; clarity UI)
- `WS_SETUP_BREAKOUT`
- `WS_SETUP_PULLBACK`
- `WS_SETUP_CONTINUATION`
- `WS_SETUP_REVERSAL`
- `WS_TREND_ALIGN_OK`
- `WS_VOLUME_OK`
- `WS_LIQ_OK`
- `WS_RR_OK`

---

## 9) Review SOP (wajib; kapan review)
- **Setiap hari setelah EOD ready (malam):** generate watchlist + catat top picks + plan.
- **Hari eksekusi (Selasa/Rabu):**
  - 09:00–09:15: no entry (avoid window)
  - 09:20–10:30: eksekusi jika guard PASS
  - 13:35–14:30: second window jika masih valid
- **Rabu sore:** evaluasi posisi T+2 untuk time-stop
- **Jumat 14:30–close:** enforce Friday bias (exit/trim) sesuai rule di atas

