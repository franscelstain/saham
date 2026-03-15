# 01 — Weekly Swing Watchlist (EOD) — Overview

## Purpose

Dokumen ini adalah overview strategy Weekly Swing. Dokumen ini merangkum ruang lingkup strategy dan jalur baca, tetapi tidak menggantikan dokumen normatif yang menjadi owner aturan detail.

## Scope

Weekly Swing mencakup:

- kontrak eksekusi PLAN dan CONFIRM,
- model data strategy,
- kontrak paramset dan validator,
- plan algorithm,
- dynamic selection yang deterministik,
- confirm overlay,
- persistence artifacts yang dimiliki strategy,
- contract-test anchors,
- dan dokumen pendukung untuk contoh, fixture, serta referensi implementatif.

## Core Reading Order

Untuk implementasi Weekly Swing, jalur baca inti yang dianjurkan adalah:

1. execution canonical,
2. data model,
3. paramset contract,
4. validator spec,
5. plan algorithm,
6. dynamic selection,
7. confirm overlay,
8. contract test checklist.

Dokumen bernomor pada folder ini adalah source of truth utama di level strategy.

## Supporting Folders

Folder pendukung pada strategy ini memiliki peran sebagai berikut:

- `_refs/`  
  Dokumen referensial yang menjelaskan atau merangkum kontrak normatif.

- `examples/`  
  Contoh output atau representasi runtime yang mengikuti kontrak strategy.

- `fixtures/`  
  Golden test assets untuk validasi determinism dan acceptance.

- `db/`  
  Artefak persistence dan implementasi SQL/schema yang mendukung strategy ini.

Folder pendukung tersebut tidak menjadi owner aturan wajib strategy.

## Relationship to Shared Policy

Weekly Swing menggunakan baseline shared yang relevan dari `docs/watchlist/policies/_shared/`. Namun, semua aturan yang hanya berlaku untuk Weekly Swing tetap dimiliki oleh file normatif bernomor pada folder ini.

## Non-Authority Statement

Jika overview ini tampak berbeda dari dokumen normatif Weekly Swing yang lebih rinci, maka dokumen normatif yang menjadi owner topik selalu menang.
