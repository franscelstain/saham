# Weekly Swing DB Artifacts

## Purpose

Folder `db/` berisi artefak persistence dan implementasi database yang mendukung strategy Weekly Swing.

## Hard Warning

SQL artifacts pada folder ini tidak menjadi owner utama atas business-rule semantics. DDL, seed, dan procedure di sini boleh merealisasikan kontrak, tetapi tidak boleh diam-diam menggantikan kontrak normatif strategy.

## Scope

Artefak pada folder ini dapat mencakup:
- schema implementation artifacts,
- promotion procedures,
- seed files,
- dan artefak persistence lain yang merealisasikan kebutuhan Weekly Swing.

## Ownership Rule

Aturan bisnis, kontrak normatif, dan semantik strategy tetap dimiliki oleh dokumen policy Weekly Swing yang relevan. SQL artifacts pada folder ini merealisasikan kontrak tersebut dan tidak menjadi owner utama atas business-rule semantics.

## Reading Guidance

Pembaca harus merujuk kembali ke dokumen normatif yang relevan, termasuk tetapi tidak terbatas pada:
- data model,
- paramset contract,
- validator spec,
- canonical paramset procedures,
- reason-code contract,
- backtest schema / calibration bila relevan,
- dan implementation blueprint untuk urutan bangun.

## Final Rule

Jika terdapat perbedaan antara artefak SQL dan dokumen normatif Weekly Swing, dokumen normatif selalu menang dan artefak SQL harus diperbarui.

## Reviewer Reminder

Perubahan pada artifact DB strategy-level wajib dibaca bersama owner normatifnya. Tidak boleh ada semantic drift yang lahir dari SQL lebih dulu lalu baru dicari pembenarannya di markdown.

