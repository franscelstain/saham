# Worked Example E2E — Weekly Swing (WS_EOD_PLAN_CONFIRM)

## Purpose
Contoh 1 ticker dari input EOD sampai hasil PLAN dan CONFIRM agar implementasi tidak menebak.

## Inputs
- ticker_id: ABCD
- trade_date: 2026-02-27
- close: 1020
- high: 1035
- low: 995
- volume: 12500000
- dv20_idr: 6800000000
- atr14_pct: 0.054
- roc20: 0.091
- hh20: 1030

## Paramset Used
- `../db/PARAMSET_WS_ACTIVE_EXAMPLE.json`

## Step 1 — Guards
- `liquidity.min_dv20_idr = 1000000000` → PASS (`6800000000 >= 1000000000`)
- `risk.min_atr14_pct = 0.02` → PASS (`0.054 >= 0.02`)
- `risk.max_atr14_pct = 0.12` → PASS (`0.054 <= 0.12`)
- `data_readiness.outlier_ruleset.max_abs_return_1d_pct = 0.25` → PASS
- `data_readiness.outlier_ruleset.max_high_low_range_1d_pct = 0.30` → PASS

## Step 2 — Component Scores
### score_momentum
- `setup.roc_lo = 0.02`
- `setup.roc_hi = 0.15`
- `roc20 = 0.091`
- Normalisasi:
  - `(0.091 - 0.02) / (0.15 - 0.02) = 0.546154`
- `score_momentum = 0.546154`

### score_breakout
- `setup.bo_near_below_pct = 0.02`
- `setup.bo_max_ext_pct = 0.05`
- `hh20 = 1030`
- `close = 1020`
- `distance_to_hh20_pct = (1030 - 1020) / 1030 = 0.009709`
- Karena masih di bawah HH20 dan jarak <= `bo_near_below_pct`, maka:
- `score_breakout = 0.75`

### score_volume
- `liquidity.min_dv20_idr = 1000000000`
- `liquidity.dv20_strong_idr = 5000000000`
- `dv20_idr = 6800000000`
- Karena `dv20_idr >= dv20_strong_idr`, maka:
- `score_volume = 1.0`

### score_risk
- `risk.atr_ideal_low = 0.035`
- `risk.atr_ideal_high = 0.075`
- `atr14_pct = 0.054`
- Karena ATR berada di zona ideal:
- `score_risk = 1.0`

## Step 3 — score_total
- `scoring.combine_mode = NORM_WEIGHTED_SUM_CLAMP01`
- `weights.momentum = 0.30`
- `weights.breakout = 0.30`
- `weights.volume = 0.20`
- `weights.risk = 0.20`

Perhitungan:
- `score_total = clamp01(0.30*0.546154 + 0.30*0.75 + 0.20*1.0 + 0.20*1.0)`
- `score_total = clamp01(0.163846 + 0.225 + 0.20 + 0.20)`
- `score_total = 0.788846`

## Step 4 — Grouping / Dynamic Selection
Misal hasil hari itu:
- `grouping.top_min_score_q.value = 0.80`
- `grouping.secondary_min_score_q.value = 0.65`
- `top_cutoff_today = 0.812000`
- `secondary_cutoff_today = 0.701000`

Evaluasi:
- `score_total = 0.788846`
- `score_total < top_cutoff_today` → bukan `TOP_PICKS`
- `score_total >= secondary_cutoff_today` → masuk `SECONDARY`

Hasil:
- `group_semantic = SECONDARY`

## Step 5 — PLAN Levels
- `plan_levels.entry_band_pct = 0.01`
- `entry_ref = close = 1020`
- `entry_min = 1020 * (1 - 0.01) = 1009.80`
- `entry_max = 1020 * (1 + 0.01) = 1030.20`

Stop:
- `risk.stop_mode = ATR`
- `risk.stop_atr_mult = 1.5`
- `atr14_pct = 0.054`
- `stop_price = 1020 * (1 - (1.5 * 0.054))`
- `stop_price = 1020 * (1 - 0.081)`
- `stop_price = 937.38`

TP1:
- `risk.min_rr = 1.5`
- `risk_per_share = 1020 - 937.38 = 82.62`
- `tp1_price = 1020 + (1.5 * 82.62)`
- `tp1_price = 1143.93`

## Step 6 — Confirm Overlay
Misal data runtime:
- `last_price = 1028`
- `snapshot_age_sec = 120`
- `turnover_idr = 143960000000`
- `volume_shares = 39330000`
- `drift_pct = (1028 - 1020) / 1020 = 0.007843`

Check:
- `snapshot_age_sec <= 900` → valid
- `turnover_idr > 0 dan volume_shares > 0` → pass
- `drift_pct <= 0.03` → pass

Hasil:
- `label = CONFIRMED`
- `reasons = []` karena tidak ada warning/block/info tambahan dari CONFIRM

## Final Output Summary
- `plan_trade_date = 2026-02-28`
- `ticker_id = ABCD`
- `score_total = 0.788846`
- `group_semantic = SECONDARY`
- `entry_ref = 1020`
- `entry_min = 1009.80`
- `entry_max = 1030.20`
- `stop_price = 937.38`
- `tp1_price = 1143.93`
- `label = CONFIRMED`
- `reasons = []`

---

## Artifacts Produced (GOLDEN, LOCKED)
Dokumen ini dianggap “golden runnable example”. Minimal artefak yang harus dapat dihasilkan ulang:

### A) PLAN (persisted)
- `plan_run` untuk `trade_date=T`
- `plan_items` untuk `trade_date=T` (minimal 1 ticker contoh ini)

### B) CONFIRM snapshot input (persisted, manual)
- `watchlist_confirm_snapshots` (header) untuk `(policy_code='WS', trade_date=T)`
- `watchlist_confirm_snapshot_items` (items) untuk `ticker_code=ABCD` dengan field:
  - `last_price`, `chg_pct`, `volume_shares`, `turnover_idr`, `captured_at`

### C) CONFIRM output (runtime)
- Output mengikuti schema di `WS_RUNTIME_OUTPUT_SCHEMA.md`.
- Contoh output referensi ada di `WS_RUNTIME_OUTPUT_EXAMPLES.md`.

Catatan (LOCKED):
- CONFIRM memakai **intraday aggregate snapshot**, **bukan ladder**.

---

## Replay Checklist (GOLDEN, LOCKED)
Tujuan: reviewer/operator bisa mengulang hasil dengan data yang sama.

1) Pastikan paramset yang dipakai sama:
   - `../db/PARAMSET_WS_ACTIVE_EXAMPLE.json`
2) Jalankan PLAN untuk `trade_date=2026-02-27` (PLAN rekomendasi untuk `plan_trade_date=2026-02-28`).
3) Input snapshot CONFIRM manual untuk `trade_date=2026-02-27`:
   - `captured_at` sesuai contoh (snapshot_age_sec <= 900)
   - Items: `last_price=1028`, `volume_shares=39330000`, `turnover_idr=143960000000`
4) Jalankan CONFIRM overlay.
5) Assert hasil sesuai “Final Output Summary” di atas.

Invariant (LOCKED):
- CONFIRM tidak boleh mengubah PLAN (plan_hash_before == plan_hash_after).