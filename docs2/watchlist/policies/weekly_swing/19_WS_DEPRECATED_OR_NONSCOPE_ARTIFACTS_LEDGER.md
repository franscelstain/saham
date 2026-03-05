# 19 — WS Deprecated / Non-scope Artifacts Ledger (LOCKED)

## Purpose
Mencatat artefak, istilah, atau nama objek yang pernah muncul pada diskusi/versi lama tetapi **bukan** artefak resmi Weekly Swing dalam paket dokumen final ini.

Ledger ini mencegah artefak lama ikut terbawa seolah-olah masih aktif.

## Scope
Dokumen ini berlaku untuk:
- nama tabel lama,
- export lama,
- istilah artefak yang tidak masuk manifest resmi,
- dan artefak diskusi yang tidak punya schema/kontrak final.

## Inputs
- artefak yang pernah disebut pada versi lama,
- hasil audit referensi silang,
- manifest artefak resmi WS.

## Outputs
- daftar artefak non-scope/deprecated,
- status default artefak yang tidak ada di manifest,
- rule penggunaan ulang artefak lama.

## Prerequisites
- [`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)

## 1) Default Rule (LOCKED)
Artefak yang tidak tercantum pada manifest resmi file 18 memiliki status default:
- `NON_SCOPE`

Status default ini berlaku sampai artefak tersebut:
- ditambahkan resmi ke manifest,
- diberi schema/kontrak,
- dan dijelaskan flow pembentukan + konsumennya.

## 2) Official Non-scope / Deprecated Items

### A) `watchlist_bt_dataset_ws`
- status: `NON_SCOPE`
- alasan: tidak ada schema resmi final pada paket dokumen ini dan tidak dibutuhkan oleh flow calibration/final proof yang dikunci saat ini.

### B) `coverage_matrix_export.json`
- status: `NON_SCOPE` sebagai artefak produksi resmi
- alasan: coverage matrix di-govern oleh dokumen kontrak dan fixture, bukan oleh export file produksi baku.

### C) Nama artefak lain yang tidak ada di manifest file 18
- status default: `NON_SCOPE`
- alasan: belum punya izin resmi untuk dipakai sebagai artefak WS final.

## 3) Re-activation Rule (LOCKED)
Artefak non-scope hanya boleh dipakai kembali jika semua syarat berikut dipenuhi:
1. ditambahkan ke manifest resmi ([`18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md`](18_WS_BACKTEST_ARTIFACT_MANIFEST_LOCKED.md)),
2. punya schema/kontrak yang sah,
3. dijelaskan bagaimana artefak itu dibentuk,
4. dijelaskan siapa konsumennya,
5. dan semua referensi lama yang ambigu diperbarui.

Jika syarat ini belum lengkap, artefak tersebut tetap non-scope.

## 4) Parity Failure Rule (LOCKED)
Jika sebuah prosedur, evidence rule, promote rule, atau worked example menyebut artefak non-scope seolah-olah wajib/resmi, maka kondisi itu dianggap:
- `ARTIFACT_REFERENCE_VIOLATION`

## 5) Relationship with Manifest
- Manifest (file 18) adalah allowlist resmi.
- Ledger ini adalah daftar penolakan/default non-scope.
- Sebuah artefak tidak boleh berada di dua status sekaligus.

## Next
- [`20_WS_CANONICAL_PARAMSET_PROCEDURES.md`](20_WS_CANONICAL_PARAMSET_PROCEDURES.md)
