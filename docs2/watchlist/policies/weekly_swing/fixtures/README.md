# Weekly Swing Fixtures

## Purpose

Folder `fixtures/` berisi golden test assets untuk validasi determinism, contract behavior, dan acceptance pada strategy Weekly Swing.

## Hard Warning

Fixture adalah alat uji, bukan owner behavior. Fixture tidak boleh dipakai untuk:
- mendefinisikan kontrak baru,
- menambah field runtime baru,
- atau menetapkan semantik yang belum hidup di owner normatif.

## Scope

Fixtures pada folder ini digunakan untuk:
- mendukung contract-test verification,
- membuktikan positive dan negative cases,
- membantu menjaga determinism,
- dan menyediakan artefak uji terhadap behavior yang telah ditetapkan secara normatif.

## Ownership Rule

Fixtures tunduk pada dokumen normatif Weekly Swing dan tidak menjadi owner definisi kontrak. Validitas fixture hanya dapat dipahami dengan merujuk ke owner normatif yang relevan.

## Reading Guidance

Fixtures harus dibaca bersama:
- `13_WS_CONTRACT_TEST_CHECKLIST.md`,
- dan dokumen owner lain yang menetapkan rule yang sedang diuji.

## Maintenance Rule

Jika suatu fixture menampilkan rule, field, atau behavior yang tidak dapat ditelusuri ke dokumen normatif, maka fixture tersebut harus diperbaiki atau ownership normatifnya harus ditegaskan terlebih dahulu. Fixture tidak boleh menjadi satu-satunya tempat rule hidup.

## Reviewer Reminder

Perubahan fixture harus dilihat sebagai konsekuensi dari contract yang sudah dikunci, bukan sebagai tempat memperkenalkan perilaku baru. Jika fixture tampak memerlukan rule baru, rule itu harus hidup pada owner normatif sebelum fixture dianggap valid.

