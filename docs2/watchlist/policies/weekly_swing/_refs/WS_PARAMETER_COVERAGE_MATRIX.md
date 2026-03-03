# WS Parameter Coverage Matrix (LOCKED)

Tujuan: bukti 1 halaman bahwa parameter yang dipakai runtime punya coverage di:
- kontrak: `../04_WS_PARAMSET_JSON_CONTRACT.md`
- registry: `../05_WS_PARAMETER_REGISTRY_COMPLETE.md`
- validator: `../06_WS_PARAMSET_VALIDATOR_SPEC.md`
- eksekusi & algoritma:
  - `../02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`
  - `../08_WS_PLAN_ALGORITHM.md`
  - `../09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
  - `../10_WS_CONFIRM_OVERLAY.md`

Aturan (LOCKED): untuk parameter yang dipakai runtime, kolom 03/09/10/used_in wajib terisi.

last_updated=2026-02-22

| param_key | in_03 | in_09 | in_10 | used_in | provenance | default |
|---|---:|---:|---:|---|---|---|
| `confirm_overlay.max_drift_from_entry_pct` | Y | Y | Y | 06,10 | see 09 | see 03 |
| `confirm_overlay.snapshot_max_age_sec` | Y | Y | Y | 06,07,10 | see 09 | see 03 |
| `data_contract.disabled_fields` | Y | Y | Y | 06 | see 09 | see 03 |
| `data_contract.required_fields` | Y | Y | Y | 06 | see 09 | see 03 |
| `data_contract.required_sources` | Y | Y | Y | 06 | see 09 | see 03 |
| `data_readiness.max_missing_bar_days_60d` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `data_readiness.min_coverage_ratio` | Y | Y | Y | 02,06,09 | see 09 | see 03 |
| `data_readiness.min_history_days` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `data_readiness.outlier_ruleset.value.enabled` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `data_readiness.outlier_ruleset.value.max_abs_return_1d_pct` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `data_readiness.outlier_ruleset.value.max_high_low_range_1d_pct` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `data_readiness.reject_if_eod_incomplete` | Y | Y | Y | 02,06,09 | see 09 | see 03 |
| `grouping.grouping_mode` | Y | Y | Y | 06,13 | see 09 | see 03 |
| `grouping.rounding_mode` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `grouping.sort_keys` | Y | Y | Y | 06,13 | see 09 | see 03 |
| `hash_contract.null_handling` | Y | Y | Y | 06 | see 09 | see 03 |
| `hash_contract.order_by` | Y | Y | Y | 06,07 | see 09 | see 03 |
| `hash_contract.scales` | Y | Y | Y | 06 | see 09 | see 03 |
| `liquidity.dv20_strong_idr` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `liquidity.exclude_tickers` | Y | Y | Y | 06,08,12 | see 09 | see 03 |
| `liquidity.min_dv20_idr` | Y | Y | Y | 06,07,08 | see 09 | see 03 |
| `no_trade.min_eligible_count` | Y | Y | Y | 02,06,09 | see 09 | see 03 |
| `no_trade.no_trade_hides_all.value` | Y | Y | Y | 06 | see 09 | see 03 |
| `plan_levels.entry_band_pct` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `plan_levels.entry_mode.value` | Y | Y | Y | 06 | see 09 | see 03 |
| `risk.atr_ideal_high` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `risk.atr_ideal_low` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `risk.max_atr14_pct` | Y | Y | Y | 06,07,08 | see 09 | see 03 |
| `risk.min_atr14_pct` | Y | Y | Y | 06,07,08 | see 09 | see 03 |
| `risk.min_rr` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `risk.stop_atr_mult` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `risk.stop_mode.value` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `scoring.combine_mode.value` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `scoring.weights.value` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `grouping.secondary_min_score_q` | Y | Y | Y | 06,09 | see 09 | see 03 |
| `grouping.secondary_target` | Y | Y | Y | 06,09 | see 09 | see 03 |
| `setup.bo_max_ext_pct` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `setup.bo_near_below_pct` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `setup.bo_trigger_mode.value` | Y | Y | Y | 06 | see 09 | see 03 |
| `setup.mom_roc20_soft_min` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `setup.roc_hi` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `setup.roc_lo` | Y | Y | Y | 06,08 | see 09 | see 03 |
| `grouping.top_min_score_q` | Y | Y | Y | 06,09 | see 09 | see 03 |
| `grouping.top_picks_target` | Y | Y | Y | 06,09 | see 09 | see 03 |

