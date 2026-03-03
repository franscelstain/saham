# 04 — ParamSet JSON Contract — Weekly Swing (WS)

## Notasi key (LOCKED)

Dokumen memakai **dotted notation** (contoh: `risk.min_rr`, `data_readiness.min_coverage_ratio`) hanya sebagai cara referensi teks.
Struktur **JSON paramset yang sebenarnya** tetap **nested object** sesuai kontrak file ini (contoh: `{ "risk": { "min_rr": ... } }`).
**Aturan:** jika konteksnya validasi/perbandingan angka, gunakan `<path>.value`. Jika konteksnya registri/daftar key, cukup `<path>`.

## Purpose
Mengunci struktur params_json WS agar code tidak memakai parameter implicit/hardcode.

## Prerequisites
### Shared Global
`../_shared/02_PARAMSET_CONTRACT_GLOBAL.md`
### Weekly Swing
03_WS_DATA_MODEL_MARIADB.md

## Inputs
- Daftar parameter WS (lihat 05 registry)

## Process
### 1) Identity lock
- policy_code = "WS"
- policy_version = "WS_EOD_PLAN_CONFIRM"
- schema_version = "PARAMSET_JSON"
- `paramset_code` (string, opsional tapi direkomendasikan untuk audit; contoh: "WS_ACTIVE_BOOTSTRAP_V1")

### 2) Key wajib WS (ringkas)
WS wajib memiliki blok berikut:
- data_contract (required_sources, required_fields, disabled_fields)
- data_readiness (min_history_days, max_missing_bar_days_60d, reject_if_eod_incomplete, outlier_ruleset, min_coverage_ratio)
- liquidity (min_dv20_idr, dv20_strong_idr, exclude_tickers)
- risk (min/max atr14_pct, ideal band, stop_mode, stop_atr_mult, min_rr)
- setup (roc bounds, bo trigger, near/ext bounds)
- scoring (combine_mode, weights)
- grouping (grouping_mode, sort_keys, rounding_mode, thresholds, percentiles, min_count_overrides)
- plan_levels (entry_mode, entry_band_pct)
- no_trade (min_eligible_count, no_trade_hides_all)
- confirm_overlay (snapshot_max_age_sec, max_drift_from_entry_pct)
- eval (min_trades_oos, min_trades, min_days_covered, min_p25_ret_net_top, min_month_win_rate_min, min_month_avg_ret_net_min)  <-- NEW
- hash_contract (order_by, scales, null_handling)

### 3) Tambahan wajib deterministik (anti drift)
- grouping.sort_keys (exact list + order)
- grouping.rounding_mode = "FLOOR"
- no_trade.no_trade_hides_all = true
- hash_contract dp fixed

### 4) Contoh params_json
File ini tidak menyertakan JSON penuh; gunakan 05 registry + validator 06 sebagai sumber kebenaran.

LOCKED (provenance):
- Setiap parameter **wajib** berupa object audit: `{ value, origin, status, bt_target, rationale, change_triggers }`.
- Tidak ada `provenance` top-level pada WS; provenance dianggap **implicit** di setiap parameter node.

## Outputs
- Kontrak paramset WS yang harus dipatuhi.

## Failure modes
- Missing key => PLAN abort: PLAN_ABORT_PARAMSET_INVALID

## Cutoff dinamis (BT)
Paramset WS wajib memuat cutoff quantile untuk menjaga kualitas picks.

- `grouping.top_min_score_q` (0..1): quantile untuk cutoff TOP_PICKS.
- `grouping.secondary_min_score_q` (0..1): quantile untuk cutoff SECONDARY.

Catatan:
- Nilai quantile berasal dari kalibrasi backtest 2 tahun (BT) dan **bukan** dihitung ulang lewat backtest harian.
- Cutoff harian dihitung dari distribusi `score_total` pada eligible pool hari itu.

## Target dinamis (turunan per-run)
Paramset menyimpan **base target**:
- `grouping.top_picks_target`
- `grouping.secondary_target`

Saat PLAN dibuat, sistem menghitung nilai turunan per-run:
- `top_picks_target_dynamic`
- `secondary_target_dynamic`

Catatan wajib:
- Nilai dinamis **bukan** parameter baru di paramset; ia hasil derivasi yang disimpan di `watchlist_plan_runs.run_metrics_json`.
- `*_target_dynamic` boleh menjadi 0 hanya jika ada stop condition yang eksplisit pada run (NO_TRADE).

## Next
### Weekly Swing
- 05_WS_PARAMETER_REGISTRY_COMPLETE.md