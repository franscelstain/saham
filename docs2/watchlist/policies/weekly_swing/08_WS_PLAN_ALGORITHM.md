# 08 — PLAN Algorithm (EOD) — Weekly Swing

## Purpose
Menetapkan algoritma PLAN WS dari EOD snapshot sampai menghasilkan plan_items dengan group_semantic + display_bucket + reason_codes.

## Prerequisites
### Weekly Swing
07_WS_REASON_CODES_AND_HASH.md

## Inputs
- asof_eod_date D
- OHLCV(D), indicators(D): dv20_idr, atr14_pct, roc20, hh20
- params_json WS (ACTIVE)

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
Catatan: coverage check run-level dilakukan di execution (lihat `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`).

### Step 2 — Hard guards (pass_guard)
Jika salah satu gagal => AVOID:
- dv20_idr < liquidity.min_dv20_idr => WS_LIQ_FAIL
- atr14_pct < risk.min_atr14_pct => WS_ATR_LOW
- atr14_pct > risk.max_atr14_pct => WS_ATR_HIGH

### Step 3 — Compute component scores (0..1)
WS memakai 4 komponen:
1) score_momentum: clamp01((roc20 - roc_lo)/(roc_hi - roc_lo))
   - jika roc20 < setup.mom_roc20_soft_min => set score_momentum = 0 dan tambah reason WS_MOM_SOFT_MIN
2) score_breakout:
   - jika close >= hh20: 1.0 tapi turun jika extended (bo_max_ext_pct)
   - jika close < hh20: naik jika near (bo_near_below_pct)
3) score_volume:
   - 0 jika dv20 < min_dv20
   - 1 jika dv20 >= dv20_strong
   - linear di antaranya
4) score_risk:
   - peak di [atr_ideal_low..atr_ideal_high]
   - turun menuju 0 saat mendekati min/max guard

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
  - group_semantic sementara: TOP_PICKS/SECONDARY/WATCH_ONLY ditentukan oleh selection (dok 09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md)
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

Catatan rounding (LOCKED):
- `tp1_price` dibulatkan sesuai aturan rounding output PLAN yang sudah dikunci (lihat `grouping.rounding_mode` / output rounding spec).

- rr dihitung dan disimpan

Catatan: Rounding & tick-size mengikuti input harga yang tersedia; kontrak watchlist menyimpan angka hasil perhitungan tanpa pembulatan tick-size (pembulatan dilakukan saat eksekusi manual di broker).

## Outputs
- per ticker: scores + plan levels + preliminary classification + reasons.

## Failure modes
- division by zero (roc_hi==roc_lo) => validator harus mencegah.

## Reference
- `_refs/WS_WORKED_EXAMPLE_E2E.md`

## Next
### Weekly Swing
- 09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md
