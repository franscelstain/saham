# 12 — Contract Test Checklist — WS_EOD_PLAN_CONFIRM

## Purpose
Mengunci anti-drift khusus WS (di atas framework global).

## Prerequisites
### Weekly Swing
11_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md

## Inputs
- Fixture dataset WS (EOD+indicators)
- Sample paramset WS

## Process

### A) WSParamSetContractTest
- Jalankan validator WS (dok 06_WS_PARAMSET_VALIDATOR_SPEC.md) dan pastikan PASS.
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
- Run CONFIRM
- Assert plan tables tidak berubah:
  - plan_run hash & counts unchanged
  - plan_items unchanged

### E) GroupSemanticsRulesTest (WS)
- Guard fail => AVOID + HIDE + reason guard
- Forced watch-only => WATCH_ONLY (cannot become TOP/SECONDARY)
- NO_TRADE => semua HIDE (LOCKED)

## Outputs
- Test checklist yang wajib ada sebelum deploy.

## Reference
- `_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`

## Next
### Weekly Swing
- 13_WS_CANONICAL_PARAMSET_PROCEDURES.md
### Action
db/REASON_CODES_SEED.sql

- [ ] Paramset validator memastikan `grouping.grouping_mode.value == 'QUALIFIED_POOLS_QUANTILE_CUTOFF'`.
- [ ] Tidak ada referensi sistem selection lain selain qualified pools + quantile cutoff.
