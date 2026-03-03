# 13 — Contract Test Checklist — WS_EOD_PLAN_CONFIRM

## Purpose
Mengunci anti-drift khusus WS (di atas framework global).

## Prerequisites
### Weekly Swing
12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md

## Inputs
- Fixture dataset WS (EOD+indicators)
- Sample paramset WS

## LOCKED — Fixture Manifest (wajib dibuat, tanpa ini test hanya “wacana”)

**LOCKED — Fixture Base Path**

Semua referensi `fixture: <nama_file>` di dokumen ini **SECARA DEFAULT** mengacu ke folder:

- `docs/watchlist/policies/weekly_swing/fixtures/`

Saat implementasi test suite dibuat, folder tersebut **di-mirror/copy** menjadi:

- `tests/Fixtures/watchlist/ws/`

Aturan (LOCKED):
- Selama fase dokumentasi, fixture source-of-truth ada di `docs/.../fixtures/`.
- Saat fase coding, fixture yang dipakai test adalah copy identik (byte-identical) di `tests/...`.
- Jika nama file sama, maka dianggap file yang sama (tanpa perlu menulis path di tiap test case).

Daftar file minimal yang **WAJIB ADA** (LOCKED) beserta isinya:

### A) Paramset fixtures
- `paramset_valid.json` — 1 paramset WS lengkap dan valid (audit-node lengkap).
- `paramset_missing_required_key.json` — sama seperti valid tapi 1 key required hilang.
- `paramset_unknown_key.json` — sama seperti valid tapi ada 1 key ekstra yang tidak dikenal.
- `paramset_type_drift.json` — sama seperti valid tapi 1 value type salah (mis. string vs number).
- `paramset_missing_audit_field.json` — sama seperti valid tapi audit-node kehilangan 1 field wajib.
- `paramset_bad_enum.json` — enum invalid (origin/status/mode tidak ada di allowed set).
- `paramset_bad_eval.json` — eval gate invalid (mis. range/threshold tidak konsisten dengan spec).
- `paramset_bad_hash_contract.json` — hash contract lock sengaja dilanggar (untuk memicu `CF_HASH_CONTRACT_VIOLATION`).

### B) PLAN fixtures (golden master)
- `plan_universe_snapshot_sample.json` — snapshot universe EOD minimal (ticker_id, ticker_code, is_active, dll).
- `plan_items_guard_fail.json` — input EOD+indicators yang memicu guard fail (dv20/atr/vol_ratio) dan expected output reasons.
- `plan_items_artificial_ties.json` — input yang sengaja membuat `score_total_raw` tie untuk verifikasi tie-break `ticker_id ASC`.
- `plan_items_no_trade_hide_all.json` — input yang membuat semua candidate ter-filter sehingga output harus deterministik (mis. group `NO_TRADE`/empty sesuai spec).
- `scored_items_quantile_cutoff.json` — dataset kecil untuk verifikasi rule cutoff/quantile (jika dipakai oleh grouping).

### C) CONFIRM fixtures
- `confirm_snapshots_two.json` — 2 snapshot intraday untuk verifikasi “confirm overlay terpisah” dan tidak mengubah PLAN.
- `confirm_payload_with_orderbook_fields.json` — payload yang mengandung field orderbook (bid/ask ladder) dan **harus FAIL** (karena CONFIRM dilarang pakai orderbook).

### D) Backtest governance fixtures
- `bt_coverage_guard_minimal.json` — coverage matrix minimal: memastikan rule coverage “pass/fail” berjalan.
- `universe_equivalence_sample.json` — contoh universe backtest vs production untuk verifikasi equivalence.
- `bt_eval_metrics_minimal.json` — contoh output evaluasi (avg_ret_net_top, win_rate, picks_count, dll) sesuai schema.
- `bt_oos_proof_minimal.json` — contoh bukti OOS / walk-forward minimal (bukan narasi).

LOCKED — aturan umum fixture:
- semua file harus **self-contained** (tidak butuh DB) untuk contract test.
- semua angka mengikuti unit di `08_WS_PLAN_ALGORITHM.md` (pct 0–100 untuk atr/roc).
- setiap fixture yang menghasilkan FAIL harus menyertakan `expected_cf_code` / `expected_reason_codes` yang jelas.

## LOCKED — Code Hook Blueprint (belum ada codebase)
Tujuan: memastikan dokumen ini bisa langsung diterjemahkan jadi test suite tanpa tafsir.

### A) Hook yang WAJIB ada nanti (placeholder, tapi LOCKED)
- Runner: PHPUnit group `watchlist_ws_contract` (atau setara) yang wajib jalan di CI sebelum merge.
- Command standar:
  - `php artisan watchlist:contract:ws` (alias yang memanggil phpunit group di atas), atau
  - `vendor/bin/phpunit --group watchlist_ws_contract`
- Fixture root (LOCKED): `tests/Fixtures/watchlist/ws/`
- Semua test wajib:
  - deterministic (golden master / fixed input)
  - fail dengan reason code yang jelas (bukan “assert false” doang)

### B) Test Inventory (LOCKED, bullet list)
- WS_CT_001 — Paramset valid harus PASS (fixture: `paramset_valid.json`)
- WS_CT_002 — Missing required key harus FAIL (fixture: `paramset_missing_required_key.json`)
- WS_CT_003 — Unknown key harus FAIL (fixture: `paramset_unknown_key.json`)
- WS_CT_004 — Type drift harus FAIL (fixture: `paramset_type_drift.json`)
- WS_CT_005 — Missing audit field harus FAIL (fixture: `paramset_missing_audit_field.json`)
- WS_CT_006 — Bad enum origin/status harus FAIL (fixture: `paramset_bad_enum.json`)
- WS_CT_007 — Hash contract lock harus FAIL bila berubah (fixture: `paramset_bad_hash_contract.json`)
- WS_CT_008 — Eval gate harus FAIL bila invalid (fixture: `paramset_bad_eval.json`)
- WS_CT_009 — PLAN determinism harus PASS (fixture: `PLAN_FIXTURE_A_TIES_V1.json` + `paramset_valid.json`)
- WS_CT_010 — Confirm isolation / plan immutability harus PASS (fixture: plan+confirm snapshot)
- WS_CT_011 — Confirm snapshot selection harus PASS (fixture: `confirm_snapshots_two.json`)
- WS_CT_012 — Confirm ignores non-contract fields harus PASS (fixture: `confirm_payload_with_orderbook_fields.json`)
- WS_CT_013 — Group semantics rules harus PASS (fixture: `plan_items_guard_fail.json`, `plan_items_forced_watch_only.json`)
- WS_CT_014 — Tie-breaker sort_keys harus PASS (fixture: `PLAN_FIXTURE_A_TIES_V1.json`)
- WS_CT_015 — Qualified pools + quantile cutoff contract harus PASS (fixture: `PLAN_FIXTURE_A_TIES_V1.json`)
- WS_CT_016 — BT_COVERAGE_GUARD contract harus PASS (fixture: `bt_coverage_guard_minimal.json`)
- WS_CT_017 — NO_TRADE gate contract harus PASS (fixture: `plan_items_no_trade_hide_all.json`)
- WS_CT_018 — Universe equivalence (BT vs PROD) harus PASS (fixture: `universe_equivalence_sample.json`)
- WS_CT_019 — PLAN universe snapshot export schema harus PASS (fixture: `plan_universe_snapshot_sample.json`)
- WS_CT_020 — Eval metrics sufficiency guard harus PASS (fixture: `bt_eval_metrics_minimal.json`)
- WS_CT_021 — OOS proof guard harus PASS (fixture: `bt_oos_proof_minimal.json`)
- WS_CT_022 — Artifact reference guard harus PASS (fixture: `artifact_reference_guard_minimal.json`)

### C) Fixture Set (LOCKED)
- `paramset_valid.json` (copy dari `db/PARAMSET_WS_ACTIVE_EXAMPLE.json`)
- `paramset_unknown_key.json` (tambah 1 key liar di root)
- `paramset_missing_required_key.json` (hapus 1 key registry)
- `paramset_type_drift.json` (ubah 1 `*.value` number jadi string)
- `paramset_missing_audit_field.json` (hapus `rationale` atau `change_triggers`)
- `paramset_bad_enum.json` (origin/status enum salah)
- `paramset_bad_hash_contract.json` (ubah `hash_contract.order_by.value`)
- `paramset_bad_eval.json` (buat `eval.min_month_win_rate_min.value = 2`)
- `confirm_snapshots_two.json` (2 snapshot untuk 1 trade_date, beda captured_at)
- `confirm_payload_with_orderbook_fields.json` (tambahkan `bid1_price/ask1_price/spread/orderbook_json`)
- `PLAN_FIXTURE_A_TIES_V1.json` (golden PLAN fixture lengkap: scoring+guard+ties+quantile cutoff+expected output)
- `plan_items_artificial_ties.json` (opsional bila tidak memakai golden fixture; 2–3 ticker skor sama untuk uji sort_keys)
- `scored_items_quantile_cutoff.json` (opsional bila tidak memakai golden fixture; dataset kecil untuk uji qualified pools + quantile cutoff)
- `bt_coverage_guard_minimal.json` (fixture mapping BT params → coverage matrix + contoh cutoff/picks minimal)
- `plan_items_no_trade_hide_all.json` (plan items yang memicu NO_TRADE dan memastikan seluruh output `HIDE`)
- `universe_equivalence_sample.json` (sample universe BT vs PROD + expected match/fail + reason canonical)
- `plan_universe_snapshot_sample.json` (sample output snapshot + validasi schema `db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`)
- `bt_eval_metrics_minimal.json` (sample row/rows `watchlist_bt_eval` + threshold minimal untuk sufficiency guard)
- `bt_oos_proof_minimal.json` (sample split 70/30 + acceptance criteria untuk OOS proof guard)
- `artifact_reference_guard_minimal.json` (sample daftar referensi artefak dokumen + manifest/ledger allowlist)

## Process

### A) WSParamSetContractTest
- Wajib ada test berikut (mapping langsung ke `06_WS_PARAMSET_VALIDATOR_SPEC.md`):
  - WS_CT_001: PASS untuk `paramset_valid.json`
  - WS_CT_002: FAIL untuk `paramset_missing_required_key.json` (registry completeness)
  - WS_CT_003: FAIL untuk `paramset_unknown_key.json` (unknown key fail)
  - WS_CT_004: FAIL untuk `paramset_type_drift.json` (type drift enforcement)
  - WS_CT_005: FAIL untuk `paramset_missing_audit_field.json` (audit-node schema leaf)
  - WS_CT_006: FAIL untuk `paramset_bad_enum.json` (enum origin/status)
  - WS_CT_007: FAIL untuk `paramset_bad_hash_contract.json` (hash_contract lock)
  - WS_CT_008: FAIL untuk `paramset_bad_eval.json` (eval gates)
- Semua FAIL wajib mengeluarkan reason code yang deterministik (bukan pesan error bebas).

LOCKED — Reason code mapping (untuk failure deterministik):
- Definisi canonical untuk semua `CF_*` ada di: `../_shared/07_CONTRACT_FAILURE_CODES_LOCKED.md`
- WS_CT_002 => `CF_PARAMSET_MISSING_KEY`
- WS_CT_003 => `CF_PARAMSET_UNKNOWN_KEY`
- WS_CT_004 => `CF_PARAMSET_TYPE_DRIFT`
- WS_CT_005 => `CF_PARAMSET_AUDIT_SCHEMA_INVALID`
- WS_CT_006 => `CF_PARAMSET_ENUM_INVALID`
- WS_CT_007 => `CF_HASH_CONTRACT_VIOLATION`
- WS_CT_008 => `CF_EVAL_GATE_INVALID`

- WS_CT_018 => `CF_UNIVERSE_EQUIVALENCE_MISMATCH`
- WS_CT_019 => `CF_PLAN_UNIVERSE_SNAPSHOT_SCHEMA_INVALID`
- WS_CT_020 => `CF_EVAL_METRICS_INSUFFICIENT`
- WS_CT_021 => `CF_OOS_PROOF_FAILED`
- WS_CT_022 => `CF_ARTIFACT_REFERENCE_VIOLATION`

### B) PlanDeterminismTest (WS)
- Run PLAN WS 2x dengan input sama => output identik:
  - data_batch_hash sama
  - semua plan_items fields deterministik (scores, groups, display)
- Test tie-breaker: buat artificial ties dan pastikan order mengikuti sort_keys.

### C) HashContractTest (WS)
- Fixture kecil 3 ticker
- Assert hash == expected literal SHA256 (golden master)
- Ubah 1 value => hash berubah

### D) ConfirmIsolationTest (WS)
- Generate PLAN
- Ambil `plan_hash_before` sesuai **LOCKED — PLAN Hash Scope** di `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`.
- Run CONFIRM
- Ambil `plan_hash_after` dengan scope yang sama.
- Assert:
  - `plan_hash_before == plan_hash_after`
  - Tidak ada `UPDATE/DELETE` pada persistence PLAN (DB write-scope audit)

### D1) ConfirmSnapshotSelectionTest (WS) (LOCKED) — WS_CT_011
- Siapkan 2 snapshot untuk `(policy_code, trade_date)`:
  - snapshot A: `captured_at` lebih lama
  - snapshot B: `captured_at` lebih baru
- Assert snapshot terpilih = yang `captured_at DESC`
- Jika `captured_at` sama, assert tie-breaker = `snapshot_id DESC`

### D2) ConfirmIgnoresOrderBookFieldsTest (WS) (LOCKED) — WS_CT_012
- Beri input/payload yang mengandung field non-contract (contoh: `bid1_price`, `ask1_price`, `spread`, `orderbook_json`)
- Assert hasil CONFIRM **identik** dengan saat field-field itu tidak ada
- Assert tidak ada reason code yang berasal dari bid/ask/spread/orderbook

### E) GroupSemanticsRulesTest (WS)
- Guard fail => AVOID + HIDE + reason guard
- Forced watch-only => WATCH_ONLY (cannot become TOP/SECONDARY)
- NO_TRADE => output API/UI tidak menampilkan kandidat; persistence audit tetap menyimpan item dan seluruhnya `HIDE` (LOCKED) — WS_CT_017

## Outputs
- Test checklist yang wajib ada sebelum deploy.

## Reference
- `_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`

## Next
### Weekly Swing
- 14_WS_BT_COVERAGE_MATRIX_LOCKED.md
### Action
db/REASON_CODES_SEED.sql

- [ ] WS_CT_015 (LOCKED): `grouping.grouping_mode.value == 'QUALIFIED_POOLS_QUANTILE_CUTOFF'` dan **tidak ada** sistem selection lain selain qualified pools + quantile cutoff.
- [ ] WS_CT_018 (LOCKED): Universe equivalence — backtest universe vs production universe harus match (pass/fail + canonical reason) untuk sample tanggal.
- [ ] WS_CT_016 (LOCKED): BT_COVERAGE_GUARD — setiap param origin=BT wajib punya mapping di `14_WS_BT_COVERAGE_MATRIX_LOCKED.md`; kolom grid ada di schema; cutoff tersimpan di watchlist_bt_cutoffs_ws; picks menyimpan bucket_code; dan pick memenuhi cutoff.
- [ ] WS_CT_019 (LOCKED): PLAN universe snapshot export — production PLAN wajib bisa export snapshot universe sesuai `db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`.
- [ ] WS_CT_020 (LOCKED): Eval metrics sufficiency guard — `watchlist_bt_eval` harus memiliki metrik minimum dan lolos gating rules untuk memilih param_id BEST/ACTIVE.
- [ ] WS_CT_021 (LOCKED): OOS proof guard — pemilihan param_id BEST wajib disertai evaluasi OOS (70/30 split) dan lulus acceptance criteria.
- [ ] WS_CT_022 (LOCKED): Artifact reference guard — dokumen WS tidak boleh menyebut artefak di luar manifest (18), kecuali tercatat di ledger (19).