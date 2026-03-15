# 14 — WS BT Coverage Matrix (LOCKED)

## Purpose

Dokumen ini mengunci coverage proof antara parameter runtime WS dan bukti BT yang sah; dokumen ini bukan registry parameter dan bukan validator.

Mengunci definisi coverage minimum untuk parameter Weekly Swing yang berasal dari backtest (`origin = BT`) agar setiap parameter BT yang dipakai runtime punya bukti artefak kalibrasi yang nyata, dapat diaudit, dan dapat dilacak ke grid / cutoffs / picks.

## Scope
Dokumen ini hanya mengatur **coverage proof** untuk parameter BT.
Dokumen ini tidak mengganti algorithm scoring, validator, atau sufficiency gate lain.

## Inputs
- registry parameter Weekly Swing,
- hasil calibration/backtest,
- tabel artefak resmi yang ada di manifest WS.

## Outputs
- coverage matrix minimum,
- aturan keterkaitan parameter BT dengan artefak backtest,
- acceptance rule untuk promosi paramset BT.

## Prerequisites
- [`12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md`](12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md)
- [`13_WS_CONTRACT_TEST_CHECKLIST.md`](13_WS_CONTRACT_TEST_CHECKLIST.md)
- [`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)

## 1) Rule of Coverage (LOCKED)

Jika sebuah parameter belum memiliki coverage proof, status parameter tersebut tidak otomatis invalid secara schema, tetapi tidak boleh diklaim sebagai BT-backed tanpa pengecualian terdokumentasi.

Setiap parameter dengan `origin = BT` yang dipakai runtime wajib memiliki bukti minimal berikut:
1. parameter itu muncul pada grid / evaluasi yang resmi,
2. artefak hasilnya dapat ditelusuri ke cutoffs/picks/eval yang sah,
3. dan coverage-nya cukup untuk membuktikan bahwa parameter tersebut bukan angka liar.

Jika salah satu bukti di atas tidak ada, parameter BT tersebut tidak boleh dipakai untuk promote ACTIVE.

## 2) Official Coverage Artifacts (LOCKED)
Artefak resmi yang dipakai untuk coverage BT Weekly Swing adalah:
- `watchlist_bt_param_grid`
- `watchlist_bt_eval`
- `watchlist_bt_picks_ws`
- `watchlist_bt_cutoffs_ws`
- `watchlist_bt_oos_eval_ws`

Catatan:
- detail authoritative daftar artefak ada di [`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md),
- dokumen ini hanya menjelaskan relasi coverage-nya.

## 3) Coverage Matrix Minimum (LOCKED)

Ownership daftar key canonical tetap berada pada file 05; dokumen ini hanya memetakan coverage evidencenya.

Setiap parameter BT yang memengaruhi runtime harus punya row coverage matrix dengan kolom minimal berikut:
- `param_key`
- `origin`
- `grid_column`
- `cutoff_dependency`
- `pick_dependency`
- `eval_dependency`
- `notes`

Aturan:
- `origin` untuk dokumen ini harus `BT`,
- `grid_column` harus menunjuk ke kolom resmi pada `watchlist_bt_param_grid`,
- dependency lain harus menunjuk artefak resmi yang benar-benar ada.

## 4) Required Coverage Semantics

### A) Grid presence
Jika sebuah parameter BT diklaim aktif di runtime, maka:
- parameter itu wajib punya representasi eksplisit pada `watchlist_bt_param_grid`,
- dan nama/semantics-nya tidak boleh ambigu.

### B) Cutoff dependency
Jika bucket selection dipengaruhi cutoff score, maka bukti cutoff wajib ada pada:
- `watchlist_bt_cutoffs_ws`

### C) Pick dependency
Jika evidence hasil kalibrasi memakai pick list, maka pick terkait wajib ada pada:
- `watchlist_bt_picks_ws`

### D) Eval dependency
Jika parameter dipromosikan karena performa, maka bukti metrik evaluasi wajib ada pada:
- `watchlist_bt_eval`
- dan untuk promote ACTIVE juga wajib lolos bukti OOS dari `watchlist_bt_oos_eval_ws`

## 5) Pick-to-Cutoff Integrity Rule (LOCKED)
Untuk setiap row pick yang mengklaim bucket tertentu:
- jika `bucket_code = 'TOP_PICKS'`, maka `score_total >= top_cutoff_score`
- jika `bucket_code = 'SECONDARY'`, maka `score_total >= secondary_cutoff_score`

Jika aturan ini gagal, maka coverage proof gagal.

## 6) Missing Coverage Classes
Coverage dianggap gagal jika terjadi salah satu kondisi berikut:
- row coverage matrix tidak ada,
- `grid_column` tidak ada pada param grid resmi,
- cutoff yang dibutuhkan tidak ada,
- picks yang dibutuhkan tidak ada,
- mapping param BT ke artefak resmi tidak bisa dibuktikan,
- atau hasil pick melanggar cutoff integrity.

Reason code WS yang relevan boleh memakai namespace:
- `WS_BT_COV_MATRIX_MISSING`
- `WS_BT_COV_GRID_MISSING`
- `WS_BT_COV_CUTOFFS_MISSING`
- `WS_BT_COV_PICK_VIOLATION`

## 7) Acceptance Rule (LOCKED)
Paramset Weekly Swing yang mengandung parameter `origin = BT` hanya boleh dipromosikan jika:
- semua parameter BT punya row coverage matrix,
- row matrix menunjuk artefak resmi yang ada,
- integrity picks-vs-cutoffs lolos,
- dan bukti evaluasi/OOS yang diwajibkan juga lolos.

## 8) Test Anchor
Dokumen ini harus dibuktikan minimal oleh test:
- `WS_CT_016` dengan fixture [`fixtures/bt_coverage_guard_minimal.json`](fixtures/bt_coverage_guard_minimal.json)

## Next
- [`15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md)
