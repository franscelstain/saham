# 06 — ParamSet Validator Spec — WS_EOD_PLAN_CONFIRM

## Purpose
Validasi wajib params_json WS sebelum eksekusi. PLAN/CONFIRM/backtest abort jika gagal.

## Prerequisites
### Weekly Swing
05_WS_PARAMETER_REGISTRY_COMPLETE.md

## Inputs
- params_json WS

## Process

### 1) Failure output (LOCKED)
- Setiap VALIDATION FAIL **wajib** mengeluarkan `cf_code` (string) yang canonical, referensi: `watchlist/policies/_shared/07_CONTRACT_FAILURE_CODES_LOCKED.md`.
- Pesan error bebas boleh ada sebagai context, tapi **tidak boleh menggantikan** `cf_code`.

LOCKED — CF mapping (validator layer):
- Missing required key (registry completeness) => `CF_PARAMSET_MISSING_KEY`
- Unknown key (drift) => `CF_PARAMSET_UNKNOWN_KEY`
- Type drift (value type != registry) => `CF_PARAMSET_TYPE_DRIFT`
- Audit-node schema invalid (missing `{ value, origin, status, bt_target, rationale, change_triggers }`) => `CF_PARAMSET_AUDIT_SCHEMA_INVALID`
- Enum invalid (origin/status/mode keys) => `CF_PARAMSET_ENUM_INVALID`
- Hash contract lock violated => `CF_HASH_CONTRACT_VIOLATION`
- Eval gate invalid => `CF_EVAL_GATE_INVALID`

### 2) JSON & identity
- params_json valid JSON object
- policy_code='WS'
- policy_version='WS_EOD_PLAN_CONFIRM'
- schema_version='PARAMSET_JSON'
- jika `paramset_code` ada: string non-empty, pattern `^[A-Z0-9_]+$` (audit-friendly, stabil)

### 3) Enum rules
- origin enum: DET, MAN, BT, DET+MAN, MAN+BT, DET+BT
- status enum: ACTIVE, TEMP, DEPRECATED
- TEMP => bt_target=true
- origin=BT => status != TEMP
- LOCKED: setiap leaf parameter **wajib** punya field audit `{ value, origin, status, bt_target, rationale, change_triggers }`
- `change_triggers` wajib array (boleh kosong)

### 4) Type rules (ringkas)
- boolean: data_readiness.reject_if_eod_incomplete, enabled flags, no_trade_hides_all
- number: thresholds, weights, bounds
- integer: dv20_idr, counts, dp scales
- string: *mode keys
- array: liquidity.exclude_tickers, grouping.sort_keys
- object/map: grouping.min_count_overrides, scoring.weights.value, data_contract.required_fields.value (list), data_contract.required_sources.value (list)

### 5) Registry completeness check (LOCKED)
Validator **wajib** menjadikan registry sebagai sumber kebenaran:
- Load `05_WS_PARAMETER_REGISTRY_COMPLETE.md`, ambil semua key yang terdefinisi:
  - Expand shorthand `{a,b,c}` menjadi key individual.
  - Untuk wildcard `.*`, treat sebagai “pattern” (bukan key tunggal).
- Untuk setiap key non-wildcard:
  - Key **wajib ada** di `params_json` sebagai node audit `{ value, origin, status, bt_target, rationale, change_triggers }`.
  - Tipe `value` **wajib** sesuai definisi tipe di registry (bool / integer / number / string / array / object-map). Jika tidak match => FAIL (type drift).
- Untuk wildcard `X.*`:
  - Node base `X` **wajib ada** dan `X.value` harus **array(list) atau object(map)** yang **non-empty** (memuat minimal 1 item/entry).
- Jika ada key di `params_json` yang **tidak ada** di registry (kecuali node base yang dibutuhkan oleh wildcard), maka FAIL (drift).

### 6) Numeric sanity
Liquidity:
- liquidity.min_dv20_idr.value > 0
- liquidity.dv20_strong_idr.value > liquidity.min_dv20_idr.value

ATR:
- risk.min_atr14_pct.value > 0
- risk.max_atr14_pct.value > risk.min_atr14_pct.value
- risk.atr_ideal_low.value >= risk.min_atr14_pct.value
- risk.atr_ideal_high.value <= risk.max_atr14_pct.value
- risk.atr_ideal_low.value <= risk.atr_ideal_high.value

Momentum:
- setup.roc_lo.value < setup.roc_hi.value

Breakout:
- setup.bo_near_below_pct.value > 0
- setup.bo_max_ext_pct.value > 0
- setup.mom_roc20_soft_min.value is required
- setup.mom_roc20_soft_min.value is number
- -1 <= setup.mom_roc20_soft_min.value <= 1

Weights:
- scoring.weights.value.momentum >= 0
- scoring.weights.value.breakout >= 0
- scoring.weights.value.volume >= 0
- scoring.weights.value.risk >= 0
- sum(scoring.weights.value.{momentum,breakout,volume,risk}) > 0

Caps / targets ordering:
- grouping.top_picks_target.value >= 0
- grouping.secondary_target.value >= 0
- Dynamic targets are derived per-run and may be 0 only under explicit stop condition (NO_TRADE).

Plan:
- plan_levels.entry_band_pct.value > 0

Risk:
- risk.stop_atr_mult.value > 0
- risk.min_rr.value > 0

Confirm:
- confirm_overlay.snapshot_max_age_sec.value == 900
- confirm_overlay.max_drift_from_entry_pct.value > 0

No Trade:
- no_trade.min_eligible_count.value >= 1

Eval (backtest & OOS gates):
- eval.min_trades_oos.value >= 0
- eval.min_trades.value >= 0
- eval.min_days_covered.value >= 0
- eval.min_p25_ret_net_top.value is number
- 0 <= eval.min_month_win_rate_min.value <= 1
- eval.min_month_avg_ret_net_min.value is number

Outlier:
- if enabled: max_abs_return_1d_pct.value > 0, max_high_low_range_1d_pct.value > 0
- data_contract.required_fields.value (required, list non-empty)
- data_contract.required_sources.value (required, list non-empty)
- data_contract.disabled_fields.value (optional, list)

### 7) Locked invariants (must match exact)
- scoring.combine_mode.value == 'NORM_WEIGHTED_SUM_CLAMP01'
- grouping.grouping_mode.value == 'QUALIFIED_POOLS_QUANTILE_CUTOFF'
- grouping.rounding_mode.value == 'FLOOR'
- setup.bo_trigger_mode.value == 'CLOSE_GT_HH20'
- risk.stop_mode.value == 'ATR'
- plan_levels.entry_mode.value == 'BREAKOUT'
- no_trade.no_trade_hides_all.value == true
- confirm_overlay.snapshot_max_age_sec.value == 900

sort_keys exact order:
1) score_total_desc
2) score_breakout_desc
3) score_momentum_desc
4) dv20_idr_desc
5) atr14_pct_asc
6) ticker_id_asc

hash_contract lock:
- hash_contract.order_by.value == 'ticker_id_asc'
- hash_contract.null_handling.value == 'EXCLUDE_FROM_HASH_PAYLOAD'
- hash_contract.scales.value.close_price_dp == 4
- hash_contract.scales.value.hh20_dp == 4
- hash_contract.scales.value.roc20_dp == 6
- hash_contract.scales.value.atr14_pct_dp == 4
- hash_contract.scales.value.dv20_idr_dp == 0

Catatan LOCKED: nilai dp ini **harus identik** dengan definisi di `07_WS_REASON_CODES_AND_HASH.md` dan test vector hash di sana.

## Outputs
- PASS/FAIL + daftar error.

### Data readiness: coverage gate (LOCKED)

- Required: `data_readiness.min_coverage_ratio`
- Type: number
- Range: `0.0 <= value <= 1.0`

- Required: `data_readiness.min_history_days`
- Type: integer
- Range: value >= 1

- Required: `data_readiness.max_missing_bar_days_60d`
- Type: integer
- Range: value >= 0

- Required: `data_readiness.outlier_ruleset.value.enabled`
- Type: boolean

- Required: `data_readiness.outlier_ruleset.value.max_abs_return_1d_pct`
- Type: number
- Range: 0 < value <= 1

- Required: `data_readiness.outlier_ruleset.value.max_high_low_range_1d_pct`
- Type: number
- Range: 0 < value <= 2

### Cutoff quantiles (LOCKED)

- Required: `grouping.top_min_score_q`, `grouping.secondary_min_score_q`
- Type: number
- Range: `0.0 <= value <= 1.0`

Relasi (LOCKED):
- `grouping.top_min_score_q.value >= grouping.secondary_min_score_q.value`

### NO_TRADE gate (LOCKED)
- Required: no_trade.min_eligible_count
- Type: integer
- Range: value >= 1

Rasional: TOP_PICKS harus punya cutoff minimal yang **tidak lebih longgar** daripada SECONDARY.

## Plan immutability check (LOCKED)

Tujuan: memastikan implementasi tidak pernah “menyentuh” PLAN saat menjalankan CONFIRM.

Contract check:
- Input: satu `plan_record` (hasil PLAN yang tersimpan) + runtime input CONFIRM.
- Hitung `plan_hash_before` dari `plan_record` (menggunakan canonical hash contract).
- Jalankan CONFIRM overlay.
- Hitung `plan_hash_after` dari `plan_record` yang sama.
- Wajib: `plan_hash_before == plan_hash_after`.

Catatan:
- Check ini bukan validasi paramset; ini **contract test/invariant** untuk pipeline eksekusi.

## Reference
- `_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`

## Next
### Weekly Swing
- 07_WS_REASON_CODES_AND_HASH.md