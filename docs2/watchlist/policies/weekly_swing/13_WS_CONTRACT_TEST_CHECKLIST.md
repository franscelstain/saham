# 13 — Contract Test Checklist — WS_EOD_PLAN_CONFIRM

## Purpose
Mengunci anti-drift khusus WS (di atas framework global).

## Prerequisites
### Weekly Swing
12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md

## Inputs
- Fixture dataset WS (EOD+indicators)
- Sample paramset WS

## Process

### A) WSParamSetContractTest
- Jalankan validator WS (lihat `06_WS_PARAMSET_VALIDATOR_SPEC.md`) dan pastikan PASS.
- Assert locked invariants persis.

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

### D1) ConfirmSnapshotSelectionTest (WS) (LOCKED)
- Siapkan 2 snapshot untuk `(policy_code, trade_date)`:
  - snapshot A: `captured_at` lebih lama
  - snapshot B: `captured_at` lebih baru
- Assert snapshot terpilih = yang `captured_at DESC`
- Jika `captured_at` sama, assert tie-breaker = `snapshot_id DESC`

### D2) ConfirmIgnoresOrderBookFieldsTest (WS) (LOCKED)
- Beri input/payload yang mengandung field non-contract (contoh: `bid1_price`, `ask1_price`, `spread`, `orderbook_json`)
- Assert hasil CONFIRM **identik** dengan saat field-field itu tidak ada
- Assert tidak ada reason code yang berasal dari bid/ask/spread/orderbook

### E) GroupSemanticsRulesTest (WS)
- Guard fail => AVOID + HIDE + reason guard
- Forced watch-only => WATCH_ONLY (cannot become TOP/SECONDARY)
- NO_TRADE => output API/UI tidak menampilkan kandidat; persistence audit tetap menyimpan item dan seluruhnya `HIDE` (LOCKED)

## Outputs
- Test checklist yang wajib ada sebelum deploy.

## Reference
- `_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`

## Next
### Weekly Swing
- 14_WS_BT_COVERAGE_MATRIX_LOCKED.md
### Action
db/REASON_CODES_SEED.sql

- [ ] Paramset validator memastikan `grouping.grouping_mode.value == 'QUALIFIED_POOLS_QUANTILE_CUTOFF'`.
- [ ] Tidak ada referensi sistem selection lain selain qualified pools + quantile cutoff.
- [ ] WS_UNIVERSE_EQUIVALENCE_TEST (LOCKED): backtest universe vs production universe harus match (pass/fail + canonical reason) untuk sample tanggal.
- [ ] BT_COVERAGE_GUARD (LOCKED): setiap param origin=BT wajib punya mapping di `14_WS_BT_COVERAGE_MATRIX_LOCKED.md`; kolom grid ada di schema; cutoff tersimpan di watchlist_bt_cutoffs_ws; picks menyimpan bucket_code; dan pick memenuhi cutoff.
- [ ] PLAN_UNIVERSE_SNAPSHOT_EXPORT (LOCKED): production PLAN wajib bisa export snapshot universe sesuai:
      `db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`
- [ ] WS_EVAL_METRICS_SUFFICIENCY_GUARD (LOCKED): `watchlist_bt_eval` harus memiliki metrik minimum dan lolos gating rules untuk memilih param_id BEST/ACTIVE.
- [ ] WS_OOS_PROOF_GUARD (LOCKED): pemilihan param_id BEST wajib disertai evaluasi OOS (70/30 split) dan lulus acceptance criteria.
- [ ] WS_ARTIFACT_REFERENCE_GUARD (LOCKED): dokumen WS tidak boleh menyebut artefak di luar manifest (18), kecuali tercatat di ledger (19).