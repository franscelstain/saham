# WS Golden Fixtures (LOCKED)

## Purpose
Mencatat inventori fixture resmi Weekly Swing yang dipakai untuk membuktikan kontrak validator, PLAN, CONFIRM, hash, dan governance backtest.

## Scope
Dokumen ini hanya menginventaris fixture source-of-truth dan tujuan pengujiannya.
Dokumen ini tidak mendefinisikan kontrak baru di luar dokumen normatif.

## Inputs
- file fixture resmi pada folder [`../fixtures/`](../fixtures/README.md)
- kontrak normatif Weekly Swing

## Outputs
- daftar fixture resmi,
- relative path resmi,
- mapping fixture ke tujuan test.

## Official Fixture Root (LOCKED)
Source of truth fixture Weekly Swing berada di:
- [`../fixtures/`](../fixtures/README.md)

Jika test suite memirror file ke lokasi lain, mirror tersebut harus byte-identical.
Namun dokumen ini selalu menyebut fixture dengan **relative path eksplisit** dari folder `_refs/` ini.

## Official Fixture Inventory (LOCKED)

### A) Paramset validator fixtures
- `../fixtures/paramset_valid.json`
- `../fixtures/paramset_unknown_key.json`
- `../fixtures/paramset_missing_required_key.json`
- `../fixtures/paramset_type_drift.json`
- `../fixtures/paramset_missing_audit_field.json`
- `../fixtures/paramset_bad_enum.json`
- `../fixtures/paramset_bad_hash_contract.json`
- `../fixtures/paramset_bad_eval.json`

### B) PLAN / grouping fixtures
- `../fixtures/PLAN_FIXTURE_A_TIES_V1.json`
- `../fixtures/plan_items_artificial_ties.json`
- `../fixtures/plan_items_guard_fail.json`
- `../fixtures/plan_items_forced_watch_only.json`
- `../fixtures/plan_items_no_trade_hide_all.json`
- `../fixtures/plan_items_no_trade_min_eligible.json`
- `../fixtures/scored_items_quantile_cutoff.json`

### C) CONFIRM / immutability fixtures
- `../fixtures/confirm_immutability_pair.json`
- `../fixtures/confirm_snapshots_two.json`
- `../fixtures/confirm_payload_with_orderbook_fields.json`
- `../fixtures/confirm_payload_with_unknown_top_level_field.json`

### D) Backtest governance fixtures
- `../fixtures/bt_coverage_guard_minimal.json`
- `../fixtures/bt_eval_metrics_minimal.json`
- `../fixtures/bt_oos_proof_minimal.json`
- `../fixtures/artifact_reference_guard_minimal.json`
- `../fixtures/universe_equivalence_sample.json`
- `../fixtures/plan_universe_snapshot_sample.json`

### E) Hash vector fixture
- `../fixtures/hash_contract_vectors.json`

## Schema Expectations by Fixture Family (LOCKED)

### PLAN / grouping fixtures
Minimal harus memuat:
- `fixture_id`
- `policy_code`
- `asof_eod_date`
- input rows yang cukup untuk menghitung guard/scoring/grouping
- `expected` canonical

### CONFIRM fixtures
Minimal harus memuat:
- `trade_date`
- `captured_at` dan/atau `checked_at`
- item runtime dengan field contract seperti `ticker`, `last`/`last_price`, `volume_shares`, `turnover_idr`
- jika ada field non-contract seperti bid/ask/spread/orderbook, field tersebut wajib di-ignore

### Hash vector fixture
Minimal harus memuat:
- `rules_locked`
- `vectors[]`
- `canonical_string`
- `expected_sha256`

## Fixture → Test Purpose Mapping (LOCKED)
- `../fixtures/PLAN_FIXTURE_A_TIES_V1.json` -> PLAN determinism + tie handling
- `../fixtures/plan_items_artificial_ties.json` -> exact sort / tie-break
- `../fixtures/plan_items_guard_fail.json` -> guard fail grouping
- `../fixtures/plan_items_forced_watch_only.json` -> forced WATCH_ONLY contract
- `../fixtures/plan_items_no_trade_hide_all.json` -> NO_TRADE hide-all contract
- `../fixtures/plan_items_no_trade_min_eligible.json` -> NO_TRADE minimum eligible contract
- `../fixtures/scored_items_quantile_cutoff.json` -> quantile cutoff / qualified pools
- `../fixtures/confirm_immutability_pair.json` -> PLAN immutability under CONFIRM
- `../fixtures/confirm_snapshots_two.json` -> snapshot selection contract
- `../fixtures/confirm_payload_with_orderbook_fields.json` -> ignore non-contract orderbook fields
- `../fixtures/confirm_payload_with_unknown_top_level_field.json` -> top-level drift must FAIL (INVALID_SCHEMA_DRIFT)
- `../fixtures/bt_coverage_guard_minimal.json` -> BT coverage guard
- `../fixtures/bt_eval_metrics_minimal.json` -> eval sufficiency guard
- `../fixtures/bt_oos_proof_minimal.json` -> OOS proof guard
- `../fixtures/artifact_reference_guard_minimal.json` -> artifact allowlist / path guard
- `../fixtures/universe_equivalence_sample.json` -> universe equivalence audit
- `../fixtures/plan_universe_snapshot_sample.json` -> production snapshot export schema
- `../fixtures/hash_contract_vectors.json` -> data_batch_hash canonical vectors

## Anti-Drift Rule (LOCKED)
- Jika kontrak menyebut fixture baru, file fisiknya wajib ada pada [`../fixtures/`](../fixtures/README.md).
- Jika file fixture dihapus, rename, atau dipindah, semua referensi dokumen wajib diperbarui pada commit yang sama.
- Tidak boleh ada placeholder fixture name di dokumen ini.
