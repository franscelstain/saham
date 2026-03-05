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
- Pembaca yang ingin mengimplementasikan policy WS (engineer, AI, reviewer).

## Outputs
- Urutan baca yang benar.
- Peta dokumen utama vs referensi.

## Start here
Urutan baca minimum yang aman:
1. [`01_WS_OVERVIEW.md`](01_WS_OVERVIEW.md)
2. [`02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`](02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md)
3. [`03_WS_DATA_MODEL_MARIADB.md`](03_WS_DATA_MODEL_MARIADB.md)
4. [`05_WS_PARAMETER_REGISTRY_COMPLETE.md`](05_WS_PARAMETER_REGISTRY_COMPLETE.md)
5. [`07_WS_REASON_CODES_AND_HASH.md`](07_WS_REASON_CODES_AND_HASH.md)
6. [`06_WS_PARAMSET_VALIDATOR_SPEC.md`](06_WS_PARAMSET_VALIDATOR_SPEC.md)
7. [`12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md`](12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md)
8. [`13_WS_CONTRACT_TEST_CHECKLIST.md`](13_WS_CONTRACT_TEST_CHECKLIST.md)
9. [`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)

## Folder Pendukung
- Artefak DB/seed WS: [`db/README.md`](db/README.md)
- Referensi/fixture WS (non-normatif): [`_refs/README.md`](_refs/README.md)

## Status
Dokumen bernomor di folder ini adalah kontrak (normatif) kecuali yang jelas diberi label `_refs` atau `reference`.
