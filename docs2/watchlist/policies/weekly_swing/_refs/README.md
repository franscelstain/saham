# Weekly Swing References

## Purpose

Folder `_refs/` berisi dokumen referensial yang membantu pembacaan kontrak strategy Weekly Swing.

## Hard Warning

Folder `_refs/` bukan owner normatif. Dokumen di folder ini tidak boleh dijadikan sumber tunggal untuk:
- acceptance,
- invariants,
- business rules,
- output shape,
- atau branch behavior implementasi.

## Scope

Dokumen di folder ini dapat berupa:
- ringkasan,
- elaborasi,
- glossary,
- template,
- worked examples,
- atau matriks bantu pembacaan.

## Ownership Rule

Folder `_refs/` tidak menetapkan aturan wajib baru. Jika terjadi konflik, file normatif bernomor Weekly Swing selalu menang.

## Reading Guidance

Dokumen referensial harus dipakai untuk membantu pemahaman, bukan untuk menetapkan acceptance, invariants, atau business rules yang belum hidup di dokumen owner normatif.

## Maintenance Rule

Jika suatu rule penting hanya hidup di `_refs/`, substansinya harus dipindahkan atau ditegaskan pada dokumen normatif yang menjadi owner topiknya.

## Reviewer Reminder

Reviewer wajib menolak perubahan yang hanya menambah atau mengubah `_refs/` tetapi tidak menyentuh owner normatif ketika perubahan tersebut sebenarnya mengubah contract, branch behavior, output shape, atau acceptance semantics.

