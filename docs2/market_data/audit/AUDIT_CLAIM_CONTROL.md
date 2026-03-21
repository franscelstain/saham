# Audit Claim Control

## Claim types
### Baseline-ready
Paket cukup kuat dipakai sebagai baseline desain atau review domain.

### Implementation-ready
Paket cukup jelas dipakai untuk implementasi tanpa banyak menebak.

### Runtime-proven
Paket didukung oleh bukti runtime nyata yang cukup kuat dan traceable.

## Required support per claim
- baseline-ready: strong Layer A
- implementation-ready: strong A+B
- runtime-proven: actual Layer C evidence

## Runtime-proven interpretation rule
Claim `runtime-proven` harus selalu dibaca dalam konteks kekuatan evidence yang benar-benar ada.
Audit wajib menyatakan apakah Layer C didukung oleh:
- archived executed evidence only
- runtime payload/log surface
- real application code and runtime surface

Bila paket hanya memiliki archived executed evidence bundles tanpa code aplikasi nyata atau runtime surface aplikasi yang lengkap, maka klaim yang benar adalah:
- runtime-proven **for the documented execution artifacts that are present**, bukan
- full real-implementation package.

Kondisi ini harus diperlakukan sebagai **claim boundary yang sah**, bukan defect, selama paket tetap jujur mengenai jenis evidence yang dimilikinya. Jadi perbedaan antara archived evidence dan full application/runtime surface adalah pembatas klasifikasi, bukan otomatis kelemahan kualitas.

Kondisi ini harus diperlakukan sebagai **claim boundary yang sah**, bukan defect, selama paket tetap jujur mengenai jenis evidence yang dimilikinya. Jadi perbedaan antara archived evidence dan full application/runtime surface adalah pembatas klasifikasi, bukan otomatis kelemahan kualitas.

## Overclaim rule
Dokumen tidak boleh mengklaim runtime-proven bila yang ada baru contract, runbook, atau illustrative example.
Dokumen juga tidak boleh menyamakan:
- executed archived evidence
- real application implementation completeness

## Downgrade rule
Audit wajib menurunkan klaim bila evidence tidak cukup.
Contoh downgrade yang wajib dilakukan:
- dari `runtime-proven` menjadi `implementation-ready` bila evidence hanya template/contoh
- dari `full real implementation` menjadi `runtime-proven via archived evidence` bila bukti nyata hanya berupa executed evidence bundles
