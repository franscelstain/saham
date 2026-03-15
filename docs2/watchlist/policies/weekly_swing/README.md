# Weekly Swing (WS) — Index

> **Status:** LOCKED (Normative)
> **Doc Role:** Weekly Swing strategy entry

## Purpose

Dokumen ini adalah entry point strategy Weekly Swing. Dokumen ini memimpin jalur baca strategy, memetakan file normatif utama, dan menjelaskan batas antara kontrak inti dan artefak pendukung.

## Scope

Dokumen pada folder ini adalah kontrak normatif Weekly Swing untuk:

- execution canonical PLAN dan CONFIRM,
- data model dan runtime output shape,
- paramset contract dan validator,
- PLAN algorithm dan deterministic selection,
- CONFIRM overlay dan snapshot-related behavior,
- contract-test acceptance,
- serta governance backtest, evidence, dan procedures yang relevan.

## Core Reading Order

Untuk implementasi inti Weekly Swing, baca dokumen bernomor berikut secara berurutan:

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
11. [`11_WS_INTRADAY_SNAPSHOT_TABLES.md`](11_WS_INTRADAY_SNAPSHOT_TABLES.md)
12. [`13_WS_CONTRACT_TEST_CHECKLIST.md`](13_WS_CONTRACT_TEST_CHECKLIST.md)

Dokumen berikut dibaca bila perubahan atau implementasi menyentuh area backtest, evidence, manifest, deprecated artifacts, atau canonical procedures:

- [`12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md`](12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md)
- [`14_WS_BT_COVERAGE_MATRIX_LOCKED.md`](14_WS_BT_COVERAGE_MATRIX_LOCKED.md)
- [`15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md)
- [`16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md`](16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md)
- [`17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`](17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md)
- [`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)
- [`19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md`](19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md)
- [`20_WS_CANONICAL_PARAMSET_PROCEDURES.md`](20_WS_CANONICAL_PARAMSET_PROCEDURES.md)

## Supporting Folders

- Artefak DB / schema / SQL: [`db/README.md`](db/README.md)
- Referensi penjelas: [`_refs/README.md`](_refs/README.md)
- Contoh runtime output: [`examples/README.md`](examples/README.md)
- Golden test assets: [`fixtures/README.md`](fixtures/README.md)

## Ownership Boundaries

Di dalam domain Weekly Swing, file bernomor adalah rumah utama aturan wajib.

- `_refs/` hanya untuk referensi penjelas, glossary, template, worked example, dan matriks bantu baca.
- `examples/` hanya untuk contoh output yang tunduk pada kontrak normatif.
- `fixtures/` hanya untuk data uji, determinism, dan validation replay.
- `db/` hanya untuk persistence dan implementation artifacts.

Folder pendukung tidak boleh menjadi sumber aturan baru.

## Relationship to Shared Policy

Weekly Swing menggunakan baseline shared yang relevan dari `../_shared/`. Namun, aturan yang hanya berlaku untuk Weekly Swing tetap dimiliki oleh file normatif bernomor pada folder ini.

## Final Rule

Jika terdapat perbedaan antara README ini dan dokumen owner normatif Weekly Swing yang lebih rinci, dokumen owner normatif selalu menang.
