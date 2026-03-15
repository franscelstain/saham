# Weekly Swing DB Artifacts

## Purpose

Folder `db/` berisi artefak persistence dan implementasi database yang mendukung strategy Weekly Swing.

## Scope

Artefak pada folder ini dapat mencakup:

- schema implementation artifacts,
- promotion procedures,
- seed files,
- dan artefak persistence lain yang merealisasikan kebutuhan strategy Weekly Swing.

## Ownership Rule

Aturan bisnis, kontrak normatif, dan semantik strategy tetap dimiliki oleh dokumen policy Weekly Swing yang relevan. SQL artifacts pada folder ini merealisasikan kontrak tersebut dan tidak menjadi owner utama atas business-rule semantics.

## Reading Guidance

Pembaca harus merujuk kembali ke dokumen normatif yang relevan, termasuk tetapi tidak terbatas pada:

- data model,
- paramset contract,
- validator spec,
- canonical paramset procedures,
- reason-code contract,
- dan dokumen backtest normatif bila relevan.

## Final Rule

Jika terdapat perbedaan antara artefak SQL dan dokumen normatif Weekly Swing, dokumen normatif selalu menang dan artefak SQL harus diperbarui.
