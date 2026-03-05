# 08 — PLAN Algorithm (EOD) — Weekly Swing

## Purpose
Menetapkan algoritma PLAN WS dari EOD snapshot sampai menghasilkan plan_items dengan group_semantic + display_bucket + reason_codes.

## Prerequisites
### Weekly Swing
- 07_WS_REASON_CODES_AND_HASH.md
- ../../../db/04_EOD_INDICATORS.md (LOCKED — definisi indikator EOD: dv20_idr, atr14_pct, roc20, hh20)

## Inputs
- asof_eod_date D
- OHLCV(D), indicators(D): dv20_idr, atr14_pct, roc20, hh20 (lihat kontrak: `../../../db/04_EOD_INDICATORS.md`)
- params_json WS (ACTIVE)

## LOCKED — Units & Scales for Indicator Inputs
Agar implementasi tidak drift (mis. persen vs desimal), unit/scale berikut **wajib**:
- `dv20_idr` = nilai transaksi harian rata-rata 20 hari dalam **IDR** (contoh: `15_000_000_000`).
- `atr14_pct` = **decimal-percent / ratio** ATR14 terhadap harga (contoh: `0.0325` berarti 3.25%).
- `roc20` = **decimal-percent / ratio** return 20 hari (contoh: `0.125` berarti 12.5%).
- `hh20` = harga **price-level** (bukan persen), high tertinggi 20 hari.
- `close`/`asof_close` = harga **price-level** pada `asof_eod_date`.

Jika kontrak indikator (`../../../db/04_EOD_INDICATORS.md`) berbeda, maka kontrak indikator menang. PLAN wajib mengikuti kontrak indikator.

## LOCKED — Precision, Rounding, dan Comparator

Agar hasil tidak drift antar implementasi:

- Semua perhitungan internal boleh memakai float.
- Output `score_total` untuk persist + UI **wajib** dibulatkan ke **4 decimal**: `score_total_out = round(score_total_raw, 4)`.
- Ranking **wajib** memakai `score_total_raw` (bukan string), lalu tie-break deterministik:
  1) `score_total_raw DESC`
  2) `ticker_id ASC`

Comparator untuk guard (LOCKED):
- Liquidity: `dv20_idr >= min_dv20_idr` (kalau < → guard fail)
- Volatility: `atr14_pct <= max_atr14_pct` (kalau > → guard fail)
- Volume confirmation: `vol_ratio >= min_vol_ratio` (kalau < → guard fail)

Catatan (LOCKED):
- `atr14_pct` dan `roc20` disimpan sebagai **decimal-percent (ratio 0–1)**.
  - Contoh: 0.065 berarti 6.5%.
- Semua threshold di paramset mengikuti unit ini (0–1).

## Process

### Step 0 — Build universe
Universe = semua ticker aktif (master tickers), dikurangi `liquidity.exclude_tickers`.

### Step 1 — Data readiness
Untuk setiap ticker:
- required fields tersedia? jika tidak => AVOID + reason WS_DATA_MISSING
- jika jumlah bar historis < data_readiness.min_history_days => AVOID + reason WS_HIST_SHORT
- jika missing bars pada 60 trading days terakhir > data_readiness.max_missing_bar_days_60d => AVOID + reason WS_MISSING_BARS_60D
- jika data_readiness.outlier_ruleset.value.enabled=true dan abs(ret_1d) > data_readiness.outlier_ruleset.value.max_abs_return_1d_pct => AVOID + reason WS_OUTLIER_RET1D
- jika data_readiness.outlier_ruleset.value.enabled=true dan (high/low - 1) > data_readiness.outlier_ruleset.value.max_high_low_range_1d_pct => AVOID + reason WS_OUTLIER_RANGE1D
- outlier check (jika enabled) => AVOID + reason WS_DATA_OUTLIER

Catatan: coverage check run-level dilakukan di execution (lihat [`02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`](02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md)).

### Step 2 — Hard guards (pass_guard)
Jika salah satu gagal => AVOID:
- dv20_idr < liquidity.min_dv20_idr => WS_LIQ_FAIL
- atr14_pct < risk.min_atr14_pct => WS_ATR_LOW
- atr14_pct > risk.max_atr14_pct => WS_ATR_HIGH
- vol_ratio < volume.min_vol_ratio => WS_VOLR_FAIL

### Step 3 — Compute component scores (0..1)
WS memakai 4 komponen. Semua komponen **wajib** deterministik dengan rumus berikut.

Definisi helper (LOCKED):
- `clamp01(x) = min(1, max(0, x))`

1) **score_momentum** (ROC20):
   - `roc_lo = setup.roc_lo`, `roc_hi = setup.roc_hi` (wajib `roc_hi > roc_lo`, divalidasi)
   - `score_momentum = clamp01((roc20 - roc_lo) / (roc_hi - roc_lo))`
   - Jika `roc20 < setup.mom_roc20_soft_min` ⇒ set `score_momentum = 0` dan tambah reason `WS_MOM_SOFT_MIN`.

2) **score_breakout** (HH20):
   - `level = hh20`
   - `near = setup.bo_near_below_pct` (0..1), `ext = setup.bo_max_ext_pct` (0..1)
   - Jika `setup.bo_trigger_mode = OFF` ⇒ `score_breakout = 0`.
   - Jika `close >= level` (breakout):
      - `p = (close / level) - 1`
      - `score_breakout = clamp01(1 - (p / ext))`
   - Jika `close < level` (near-breakout):
      - `p = (level / close) - 1`
      - `score_breakout = clamp01(1 - (p / near))`

3) **score_volume** (DV20):
   - `dv_min = liquidity.min_dv20_idr`, `dv_strong = liquidity.dv20_strong_idr` (wajib `dv_strong > dv_min`, divalidasi)
   - Jika `dv20_idr <= dv_min` ⇒ `score_volume = 0`
   - Jika `dv20_idr >= dv_strong` ⇒ `score_volume = 1`
   - Selain itu ⇒ `score_volume = (dv20_idr - dv_min) / (dv_strong - dv_min)`

4) **score_risk** (ATR14% band):
   - `atr_min = risk.min_atr14_pct`, `atr_max = risk.max_atr14_pct`
   - `atr_i_low = risk.atr_ideal_low`, `atr_i_high = risk.atr_ideal_high` (wajib `atr_min < atr_i_low <= atr_i_high < atr_max`, divalidasi)
   - Jika `atr14_pct <= atr_min` atau `atr14_pct >= atr_max` ⇒ `score_risk = 0`
   - Jika `atr_i_low <= atr14_pct <= atr_i_high` ⇒ `score_risk = 1`
   - Jika `atr_min < atr14_pct < atr_i_low` ⇒ `score_risk = (atr14_pct - atr_min) / (atr_i_low - atr_min)`
   - Jika `atr_i_high < atr14_pct < atr_max` ⇒ `score_risk = (atr_max - atr14_pct) / (atr_max - atr_i_high)`

### Step 4 — Combine total score
score_total = clamp01( Σ(w_i * score_i) / Σw_i )
- weights di params_json.scoring.weights.value.*
- combine_mode locked: NORM_WEIGHTED_SUM_CLAMP01

### Step 5 — Forced Watch-Only rules (tidak mengubah guards)
Jika pass_guard tapi:
- breakout extended (close terlalu jauh di atas hh20) => group_semantic=WATCH_ONLY (forced) reason WS_FW_EXT
- rr < min_rr atau invalid => group_semantic=WATCH_ONLY (forced) reason WS_FW_RR_LOW / WS_FW_RR_INV

### Step 6 — Group semantic default
Jika pass_guard dan tidak forced:
- kandidat masuk ranking pool:
  - group_semantic sementara: TOP_PICKS/SECONDARY/WATCH_ONLY ditentukan oleh selection (lihat [`09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`](09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md))

**LOCKED — Ranking sort key (PLAN output & selection):**
Ranking deterministik **wajib** mengikuti `grouping.sort_keys` (lihat [`09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`](09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md)), urutan exact:
1) `score_total DESC`
2) `score_breakout DESC`
3) `score_momentum DESC`
4) `dv20_idr DESC`
5) `atr14_pct ASC`
6) `ticker_id ASC`

Jika AVOID => group_semantic=AVOID

### Step 7 — Plan levels (entry/stop/tp)
- entry_ref = entry_band_mid (lihat bagian **Entry reference (LOCKED)**)
- entry band: [entry_band_low, entry_band_high] (diturunkan dari `asof_close` dan `plan_levels.entry_band_pct`)
- stop_price = entry_ref - stop_atr_mult * ATR (ATR derived from `atr14_pct * asof_close`)
- tp1_price ditentukan deterministik (lihat bagian **TP1 formula (LOCKED)**)

### Entry reference (LOCKED)

`entry_ref` adalah sumber tunggal untuk perhitungan RR, stop, dan TP1.

Aturan:
- `asof_close` = close price pada `asof_eod_date` (EOD).
- `entry_band_pct` = `plan_levels.entry_band_pct` (ratio 0..1).
- `entry_band_low  = asof_close * (1 - entry_band_pct)`
- `entry_band_high = asof_close * (1 + entry_band_pct)`
- `entry_band_mid  = (entry_band_low + entry_band_high) / 2`
- **LOCKED:** `entry_ref = entry_band_mid`

Catatan:
- CONFIRM tidak boleh mengganti `entry_ref`.

### TP1 formula (LOCKED)

`tp1_price` wajib deterministik dan berasal dari parameter, bukan “resistance manual”.

Definisi:
- `entry_ref` = harga referensi entry dari PLAN (lihat bagian Entry reference/Entry band).
- `stop_price` = hasil perhitungan stop (berdasarkan `risk.stop_mode` dan/atau `risk.stop_atr_mult`).
- `rr_target` = `risk.min_rr`.

Rumus (R-multiple):
- `risk_per_share = entry_ref - stop_price`
- Jika `risk_per_share <= 0` ⇒ forced WATCH_ONLY dengan reason `WS_FW_RR_INV`.
- `tp1_price = entry_ref + (rr_target * risk_per_share)`

Catatan rounding & tick-size (LOCKED):
- **Kontrak PLAN menyimpan angka hasil perhitungan tanpa pembulatan tick-size broker.**
- Untuk mencegah drift floating-point, semua angka turunan PLAN **wajib** dinormalisasi sebelum disimpan/output/hashing:
  - `PRICE_SCALE = 6` untuk: `entry_band_low`, `entry_band_high`, `entry_band_mid`, `entry_ref`, `stop_price`, `tp1_price`.
  - `RATIO_SCALE = 6` untuk: `risk_per_share`, `rr`.
  - Normalisasi menggunakan `ROUND_HALF_UP`.
- `grouping.rounding_mode` **tidak** dipakai untuk rounding harga; itu hanya untuk rounding target dinamis (lihat [`09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`](09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md)).


## Outputs
- per ticker: scores + plan levels + preliminary classification + reasons.

## Failure modes
- division by zero (roc_hi==roc_lo) => validator harus mencegah.

## Reference
- [`_refs/WS_WORKED_EXAMPLE_E2E.md`](_refs/WS_WORKED_EXAMPLE_E2E.md)

## Next
### Weekly Swing
- 09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md