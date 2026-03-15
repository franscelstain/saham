# Weekly Swing Examples

## Purpose

Folder `examples/` berisi contoh bentuk output atau representasi runtime yang tunduk pada kontrak normatif Weekly Swing.

## Hard Warning

Examples tidak boleh dipakai sebagai owner untuk:
- shape kontraktual baru,
- label / reason baru,
- branch behavior baru,
- atau field runtime baru.

Jika contoh tampak lebih konkret daripada kontrak, kontrak normatif tetap menang.

## Scope

Examples pada folder ini digunakan untuk:
- membantu pembaca memahami representasi hasil,
- mengilustrasikan output PLAN atau CONFIRM,
- dan menunjukkan contoh pair relationship atau branch behavior yang sudah dikunci oleh dokumen normatif.

## Ownership Rule

Examples tidak menjadi owner aturan final. Rules mengenai shape, invariant, acceptance, atau behavior tetap dimiliki oleh dokumen normatif Weekly Swing yang relevan.

## Reading Guidance

Jika pembaca mencari aturan final, pembaca harus merujuk ke dokumen owner yang relevan, seperti:
- execution canonical,
- data model,
- plan algorithm,
- confirm overlay,
- contract-test checklist,
- dan implementation blueprint.

## Maintenance Rule

Jika sebuah example tampak berbeda dari owner normatifnya, owner normatif selalu menang dan example harus diperbarui. Example tidak boleh dipakai untuk memperkenalkan rule baru yang belum hidup di dokumen normatif.

## Reviewer Reminder

Perubahan pada `examples/` tidak cukup untuk dianggap perubahan kontrak. Jika shape atau behavior berubah, owner normatif dan contract-test anchor harus ikut diperbarui terlebih dahulu.

