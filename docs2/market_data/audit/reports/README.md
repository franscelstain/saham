# Audit Reports README

Folder ini dipakai untuk menyimpan **output audit aktif** terhadap paket market-data yang sedang dibaca.

## Report state model
Audit report di paket aktif harus **state-only**, bukan **round-based** dan bukan **history-driven**.

Artinya:
- report audit harus menjelaskan **state paket saat ini**
- report audit tidak boleh dijadikan log revisi berantai (`R1`, `R2`, `R3`, dst.)
- report audit tidak boleh memaksa pembaca menyusun status akhir dari kumpulan report lama
- paket audit aktif tidak menyimpan history audit yang sudah lewat bila history itu tidak lagi dibutuhkan untuk menjalankan audit saat ini

## Canonical current-state rule
Untuk paket aktif, harus ada tepat satu report kanonik:
- `AUDIT_FINAL_STATE.md`

File itu adalah satu-satunya ringkasan status audit saat ini.

## What is allowed in this folder
Di paket aktif, isi folder ini harus seminimal mungkin:
- `AUDIT_FINAL_STATE.md`
- remediation report aktif **hanya jika** masih ada temuan PARTIAL / FAIL yang belum ditutup

## What is not allowed in the active package
Yang tidak boleh dijadikan pola di paket aktif:
- `AUDIT_FINAL_STATE_R1.md`
- `AUDIT_FINAL_STATE_R2.md`
- `AUDIT_FINAL_STATE_R3.md`
- `AUDIT_REMEDIATION_R1_*.md`
- folder `history/` untuk menyimpan jejak audit lama
- pointer README yang menunjuk file final-state berbasis revisi

## Minimum report contents
### Final-state report
- package identity
- dominant layer
- actual layer classification
- whether Layer C is genuinely active
- summary table PASS / PARTIAL / FAIL
- final verdict
- outstanding partial/fail items, if any

### Active remediation report
- exact files/folders changed
- issue still open
- target state
- concrete remediation action
- sync requirements

## Operational rule
Kalau semua PARTIAL / FAIL sudah selesai, remediation report aktif harus dihapus dan folder ini kembali menyisakan satu file kanonik saja: `AUDIT_FINAL_STATE.md`.
