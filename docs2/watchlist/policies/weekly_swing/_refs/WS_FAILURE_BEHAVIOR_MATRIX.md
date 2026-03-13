# Failure → Behavior Matrix — Weekly Swing (Reference)

## Purpose
Merangkum konsekuensi operasional dari kondisi gagal yang sudah didefinisikan pada dokumen normatif Weekly Swing.

## Scope
Dokumen ini membantu pembaca memetakan detection -> runtime status -> output/UI -> code.
Dokumen ini bukan owner kontrak perilaku sistem.

## Normative owner
Perilaku normatif yang diringkas di sini berasal dari:
- [`../02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`](../02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md)
- [`../07_WS_REASON_CODES_AND_HASH.md`](../07_WS_REASON_CODES_AND_HASH.md)
- [`../09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`](../09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md)
- [`../10_WS_CONFIRM_OVERLAY.md`](../10_WS_CONFIRM_OVERLAY.md)
- [`../13_WS_CONTRACT_TEST_CHECKLIST.md`](../13_WS_CONTRACT_TEST_CHECKLIST.md)

Jika ada konflik antara matriks ini dan dokumen bernomor di atas, maka dokumen bernomor yang berlaku.

## Matrix

| Condition | Detection Source | Severity | System Behavior | Runtime Status | UI / Output | Reason Code |
|---|---|---:|---|---|---|---|
| Paramset invalid | Validator | Hard | Fail run | FAILED | Tidak tampilkan hasil PLAN | PLAN_ABORT_PARAMSET_INVALID |
| ACTIVE param_set tidak ditemukan | Execution | Hard | Fail run | FAILED | Tidak tampilkan hasil PLAN | PLAN_ABORT_PARAMSET_NOT_FOUND |
| EOD batch incomplete | Execution / data readiness | Hard | Fail PLAN | FAILED | Tidak tampilkan hasil PLAN | PLAN_ABORT_DATA_INCOMPLETE |
| Coverage ratio < min_coverage_ratio | Data readiness | Hard | Fail PLAN | FAILED | Tidak tampilkan hasil PLAN | PLAN_ABORT_COVERAGE_LOW |
| Required source / field missing | Data contract | Hard | Fail PLAN | FAILED | Tidak tampilkan hasil PLAN | PLAN_ABORT_DATA_CONTRACT |
| Eligible count < min_eligible_count | Dynamic selection | Hard | No trade global | NO_TRADE | Output API/UI tidak menampilkan kandidat; persistence audit tetap menyimpan item dan seluruhnya `HIDE` | WS_NO_TRADE_MIN_ELIGIBLE |
| top_pool kosong | Dynamic selection | Medium | `TOP_PICKS = 0`, lanjut proses | OK | TOP kosong | WS_GRP_TOP |
| secondary_pool kosong | Dynamic selection | Low | `SECONDARY = 0`, lanjut proses | OK | SECONDARY kosong | WS_GRP_SEC |
| RR < min_rr | Plan algorithm | Medium | Forced WATCH_ONLY | OK | Tampilkan WATCH_ONLY | WS_FW_RR_LOW |
| Breakout terlalu extended | Plan algorithm | Medium | Forced WATCH_ONLY | OK | Tampilkan WATCH_ONLY | WS_FW_EXT |
| Snapshot stale | Confirm overlay | Medium | Confirm delay | OK | Label DELAY | WS_STALE |
| Snapshot missing | Confirm overlay | Medium | Confirm delay | OK | Label DELAY | WS_SNAPSHOT_MISSING |
| last_price tidak tersedia | Confirm overlay | Medium | Confirm delay | OK | Label DELAY | WS_NO_PRICE |
| Drift > max_drift_from_entry_pct | Confirm overlay | Medium | Confirm caution | OK | Label CAUTION | WS_DRIFT_FAR |
| Field intraday aggregate wajib tidak lengkap (turnover/volume) | Confirm overlay | High | Block | DELAY | Tidak sah untuk eksekusi | WS_INPUT_INCOMPLETE |
| Writeback detected during CONFIRM (writes to PLAN) | Contract test / audit | Hard | Fail test / reject release | FAIL_TEST | Tidak relevan untuk UI runtime | WS_PLAN_WRITEBACK_DETECTED |
| Hash mismatch (PLAN immutability) | Contract test / audit | Hard | Fail test / reject release | FAIL_TEST | Tidak relevan untuk UI runtime | WS_PLAN_HASH_MISMATCH |

## Notes
Matrix ini merangkum hubungan antara jenis failure dan perilaku sistem yang dirujuk oleh dokumen normatif Weekly Swing. Matrix ini tidak menetapkan acceptance rule baru. Untuk perilaku resmi, gunakan dokumen bernomor yang dirujuk oleh setiap baris matrix.

