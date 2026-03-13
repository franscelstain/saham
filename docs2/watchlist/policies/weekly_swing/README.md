# Weekly Swing (WS) — Index

> **Status:** LOCKED (Normative)
> **Doc Role:** Weekly Swing policy contract index


## Purpose
Entry point dokumentasi policy **Weekly Swing (WS)**.

## Scope
Dokumen di folder ini adalah kontrak normatif untuk:
- proses PLAN (berbasis EOD) dan CONFIRM (runtime),
- penilaian skor WS,
- output runtime yang harus stabil,
- governance backtest + kalibrasi parameter.

## Inputs
- Pembaca yang ingin mengimplementasikan policy WS (engineer, reviewer).

## Outputs
- Urutan baca yang benar.
- Peta dokumen utama vs referensi.

## Start here
Baca dokumen bernomor berikut secara berurutan untuk implementasi inti:
1. [`01_WS_OVERVIEW.md`](01_WS_OVERVIEW.md)
2. [`02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`](02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md)
3. [`03_WS_DATA_MODEL_MARIADB.md`](03_WS_DATA_MODEL_MARIADB.md)
4. [`04_WS_PARAMSET_JSON_CONTRACT.md`](04_WS_PARAMSET_JSON_CONTRACT.md)
5. [`05_WS_PARAMETER_REGISTRY_COMPLETE.md`](05_WS_PARAMETER_REGISTRY_COMPLETE.md)
6. [`06_WS_PARAMSET_VALIDATOR_SPEC.md`](06_WS_PARAMSET_VALIDATOR_SPEC.md)
7. [`07_WS_REASON_CODES_AND_HASH.md`](07_WS_REASON_CODES_AND_HASH.md)
8. [`08_WS_PLAN_ALGORITHM.md`](08_WS_PLAN_ALGORITHM.md)
9. [`09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`](09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md)
10. [`10_WS_CONFIRM_OVERLAY.md`](10_WS_CONFIRM_OVERLAY.md)

Lanjutkan ke dokumen berikut bila membutuhkan pembuktian, kalibrasi, atau verifikasi kepatuhan:
- [`12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md`](12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md)
- [`13_WS_CONTRACT_TEST_CHECKLIST.md`](13_WS_CONTRACT_TEST_CHECKLIST.md)
- [`14_WS_BT_COVERAGE_MATRIX_LOCKED.md`](14_WS_BT_COVERAGE_MATRIX_LOCKED.md)
- [`15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md)
- [`16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md`](16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md)
- [`17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`](17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md)
- [`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)

## Folder pendukung
- Artefak DB/seed WS: [`db/README.md`](db/README.md)
- Referensi/fixture WS (non-normatif): [`_refs/README.md`](_refs/README.md)
- `_refs/` hanya untuk contoh, glossary, worked example, dan schema ilustratif.
- `_refs/` tidak boleh menjadi sumber aturan baru.
- Jika aturan normatif sudah tertulis pada dokumen bernomor, `_refs/` hanya boleh mengulangi atau mencontohkan aturan tersebut.

## Source-of-truth boundaries

Di dalam domain Weekly Swing, file bernomor adalah satu-satunya rumah aturan wajib. Folder `_refs/` hanya berfungsi sebagai referensi pembaca. Folder `examples/` hanya memuat contoh yang patuh kontrak. Folder `fixtures/` hanya memuat artefak uji yang tunduk ke kontrak normatif.

Reviewer dan implementer tidak boleh mengangkat aturan baru dari `_refs/`, examples, atau fixtures bila aturan tersebut belum tertulis di file bernomor.

## Status
Dokumen bernomor di folder ini adalah kontrak (normatif) kecuali yang jelas diberi label `_refs` atau `reference`.
