# 19 — WS Deprecated / Non-scope Artifacts Ledger (LOCKED)

## Purpose

Dokumen ini adalah ledger untuk artefak Weekly Swing yang berstatus deprecated, non-scope, atau historical reference dan tidak berada dalam allowlist artefak resmi strategy.

Ledger ini mencegah artefak lama, istilah lama, atau objek diskusi lama ikut terbawa seolah-olah masih aktif dalam paket dokumen final Weekly Swing.

## Scope

Dokumen ini berlaku untuk:

- nama tabel lama,
- export lama,
- istilah artefak yang tidak masuk manifest resmi,
- dan artefak diskusi yang tidak memiliki schema atau kontrak final yang aktif.

## Prerequisite

- [`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)

## Default Rule (LOCKED)

Ketiadaan nama artefak pada manifest resmi file 18 cukup untuk menjadikannya `NON_SCOPE`, tanpa keputusan tambahan.

Artefak yang tidak tercantum pada manifest resmi memiliki status default:

- `NON_SCOPE`

Status default ini tetap berlaku sampai artefak tersebut:

1. ditambahkan resmi ke manifest,
2. memiliki schema atau kontrak yang sah,
3. memiliki flow pembentukan yang jelas,
4. dan memiliki consumer yang jelas.

## Item Format

Setiap item pada ledger ini harus dibaca dengan format berikut:

- **Artifact**
- **Status**
- **Reason**
- **Replacement Owner (if any)**

## Official Deprecated / Non-scope Items

### Artifact
`watchlist_bt_dataset_ws`

**Status**  
`NON_SCOPE`

**Reason**  
Tidak ada schema resmi final pada paket dokumen ini dan tidak dibutuhkan oleh flow calibration atau final proof yang dikunci saat ini.

**Replacement Owner (if any)**  
Tidak ada.

---

### Artifact
`coverage_matrix_export.json`

**Status**  
`NON_SCOPE` sebagai artefak produksi resmi

**Reason**  
Coverage matrix di-govern oleh dokumen kontrak dan fixtures, bukan oleh export file produksi baku.

**Replacement Owner (if any)**  
Dokumen coverage dan artefak normatif yang relevan.

---

### Artifact
Nama artefak lain yang tidak ada di manifest file 18

**Status**  
`NON_SCOPE` (default)

**Reason**  
Belum memiliki izin resmi untuk dipakai sebagai artefak final Weekly Swing.

**Replacement Owner (if any)**  
Belum ada sampai artefak tersebut masuk manifest resmi dan memiliki owner normatif yang jelas.

## Re-activation Rule (LOCKED)

Re-activation tidak boleh dilakukan hanya dengan menambahkan referensi silang. Artefak hanya boleh dipakai kembali jika seluruh syarat berikut terpenuhi:

1. ditambahkan ke manifest resmi ([`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)),
2. memiliki schema atau kontrak yang sah,
3. dijelaskan bagaimana artefak dibentuk,
4. dijelaskan siapa konsumennya,
5. dan semua referensi lama yang ambigu telah diperbarui.

Jika syarat ini belum lengkap, artefak tersebut tetap non-scope.

## Parity Failure Rule (LOCKED)

Jika sebuah procedure, evidence rule, promote rule, worked example, atau dokumen lain menyebut artefak non-scope seolah-olah wajib atau resmi, kondisi tersebut dianggap:

- `ARTIFACT_REFERENCE_VIOLATION`

## Relationship with Manifest

Jika terjadi konflik antara ledger ini dan manifest, manifest resmi adalah allowlist yang menang untuk status aktif artefak.

- Manifest (file 18) adalah allowlist resmi.
- Ledger ini adalah daftar penolakan / default non-scope.
- Sebuah artefak tidak boleh berada di dua status sekaligus.

## Final Rule

Artefak yang berada pada ledger ini tidak boleh diperlakukan sebagai artefak aktif Weekly Swing sampai statusnya diubah secara normatif melalui manifest resmi dan owner kontrak yang relevan.

## Next

- [`20_WS_CANONICAL_PARAMSET_PROCEDURES.md`](20_WS_CANONICAL_PARAMSET_PROCEDURES.md)
