# Failure → Behavior Matrix — Weekly Swing (WS_EOD_PLAN_CONFIRM)

## Purpose
Mengunci perilaku sistem saat kondisi invalid, stale, atau kualitas data buruk agar implementasi, output, dan UI tidak berbeda-beda.

## Matrix

| Condition | Detection Source | Severity | System Behavior | Runtime Status | UI / Output | Reason Code |
|---|---|---:|---|---|---|---|
| Paramset invalid | Validator | Hard | Abort run | ABORT | Tidak tampilkan hasil PLAN | WS_PARAM_INVALID |
| ACTIVE param_set tidak ditemukan | Execution | Hard | Abort run | ABORT | Tidak tampilkan hasil PLAN | WS_PARAMSET_NOT_FOUND |
| EOD batch incomplete | Execution / data readiness | Hard | Abort PLAN | ABORT | Tidak tampilkan hasil PLAN | WS_EOD_INCOMPLETE |
| Coverage ratio < min_coverage_ratio | Data readiness | Hard | Abort PLAN | ABORT | Tidak tampilkan hasil PLAN | WS_COVERAGE_LOW |
| Required source / field missing | Data contract | Hard | Abort PLAN | ABORT | Tidak tampilkan hasil PLAN | WS_DATA_CONTRACT_FAIL |
| Eligible count < min_eligible_count | Dynamic selection | Hard | No trade global | NO_TRADE | Hide all / tampilkan status no-trade | WS_NO_TRADE_MIN_ELIGIBLE |
| top_pool kosong | Dynamic selection | Medium | `TOP_PICKS = 0`, lanjut proses | OK | TOP kosong | WS_TOP_POOL_EMPTY |
| secondary_pool kosong | Dynamic selection | Low | `SECONDARY = 0`, lanjut proses | OK | SECONDARY kosong | WS_SECONDARY_EMPTY |
| RR < min_rr | Plan algorithm | Medium | Forced WATCH_ONLY | OK | Tampilkan WATCH_ONLY | WS_RR_TOO_LOW |
| Breakout terlalu extended | Plan algorithm | Medium | Forced WATCH_ONLY | OK | Tampilkan WATCH_ONLY | WS_BREAKOUT_EXTENDED |
| Snapshot stale | Confirm overlay | Medium | Confirm delay | OK | Label DELAY | WS_STALE |
| Spread > spread_max_pct | Confirm overlay | Medium | Confirm caution | OK | Label CAUTION | WS_SPR_WIDE |
| Drift > max_drift_from_entry_pct | Confirm overlay | Medium | Confirm caution | OK | Label CAUTION | WS_DRIFT_FAR |
| Bid/ask runtime tidak tersedia | Confirm overlay | Low | Lanjut tanpa spread-based decision | OK | Label sesuai check lain | WS_SPR_NA |
| Hash mismatch (PLAN immutability) | Contract test / audit | Hard | Fail test / reject release | FAIL_TEST | Tidak relevan untuk UI runtime | WS_PLAN_HASH_MISMATCH |

## Notes
- `ABORT` berarti run tidak menghasilkan output PLAN yang layak dipakai.
- `NO_TRADE` berarti run valid, tetapi sengaja tidak menampilkan kandidat karena kualitas tidak memenuhi syarat.
- `OK` berarti run tetap lanjut, namun item tertentu bisa berubah label menjadi `WATCH_ONLY`, `CAUTION`, atau `DELAY`.
- `FAIL_TEST` hanya berlaku untuk contract test / release gate, bukan status runtime operasional.

## Intent
- File ini tidak menambah rule baru.
- File ini hanya mengunci konsekuensi sistem dari kondisi yang sudah didefinisikan di dokumen utama.