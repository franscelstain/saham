# Policy Governance — Watchlist

## Purpose
Dokumen ini mengunci governance tingkat root untuk seluruh paket [`docs/watchlist/`](./README.md).
Fungsi utamanya adalah memastikan pembaca, implementer, reviewer, auditor, dan tooling memahami:
- dokumen mana yang normatif,
- dokumen mana yang referensial,
- urutan baca yang benar,
- dan aturan bahwa dokumentasi watchlist adalah **kontrak kerja**, bukan tempat spekulasi.

## Scope
Dokumen ini berlaku untuk seluruh struktur di bawah [`docs/watchlist/`](./README.md), termasuk:
- [`db/`](db/README.md)
- [`policies/_shared/`](policies/_shared/README.md)
- `policies/<policy_code>/`

Dokumen ini tidak menggantikan kontrak teknis yang lebih spesifik pada dokumen policy masing-masing.
Jika terjadi konflik, maka prioritasnya adalah:
1. dokumen policy LOCKED yang paling spesifik,
2. dokumen `_shared` LOCKED,
3. dokumen governance root ini.

## Inputs
- Struktur folder watchlist.
- Kebutuhan governance lintas policy.
- Prinsip auditability, determinism, anti-drift, dan PLAN/CONFIRM separation.

## Outputs
- Aturan baca dokumen.
- Aturan klasifikasi dokumen.
- Aturan pembedaan source of truth vs reference.
- North star implementasi watchlist berbasis trading.

## Root Folder
Folder utama watchlist adalah:
- [`docs/watchlist/`](./README.md)

Dokumentasi katalog dan struktur policy ada di:
- [`README.md`](README.md)

## Document Classes (LOCKED)
Semua dokumen watchlist wajib dipahami menurut kelas berikut:

### 1) Normative / Contract Documents
Dokumen dianggap normatif bila **berada pada jalur normatif resmi** dan perannya memang kontraktual, misalnya dokumen bernomor policy, dokumen `_shared`, dokumen schema/DDL resmi, atau dokumen lain yang secara eksplisit dinyatakan LOCKED/contractual di folder normatifnya.
Dokumen jenis ini adalah source of truth perilaku sistem atau struktur artefak.

### 2) Reference Documents
Dokumen di `_refs/` selalu bersifat referensial, walaupun nama file mengandung kata seperti `LOCKED`, `CONTRACT`, `CANONICAL`, atau `SCHEMA`.
Dokumen ini membantu implementasi, audit, contoh runtime, glossary, atau worked example.
Dokumen referensi **tidak boleh** membatalkan kontrak normatif.
Jika ada mismatch, dokumen normatif menang.

### 3) Examples and Fixtures
File di `examples/` dan `fixtures/` adalah artefak pengujian / pembuktian.
Mereka membantu membumikan kontrak, tetapi **tidak menjadi sumber aturan baru**.
Jika fixture atau example bertentangan dengan dokumen normatif, maka fixture/example harus diperbaiki.

### 4) DB and Seed Artifacts
DDL, seed, dan schema doc adalah source of truth struktur data.
Pemisahan DDL vs seed bersifat wajib.
Referensinya ada pada:
- [`db/02_DB_SCHEMA_MARIADB.md`](db/02_DB_SCHEMA_MARIADB.md)
- [`db/05_DB_DDL_MARIADB.sql`](db/05_DB_DDL_MARIADB.sql)
- [`db/04_DB_SEED_GLOBAL.sql`](db/04_DB_SEED_GLOBAL.sql)

## Reading Order (LOCKED)
Penomoran file memakai prefix dua digit dan **harus dibaca berurutan**.
Tidak boleh membaca file tengah/lanjut tanpa konteks file sebelumnya jika file-file itu berada dalam satu ladder policy.

### A) Shared framework (wajib dibaca lebih dulu)
1. [`policies/_shared/01_POLICY_FRAMEWORK_OVERVIEW.md`](policies/_shared/01_POLICY_FRAMEWORK_OVERVIEW.md)
2. [`policies/_shared/02_PARAMSET_CONTRACT_GLOBAL.md`](policies/_shared/02_PARAMSET_CONTRACT_GLOBAL.md)
3. [`policies/_shared/03_VALIDATOR_SPEC_GLOBAL.md`](policies/_shared/03_VALIDATOR_SPEC_GLOBAL.md)
4. [`policies/_shared/04_CONTRACT_TESTS_GLOBAL.md`](policies/_shared/04_CONTRACT_TESTS_GLOBAL.md)
5. [`policies/_shared/05_EXECUTION_CANONICAL_GLOBAL.md`](policies/_shared/05_EXECUTION_CANONICAL_GLOBAL.md)
6. [`policies/_shared/06_SCHEMA_PARITY_RULES.md`](policies/_shared/06_SCHEMA_PARITY_RULES.md)
7. [`policies/_shared/07_CONTRACT_FAILURE_CODES_LOCKED.md`](policies/_shared/07_CONTRACT_FAILURE_CODES_LOCKED.md)

### B) Weekly Swing policy ladder
1. [`policies/weekly_swing/01_WS_OVERVIEW.md`](policies/weekly_swing/01_WS_OVERVIEW.md)
2. [`policies/weekly_swing/02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`](policies/weekly_swing/02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md)
3. [`policies/weekly_swing/03_WS_DATA_MODEL_MARIADB.md`](policies/weekly_swing/03_WS_DATA_MODEL_MARIADB.md)
4. [`policies/weekly_swing/04_WS_PARAMSET_JSON_CONTRACT.md`](policies/weekly_swing/04_WS_PARAMSET_JSON_CONTRACT.md)
5. [`policies/weekly_swing/05_WS_PARAMETER_REGISTRY_COMPLETE.md`](policies/weekly_swing/05_WS_PARAMETER_REGISTRY_COMPLETE.md)
6. [`policies/weekly_swing/06_WS_PARAMSET_VALIDATOR_SPEC.md`](policies/weekly_swing/06_WS_PARAMSET_VALIDATOR_SPEC.md)
7. [`policies/weekly_swing/07_WS_REASON_CODES_AND_HASH.md`](policies/weekly_swing/07_WS_REASON_CODES_AND_HASH.md)
8. [`policies/weekly_swing/08_WS_PLAN_ALGORITHM.md`](policies/weekly_swing/08_WS_PLAN_ALGORITHM.md)
9. [`policies/weekly_swing/09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`](policies/weekly_swing/09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md)
10. [`policies/weekly_swing/10_WS_CONFIRM_OVERLAY.md`](policies/weekly_swing/10_WS_CONFIRM_OVERLAY.md)
11. [`policies/weekly_swing/11_WS_INTRADAY_SNAPSHOT_TABLES.md`](policies/weekly_swing/11_WS_INTRADAY_SNAPSHOT_TABLES.md)
12. [`policies/weekly_swing/12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md`](policies/weekly_swing/12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md)
13. [`policies/weekly_swing/13_WS_CONTRACT_TEST_CHECKLIST.md`](policies/weekly_swing/13_WS_CONTRACT_TEST_CHECKLIST.md)
14. [`policies/weekly_swing/14_WS_BT_COVERAGE_MATRIX_LOCKED.md`](policies/weekly_swing/14_WS_BT_COVERAGE_MATRIX_LOCKED.md)
15. [`policies/weekly_swing/15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](policies/weekly_swing/15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md)
16. [`policies/weekly_swing/16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md`](policies/weekly_swing/16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md)
17. [`policies/weekly_swing/17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`](policies/weekly_swing/17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md)
18. [`policies/weekly_swing/18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](policies/weekly_swing/18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)
19. [`policies/weekly_swing/19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md`](policies/weekly_swing/19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md)
20. [`policies/weekly_swing/20_WS_CANONICAL_PARAMSET_PROCEDURES.md`](policies/weekly_swing/20_WS_CANONICAL_PARAMSET_PROCEDURES.md)

## Document = Work (LOCKED)
Dokumen di [`docs/watchlist/`](./README.md) adalah kontrak kerja.
Artinya:
- hanya hal yang benar-benar akan diimplementasikan yang boleh ditulis,
- tidak boleh ada opsi A/B yang belum diputuskan,
- tidak boleh ada nama tabel/file/artefak yang tidak eksis atau tidak akan dipakai,
- tidak boleh ada wording yang membuka tafsir “nanti dipilih salah satu”.

Jika sebuah ide belum diputuskan:
- simpan di ticket / note / diskusi di luar [`docs/watchlist/`](./README.md),
- jangan masukkan ke dokumen kontrak.

## Mandatory Design Principles (LOCKED)
Setiap policy watchlist wajib mengikuti prinsip berikut:

1. **Explicit Parameters**
   Semua parameter yang mempengaruhi output PLAN atau CONFIRM wajib eksplisit.
   Tidak boleh ada parameter runtime tersirat.

2. **Parameter Provenance**
   Setiap parameter wajib punya asal-usul yang jelas:
   - `BT`: hasil kalibrasi backtest yang tervalidasi,
   - `DET`: aturan deterministik berbasis prinsip pasar yang stabil,
   - `MAN`: override/pengaturan manual yang terdokumentasi.

3. **Deterministic Output**
   Output PLAN/CONFIRM harus deterministic untuk input dan paramset yang sama.

4. **Auditability**
   Jejak keputusan harus bisa diaudit.
   Reason code, fail code, hash, cutoff, dan provenance parameter harus dapat dijelaskan ulang.

5. **No Forced Advice Under Bad Data**
   Sistem tidak boleh memaksakan rekomendasi saat data kurang, stale, invalid, atau tidak cukup bukti.

6. **PLAN/CONFIRM Separation**
   CONFIRM adalah overlay terpisah.
   CONFIRM tidak boleh mengubah ranking, skor, group, atau hash PLAN yang sudah canonical.

## Cross-Document Rule (LOCKED)
Jika satu dokumen menyebut:
- artefak,
- tabel,
- fixture,
- export schema,
- reason code,
- atau flow resmi,

maka entitas tersebut wajib:
- ada secara fisik bila berupa file,
- ada di manifest/ledger bila berupa artefak resmi,
- ada di schema/DDL bila berupa tabel kolom kontraktual,
- dan konsisten namanya di seluruh dokumen.

Jika tidak, kondisi itu dianggap **documentation parity failure**.


## Link Integrity (LOCKED)
Referensi integritas link dan konvensi path ada di:
- [`00_LINK_INTEGRITY_CHECK_LOCKED.md`](00_LINK_INTEGRITY_CHECK_LOCKED.md)

## Next
Mulai dari:
- [`policies/_shared/01_POLICY_FRAMEWORK_OVERVIEW.md`](policies/_shared/01_POLICY_FRAMEWORK_OVERVIEW.md)

Lalu lanjut berurutan sesuai ladder di atas.
