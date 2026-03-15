# 04 — Weekly Swing Paramset JSON Contract

## Purpose

Dokumen ini adalah owner normatif untuk shape JSON paramset Weekly Swing.

## Scope

Dokumen ini mengunci:

- required top-level keys,
- nested section yang wajib ada,
- struktur audit object per parameter,
- unknown-key rule,
- dan hash-contract section yang wajib ada pada paramset aktif Weekly Swing.

## Ownership Rule

Dokumen ini adalah owner shape. Registry lengkap ada di `05_WS_PARAMETER_REGISTRY_COMPLETE.md`. Valid / invalid behavior ada di `06_WS_PARAMSET_VALIDATOR_SPEC.md`. Procedure promote / activate ada di `20_WS_CANONICAL_PARAMSET_PROCEDURES.md`.

## A. Required Top-Level Keys

Top-level keys yang wajib ada pada paramset Weekly Swing saat ini adalah:

- `policy_code`
- `policy_version`
- `schema_version`
- `paramset_code`
- `data_contract`
- `data_readiness`
- `liquidity`
- `risk`
- `setup`
- `scoring`
- `grouping`
- `plan_levels`
- `no_trade`
- `confirm_overlay`
- `eval`
- `hash_contract`
- `volume`

Top-level key di luar daftar ini dianggap unknown root key dan tidak termasuk kontrak aktif.

## B. Required Meta Keys

Field meta root berikut wajib ada:

- `policy_code` — canonical internal policy code saat ini: `WS`
- `policy_version` — canonical policy version string untuk Weekly Swing paramset aktif
- `schema_version` — canonical schema version string; fixture aktif saat ini memakai `PARAMSET_JSON`
- `paramset_code` — identifier paramset

Untuk parity lintas runtime artifacts, `policy_code = WS` adalah canonical internal identifier. Runtime outputs dapat memakai `meta.policy = WEEKLY_SWING` sebagai runtime / display label untuk strategy yang sama.

## C. Required Section Keys

### `data_contract`
Wajib memiliki:
- `required_sources`
- `required_fields`
- `disabled_fields`

### `data_readiness`
Wajib memiliki:
- `min_coverage_ratio`
- `min_history_days`
- `max_missing_bar_days_60d`
- `reject_if_eod_incomplete`
- `outlier_ruleset`

### `liquidity`
Wajib memiliki:
- `min_dv20_idr`
- `dv20_strong_idr`
- `exclude_tickers`

### `volume`
Wajib memiliki:
- `min_vol_ratio`

### `risk`
Wajib memiliki:
- `min_atr14_pct`
- `max_atr14_pct`
- `atr_ideal_low`
- `atr_ideal_high`
- `stop_mode`
- `stop_atr_mult`
- `min_rr`

### `setup`
Wajib memiliki:
- `roc_lo`
- `roc_hi`
- `mom_roc20_soft_min`
- `bo_trigger_mode`
- `bo_near_below_pct`
- `bo_max_ext_pct`

### `scoring`
Wajib memiliki:
- `combine_mode`
- `weights`

`scoring.weights` wajib memiliki child key berikut pada `value`:
- `momentum`
- `breakout`
- `volume`
- `risk`

### `grouping`
Wajib memiliki:
- `secondary_target`
- `top_picks_target`
- `secondary_min_score_q`
- `top_min_score_q`
- `grouping_mode`
- `sort_keys`
- `rounding_mode`
- `min_count_overrides`
- `display_caps`

### `plan_levels`
Wajib memiliki:
- `entry_mode`
- `entry_band_pct`

### `no_trade`
Wajib memiliki:
- `min_eligible_count`
- `no_trade_hides_all`

### `confirm_overlay`
Wajib memiliki:
- `snapshot_max_age_sec`
- `max_drift_from_entry_pct`

### `eval`
Wajib memiliki:
- `min_trades_oos`
- `min_trades`
- `min_days_covered`
- `min_p25_ret_net_top`
- `min_month_win_rate_min`
- `min_month_avg_ret_net_min`

### `hash_contract`
Wajib memiliki:
- `version`
- `order_by`
- `scales`
- `null_handling`

`hash_contract.scales.value` wajib memiliki:
- `close_price_dp`
- `hh20_dp`
- `roc20_dp`
- `atr14_pct_dp`
- `dv20_idr_dp`

## D. Audit Object Contract

Setiap parameter leaf pada section Weekly Swing menggunakan audit object dengan field wajib berikut:

- `value`
- `origin`
- `status`
- `bt_target`
- `rationale`
- `change_triggers`

Ketiadaan salah satu field audit wajib tersebut adalah contract violation.

## E. Type Contract

Aturan tipe minimal yang aktif dari fixtures saat ini:

- `value` pada threshold numerik harus bertipe numerik, bukan string numerik.
- `change_triggers` harus array.
- `required_sources`, `required_fields.value`, `exclude_tickers`, dan `grouping.sort_keys.value` harus array.
- `outlier_ruleset.value` dan `hash_contract.scales.value` harus object.

## F. Enum / Fixed-Value Contract

Nilai yang saat ini terkunci secara kontraktual dari fixtures aktif dan artifacts terkait mencakup setidaknya:

- `policy_code = WS`
- `schema_version = PARAMSET_JSON`
- `hash_contract.order_by.value = ticker_id_asc`
- `hash_contract.null_handling.value = EXCLUDE_FROM_HASH_PAYLOAD`
- `grouping.sort_keys.value = [score_total_desc, score_breakout_desc, score_momentum_desc, dv20_idr_desc, atr14_pct_asc, ticker_id_asc]`

Nilai enum / fixed lain yang dipakai runtime harus konsisten dengan registry dan validator spec.

## G. Unknown-Key Rule

Unknown root key dilarang. Fixture `paramset_unknown_key.json` merepresentasikan pelanggaran kontrak ini.

Unknown key pada nested structures juga dilarang bila tidak terdaftar di contract owner / registry canonical Weekly Swing.

## H. Supporting Artifact Mapping

Shape kontrak ini dicerminkan oleh:

- `db/PARAMSET_WS_ACTIVE_EXAMPLE.json`
- `fixtures/paramset_valid.json`
- seluruh fixture negatif `paramset_*`

Artifacts tersebut tidak boleh lebih authoritative daripada dokumen ini.

## Final Contract Rule

Jika sebuah key, nested structure, atau audit field tidak dapat ditelusuri ke kontrak yang ditetapkan di dokumen ini, key atau structure tersebut tidak boleh dianggap bagian dari paramset contract Weekly Swing.
