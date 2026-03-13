# Fixtures — Weekly Swing

> **Status:** REFERENCE
> **Doc Role:** Fixture inventory and usage guide

## Purpose
Menjelaskan fungsi folder fixture Weekly Swing dan cara memakainya sebagai pendukung contract test, bukan sebagai owner kontrak.

## Scope
Dokumen ini hanya menginventaris fixture resmi yang disimpan pada folder ini dan memberi panduan penggunaan praktis.
Aturan perilaku sistem, acceptance rule, dan PASS/FAIL utama tetap berada pada dokumen normatif bernomor.

## Inputs
- Engineer test, reviewer, auditor, dan pembaca yang perlu melihat fixture resmi Weekly Swing.

## Outputs
- Peta fixture yang tersedia.
- Penjelasan singkat per keluarga fixture.
- Batas yang jelas bahwa fixture tunduk pada kontrak normatif.

## Normative owner
Kontrak yang diuji oleh fixture di folder ini tetap dikunci oleh dokumen bernomor, terutama:
- [`../13_WS_CONTRACT_TEST_CHECKLIST.md`](../13_WS_CONTRACT_TEST_CHECKLIST.md)
- [`../02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`](../02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md)
- [`../06_WS_PARAMSET_VALIDATOR_SPEC.md`](../06_WS_PARAMSET_VALIDATOR_SPEC.md)
- [`../07_WS_REASON_CODES_AND_HASH.md`](../07_WS_REASON_CODES_AND_HASH.md)
- [`../08_WS_PLAN_ALGORITHM.md`](../08_WS_PLAN_ALGORITHM.md)
- [`../09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`](../09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md)
- [`../10_WS_CONFIRM_OVERLAY.md`](../10_WS_CONFIRM_OVERLAY.md)
- [`../14_WS_BT_COVERAGE_MATRIX_LOCKED.md`](../14_WS_BT_COVERAGE_MATRIX_LOCKED.md)
- [`../15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](../15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md)
- [`../16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md`](../16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md)
- [`../17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`](../17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md)
- [`../18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](../18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)

Referensi ringkas inventori fixture tersedia di:
- [`../_refs/WS_FIXTURE_INVENTORY.md`](../_refs/WS_FIXTURE_INVENTORY.md)

Jika isi fixture berbeda dengan dokumen normatif yang relevan, maka dokumen normatif yang berlaku dan fixture harus diperbaiki.

## Kategori fixture
- **Validator fixtures** — required keys, enum, tipe data, audit fields, unknown keys, dan hash contract.
- **PLAN / selection fixtures** — tie handling, no-trade, forced watch-only, guard fail, quantile cutoff, dan snapshot universe.
- **CONFIRM fixtures** — snapshot overlay, field orderbook non-contract, dan immutability PLAN.
- **Backtest / evidence fixtures** — coverage, eval metrics, OOS proof, artifact reference guard, dan universe equivalence.
- **Hash / canonical fixtures** — canonicalization dan kestabilan hash.

## Cara pakai
- Gunakan fixture di folder ini untuk unit test, contract test, integration test, atau replay validation.
- Jangan menjadikan fixture sebagai tempat mendefinisikan aturan baru.
- Bila fixture dicerminkan ke folder test lain, salinannya harus tetap identik terhadap file di folder ini.

## Review minimum
Sebelum fixture dianggap layak dipakai, cek bahwa:
- fungsi fixture jelas,
- isi fixture cocok dengan dokumen normatif yang diuji,
- status valid/invalid memang disengaja,
- file tidak duplikatif tanpa peran yang jelas,
- perubahan fixture bisa dijelaskan dengan dasar kontrak yang spesifik.

## Catatan drift / strictness
- [`confirm_payload_with_unknown_top_level_field.json`](confirm_payload_with_unknown_top_level_field.json) adalah fixture strictness untuk memastikan drift top-level tidak diam-diam dinormalisasi oleh implementasi.
