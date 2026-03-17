# Weekly Swing Failure Behavior Matrix

## Purpose

Dokumen ini merangkum perilaku kegagalan yang telah ditetapkan oleh dokumen normatif terkait. Dokumen ini bersifat referensial dan tidak menetapkan failure rule baru.

## Reading Rule

Matriks pada dokumen ini harus dibaca bersama dokumen owner yang relevan, terutama:

- validator specification,
- contract-test checklist,
- dan failure code contracts yang berlaku.

Jika terdapat perbedaan antara dokumen ini dan dokumen owner normatif, dokumen owner normatif selalu menang.

## Scope of This Matrix

Dokumen ini hanya berfungsi untuk:

- memetakan kategori kegagalan,
- merangkum expected behavior yang sudah ditetapkan di tempat lain,
- membantu pembaca melihat keterhubungan antara rule, failure condition, dan outcome.

Dokumen ini tidak boleh dipakai sebagai satu-satunya dasar untuk menetapkan acceptance rule atau valid/invalid state.

## Matrix Usage Guidance

Setiap baris dalam matriks ini harus dipahami sebagai ringkasan terhadap rule yang sudah ditetapkan secara authoritative oleh dokumen owner.

Gunakan pola interpretasi berikut untuk setiap baris:

- kondisi kegagalan dirujukkan ke dokumen owner yang menetapkan rule,
- expected behavior dirujukkan ke dokumen owner yang menetapkan acceptance,
- failure code dirujukkan ke dokumen owner yang menetapkan semantiknya.

## Maintenance Rule

Jika sebuah perilaku kegagalan muncul di matriks ini tetapi tidak dapat ditelusuri ke dokumen owner normatif, maka substansi rule tersebut harus dipindahkan atau ditegaskan pada dokumen owner yang benar. Dokumen ini tidak boleh menjadi satu-satunya tempat rule tersebut hidup.
