# 13 — Contract Test Checklist — WS_EOD_PLAN_CONFIRM (LOCKED)

## Purpose
Mengunci daftar minimum contract tests Weekly Swing agar seluruh kontrak WS dapat diterjemahkan ke test suite nyata tanpa tafsir bebas.

## Scope
Dokumen ini berlaku untuk test contract Weekly Swing pada area:
- paramset validation,
- PLAN determinism,
- CONFIRM isolation,
- grouping semantics,
- backtest governance,
- artifact governance.

Dokumen ini tidak menggantikan validator spec, runtime schema, atau algorithm doc.
Dokumen ini adalah jembatan antara kontrak normatif dan implementasi test.

## Inputs
- fixture resmi pada folder [`fixtures/`](fixtures/README.md),
- contoh paramset resmi,
- kontrak WS dari file 01–12 dan 14–20.

## Outputs
- inventory test case minimum,
- mapping fixture ke test,
- acceptance rule untuk CI / pre-merge verification.

## Prerequisites
- [`12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md`](12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md)
- [`_refs/WS_FIXTURE_INVENTORY.md`](_refs/WS_FIXTURE_INVENTORY.md) — inventaris referensi fixture; bukan sumber aturan utama
- [`02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`](02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md)
- [`03_WS_DATA_MODEL_MARIADB.md`](03_WS_DATA_MODEL_MARIADB.md)
- [`10_WS_CONFIRM_OVERLAY.md`](10_WS_CONFIRM_OVERLAY.md)
- `../_shared/07_CONTRACT_FAILURE_CODES_LOCKED.md`


## Rule of use for `_refs/` (LOCKED)
Seluruh dokumen `_refs/` yang membahas contract tests, fixture inventory, atau worked examples bersifat elaborasi pendukung dan tidak mengalahkan checklist ini. Perubahan test minimum, acceptance, dan PASS/FAIL resmi harus dilakukan di checklist ini terlebih dahulu, lalu referensi pendukung disinkronkan.

## 1) Official Fixture Root (LOCKED)
Source of truth fixture Weekly Swing ada di:
- [`fixtures/`](fixtures/README.md)

Jika test suite di codebase ingin memirror fixture ke folder lain, mirror tersebut **harus byte-identical** terhadap source of truth di atas.
Contoh path mirror yang diizinkan:
- `tests/Fixtures/watchlist/ws/`

Namun dokumen ini selalu menyebut fixture dengan **path relatif eksplisit dari root policy Weekly Swing**, misalnya:
- [`fixtures/paramset_valid.json`](fixtures/paramset_valid.json)
- [`fixtures/confirm_immutability_pair.json`](fixtures/confirm_immutability_pair.json)

Aturan:
- tidak boleh mengandalkan nama file telanjang tanpa path,
- tidak boleh mengandalkan base-path inference diam-diam,
- dan tidak boleh menyebut fixture yang file fisiknya tidak ada.

## 2) Minimal Fixture Inventory (LOCKED)
Fixture minimum yang wajib tersedia:

### A) Paramset validator fixtures
- [`fixtures/paramset_valid.json`](fixtures/paramset_valid.json)
- [`fixtures/paramset_missing_required_key.json`](fixtures/paramset_missing_required_key.json)
- [`fixtures/paramset_unknown_key.json`](fixtures/paramset_unknown_key.json)
- [`fixtures/paramset_type_drift.json`](fixtures/paramset_type_drift.json)
- [`fixtures/paramset_missing_audit_field.json`](fixtures/paramset_missing_audit_field.json)
- [`fixtures/paramset_bad_enum.json`](fixtures/paramset_bad_enum.json)
- [`fixtures/paramset_bad_hash_contract.json`](fixtures/paramset_bad_hash_contract.json)
- [`fixtures/paramset_bad_eval.json`](fixtures/paramset_bad_eval.json)

### B) PLAN / grouping fixtures
- [`fixtures/PLAN_FIXTURE_A_TIES_V1.json`](fixtures/PLAN_FIXTURE_A_TIES_V1.json)
- [`fixtures/plan_items_guard_fail.json`](fixtures/plan_items_guard_fail.json)
- [`fixtures/plan_items_forced_watch_only.json`](fixtures/plan_items_forced_watch_only.json)
- [`fixtures/plan_items_no_trade_hide_all.json`](fixtures/plan_items_no_trade_hide_all.json)
- [`fixtures/plan_items_no_trade_min_eligible.json`](fixtures/plan_items_no_trade_min_eligible.json)
- [`fixtures/plan_items_artificial_ties.json`](fixtures/plan_items_artificial_ties.json)
- [`fixtures/scored_items_quantile_cutoff.json`](fixtures/scored_items_quantile_cutoff.json)
- [`fixtures/plan_universe_snapshot_sample.json`](fixtures/plan_universe_snapshot_sample.json)

### C) CONFIRM fixtures
- [`fixtures/confirm_immutability_pair.json`](fixtures/confirm_immutability_pair.json)
- [`fixtures/confirm_snapshots_two.json`](fixtures/confirm_snapshots_two.json)
- [`fixtures/confirm_payload_with_orderbook_fields.json`](fixtures/confirm_payload_with_orderbook_fields.json)

### D) Backtest governance fixtures
- [`fixtures/bt_coverage_guard_minimal.json`](fixtures/bt_coverage_guard_minimal.json)
- [`fixtures/universe_equivalence_sample.json`](fixtures/universe_equivalence_sample.json)
- [`fixtures/bt_eval_metrics_minimal.json`](fixtures/bt_eval_metrics_minimal.json)
- [`fixtures/bt_oos_proof_minimal.json`](fixtures/bt_oos_proof_minimal.json)
- [`fixtures/artifact_reference_guard_minimal.json`](fixtures/artifact_reference_guard_minimal.json)

### E) Hash fixture
- [`fixtures/hash_contract_vectors.json`](fixtures/hash_contract_vectors.json)

## 3) Global Test Hook Rules (LOCKED)
Implementasi test wajib memenuhi semua syarat berikut:
- deterministic,
- self-contained sebisa mungkin,
- tidak mengandalkan data DB liar untuk membuktikan kontrak,
- failure harus menghasilkan code/reason yang jelas,
- dan CI harus punya satu jalur standar untuk menjalankan seluruh contract tests WS.

Contoh entrypoint yang diizinkan:
- `php artisan watchlist:contract:ws`
- `vendor/bin/phpunit --group watchlist_ws_contract`

Nama final boleh berbeda, tetapi fungsi kontraknya harus sama.

## 4) Official Test Inventory (LOCKED)

### WS_CT_001 — Paramset valid harus PASS
- Fixture: [`fixtures/paramset_valid.json`](fixtures/paramset_valid.json)
- Assertion: validator PASS tanpa unknown/missing/type/enum errors.
- Assertion (LOCKED unit invariant):
  - 0 < risk.min_atr14_pct.value <= 1
  - 0 < risk.max_atr14_pct.value <= 1
  - 0 < risk.atr_ideal_low.value <= 1
  - 0 < risk.atr_ideal_high.value <= 1
  - `atr14_pct` wajib unit fraction (0..1); input percent-point (mis. 1.0 = 1%) dianggap salah unit dan wajib FAIL di validator.


### WS_CT_002 — Missing required key harus FAIL
- Fixture: [`fixtures/paramset_missing_required_key.json`](fixtures/paramset_missing_required_key.json)
- Expected fail code: `CF_PARAMSET_MISSING_KEY`

### WS_CT_003 — Unknown key harus FAIL
- Fixture: [`fixtures/paramset_unknown_key.json`](fixtures/paramset_unknown_key.json)
- Expected fail code: `CF_PARAMSET_UNKNOWN_KEY`

### WS_CT_004 — Type drift harus FAIL
- Fixture: [`fixtures/paramset_type_drift.json`](fixtures/paramset_type_drift.json)
- Expected fail code: `CF_PARAMSET_TYPE_DRIFT`

### WS_CT_005 — Missing audit field harus FAIL
- Fixture: [`fixtures/paramset_missing_audit_field.json`](fixtures/paramset_missing_audit_field.json)
- Expected fail code: `CF_PARAMSET_AUDIT_SCHEMA_INVALID`

### WS_CT_006 — Bad enum harus FAIL
- Fixture: [`fixtures/paramset_bad_enum.json`](fixtures/paramset_bad_enum.json)
- Expected fail code: `CF_PARAMSET_ENUM_INVALID`

### WS_CT_007 — Hash contract violation harus FAIL
- Fixture: [`fixtures/paramset_bad_hash_contract.json`](fixtures/paramset_bad_hash_contract.json)
- Expected fail code: `CF_HASH_CONTRACT_VIOLATION`

### WS_CT_008 — Eval gate invalid harus FAIL
- Fixture: [`fixtures/paramset_bad_eval.json`](fixtures/paramset_bad_eval.json)
- Expected fail code: `CF_EVAL_GATE_INVALID`

### WS_CT_009 — PLAN determinism harus PASS
- Fixtures:
  - [`fixtures/PLAN_FIXTURE_A_TIES_V1.json`](fixtures/PLAN_FIXTURE_A_TIES_V1.json)
  - [`fixtures/paramset_valid.json`](fixtures/paramset_valid.json)
- Assertions:
  - run PLAN 2x dengan input sama menghasilkan output canonical identik,
  - `meta.plan_hash` identik,
  - ranking dan grouping identik.

### WS_CT_010 — Confirm isolation / plan immutability harus PASS
- Fixture: [`fixtures/confirm_immutability_pair.json`](fixtures/confirm_immutability_pair.json)
- Assertions:
  - `plan_hash_before == plan_hash_after`
  - tidak ada mutation pada PLAN persistence scope.

### WS_CT_011 — Confirm snapshot selection harus PASS
- Fixture: [`fixtures/confirm_snapshots_two.json`](fixtures/confirm_snapshots_two.json)
- Assertions:
  - snapshot terpilih = `captured_at DESC`,
  - jika tie, `snapshot_id DESC`.

### WS_CT_012 — Confirm ignores non-contract fields harus PASS
- Fixture: [`fixtures/confirm_payload_with_orderbook_fields.json`](fixtures/confirm_payload_with_orderbook_fields.json)
- Assertions:
  - output CONFIRM identik dengan dan tanpa non-contract fields,
  - non-contract fields tidak menghasilkan reason baru,
  - field di luar kontrak di-ignore.

### WS_CT_013 — Group semantics rules harus PASS
- Fixtures:
  - [`fixtures/plan_items_guard_fail.json`](fixtures/plan_items_guard_fail.json)
  - [`fixtures/plan_items_forced_watch_only.json`](fixtures/plan_items_forced_watch_only.json)
- Assertions:
  - guard fail -> `AVOID`,
  - forced watch only -> `WATCH_ONLY`,
  - forced watch only tidak boleh naik menjadi `TOP_PICKS`/`SECONDARY` walau skor tinggi.

### WS_CT_014 — Tie-breaker sort keys harus PASS
- Fixtures:
  - [`fixtures/PLAN_FIXTURE_A_TIES_V1.json`](fixtures/PLAN_FIXTURE_A_TIES_V1.json)
  - atau [`fixtures/plan_items_artificial_ties.json`](fixtures/plan_items_artificial_ties.json)
- Assertion:
  - order deterministic sesuai sort keys contract.

### WS_CT_015 — Qualified pools / quantile cutoff contract harus PASS
- Fixtures:
  - [`fixtures/PLAN_FIXTURE_A_TIES_V1.json`](fixtures/PLAN_FIXTURE_A_TIES_V1.json)
  - atau [`fixtures/scored_items_quantile_cutoff.json`](fixtures/scored_items_quantile_cutoff.json)
- Assertion:
  - qualified pools dan cutoff sesuai kontrak grouping.

### WS_CT_016 — BT coverage guard contract harus PASS
- Fixture: [`fixtures/bt_coverage_guard_minimal.json`](fixtures/bt_coverage_guard_minimal.json)
- Assertion:
  - parameter BT tidak boleh lolos tanpa bukti coverage matrix / grid / cutoffs / picks yang sah.

### WS_CT_017 — NO_TRADE hide-all contract harus PASS
- Fixture: [`fixtures/plan_items_no_trade_hide_all.json`](fixtures/plan_items_no_trade_hide_all.json)
- Assertions:
  - `meta.fail_code = "NO_TRADE"`
  - `meta.fail_reason_codes` memuat `WS_NO_TRADE_ALL_FILTERED`
  - `items = []`
  - `meta.plan_hash` sesuai hash payload kosong canonical.

### WS_CT_017B — NO_TRADE min-eligible contract harus PASS
- Fixture: [`fixtures/plan_items_no_trade_min_eligible.json`](fixtures/plan_items_no_trade_min_eligible.json)
- Assertions:
  - `meta.fail_code = "NO_TRADE"`
  - `meta.fail_reason_codes = ["WS_NO_TRADE_MIN_ELIGIBLE"]`
  - `items = []`
  - `meta.plan_hash` sesuai hash payload kosong canonical.

### WS_CT_018 — Universe equivalence audit harus PASS
- Fixture: [`fixtures/universe_equivalence_sample.json`](fixtures/universe_equivalence_sample.json)
- Expected fail code jika mismatch: `CF_UNIVERSE_EQUIVALENCE_MISMATCH`

### WS_CT_019 — PLAN universe snapshot export schema harus PASS
- Fixture: [`fixtures/plan_universe_snapshot_sample.json`](fixtures/plan_universe_snapshot_sample.json)
- Expected fail code jika invalid: `CF_PLAN_UNIVERSE_SNAPSHOT_SCHEMA_INVALID`

### WS_CT_020 — Eval metrics sufficiency guard harus PASS
- Fixture: [`fixtures/bt_eval_metrics_minimal.json`](fixtures/bt_eval_metrics_minimal.json)
- Expected fail code jika kurang: `CF_EVAL_METRICS_INSUFFICIENT`

### WS_CT_021 — OOS proof guard harus PASS
- Fixture: [`fixtures/bt_oos_proof_minimal.json`](fixtures/bt_oos_proof_minimal.json)
- Expected fail code jika gagal: `CF_OOS_PROOF_FAILED`

### WS_CT_022 — Artifact reference guard harus PASS
- Fixture: [`fixtures/artifact_reference_guard_minimal.json`](fixtures/artifact_reference_guard_minimal.json)
- Expected fail code jika melanggar: `CF_ARTIFACT_REFERENCE_VIOLATION`

## 5) Acceptance Rules (LOCKED)
Satu implementasi Weekly Swing dianggap siap merge hanya jika:
- semua test di atas ada,
- semua fixture resmi bisa ditemukan pada path yang disebut,
- tidak ada fixture phantom,
- dan semua failure class mengeluarkan fail code/reason code deterministik.

## 6) Anti-Drift Rule
Jika ada kontrak baru yang membutuhkan fixture baru:
- file fixture fisik wajib ditambahkan pada [`fixtures/`](fixtures/README.md),
- dokumen [`_refs/WS_FIXTURE_INVENTORY.md`](_refs/WS_FIXTURE_INVENTORY.md) wajib diupdate,
- dan inventory test pada dokumen ini wajib diperbarui pada commit yang sama.

## Reference
- [`_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`](_refs/WS_FAILURE_BEHAVIOR_MATRIX.md)
- [`_refs/WS_FIXTURE_INVENTORY.md`](_refs/WS_FIXTURE_INVENTORY.md) — inventaris referensi fixture; bukan sumber aturan utama
- [`03_WS_DATA_MODEL_MARIADB.md`](03_WS_DATA_MODEL_MARIADB.md)
- [`10_WS_CONFIRM_OVERLAY.md`](10_WS_CONFIRM_OVERLAY.md)
