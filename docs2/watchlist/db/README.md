# Watchlist DB Artifacts

## Purpose

Folder ini berisi artefak database global domain watchlist.

## Scope

Artefak pada folder ini dapat mencakup:

- schema documentation,
- DDL implementation artifacts,
- seed artifacts,
- migration artifacts,
- dan referensi persistence global yang relevan terhadap domain watchlist.

## Ownership Rule

Dokumen dan SQL pada folder ini merealisasikan kebutuhan persistence watchlist. Ownership rule bisnis, governance, dan kontrak normatif tetap mengikuti dokumen governance dan policy watchlist yang relevan.

## Reading Guidance

Pembaca harus merujuk kembali ke:

- `docs/watchlist/policy.md`,
- dokumen policy yang relevan,
- dan `docs/watchlist/policies/_shared/06_SCHEMA_PARITY_RULES.md`
  untuk menjaga parity antara dokumen normatif dan artefak implementasi database.

## Final Rule

Jika terdapat perbedaan antara artefak database di folder ini dan dokumen normatif watchlist, dokumen normatif selalu menang dan artefak implementasi harus diperbarui.
