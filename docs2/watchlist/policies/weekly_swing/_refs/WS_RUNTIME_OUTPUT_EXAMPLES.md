# Weekly Swing Runtime Output Examples

## Purpose

Dokumen ini berisi contoh bentuk runtime output untuk membantu pembacaan kontrak normatif Weekly Swing. Dokumen ini tidak menjadi owner final terhadap shape, invariant, atau acceptance behavior output.

## Reading Rule

Setiap contoh pada dokumen ini harus dibaca sebagai ilustrasi terhadap kontrak normatif yang sudah ditetapkan di dokumen owner yang relevan.

Jika terdapat perbedaan antara contoh pada dokumen ini dan dokumen owner normatif, maka dokumen owner normatif selalu menang dan contoh harus diperbarui.

## PLAN Output Examples

Contoh PLAN output pada dokumen ini dimaksudkan untuk membantu pembaca memahami representasi hasil yang mengikuti kontrak pada:

- data model strategy,
- plan algorithm,
- dan contract-test checklist.

Contoh PLAN output tidak menetapkan field wajib atau behavior baru yang belum hidup di dokumen owner tersebut.

## CONFIRM Output Examples

Contoh CONFIRM output pada dokumen ini hanya menjelaskan representasi hasil confirm overlay yang mengikuti kontrak normatif strategy.

Dokumen ini tidak menetapkan aturan baru mengenai:

- overlay behavior,
- snapshot semantics,
- output acceptance,
- atau relationship dengan upstream data contract.

## PLAN / CONFIRM Pair Examples

Pair examples pada dokumen ini digunakan untuk membantu pembaca melihat keterhubungan antara output PLAN dan output CONFIRM.

Keterikatan kontraktual pair tersebut tetap dimiliki oleh dokumen owner yang relevan, terutama execution canonical, confirm overlay, dan contract-test checklist.

## Maintenance Rule

Jika sebuah field, shape, atau invariant hanya tampak pada contoh di dokumen ini tetapi tidak dapat ditelusuri ke dokumen owner normatif, maka substansinya harus dipindahkan atau ditegaskan pada dokumen owner yang benar.

## Owner Mapping Reminder

Untuk menilai apakah contoh output valid, pembaca wajib memeriksa owner berikut:
- runtime field dan persistence shape → `03_WS_DATA_MODEL_MARIADB.md`
- PLAN branch behavior → `08_WS_PLAN_ALGORITHM.md`
- deterministic selection / grouping → `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
- CONFIRM label behavior → `10_WS_CONFIRM_OVERLAY.md`
- acceptance akhir → `13_WS_CONTRACT_TEST_CHECKLIST.md`

Contoh pada dokumen ini tidak boleh dipakai untuk menambah field, reason, label, atau branch baru yang belum hidup pada owner tersebut.

