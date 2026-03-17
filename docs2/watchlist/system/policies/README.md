# Policies — Index

> **Status:** LOCKED (Normative)
> **Doc Role:** Policy layer catalog

## Purpose

Dokumen ini adalah katalog layer policy pada domain watchlist. Dokumen ini membantu pembaca masuk ke baseline lintas strategy dan memilih strategy policy yang relevan tanpa menggandakan governance root atau kontrak strategy.

## Scope

Dokumen ini dipakai setelah membaca [`../policy.md`](../policy.md) dan [`../README.md`](../README.md) apabila pembaca ingin:

- masuk ke layer policy watchlist,
- memahami pembagian antara `_shared/` dan strategy-specific folders,
- atau memilih strategy policy yang ingin ditelusuri.

Dokumen ini bukan entry point utama domain dan bukan README strategy.

## Policy Layer Structure

Layer policy di domain watchlist terdiri dari:

- [`_shared/`](_shared/README.md)  
  Baseline lintas strategy yang berlaku bersama.

- folder strategy seperti [`weekly_swing/`](weekly_swing/README.md)  
  Kontrak strategy-specific yang berdiri di atas baseline shared.

## Reading Guidance

Urutan baca minimum yang dianjurkan adalah:

1. [`../policy.md`](../policy.md)
2. [`../README.md`](../README.md)
3. dokumen pada `_shared/` yang relevan
4. README strategy yang relevan
5. file normatif bernomor pada strategy tersebut

Dokumen ini hanya memetakan letak policy. Urutan baca detail strategy tidak diulang panjang di sini dan tetap dipimpin oleh README strategy masing-masing.

## Catalog

- [`weekly_swing/`](weekly_swing/README.md) — Weekly Swing

## Minimum Rule for Strategy Policies

Setiap policy strategy harus dibangun di atas baseline `_shared/`. Dokumen strategy-specific tidak boleh mendefinisikan ulang kontrak minimum global yang sudah hidup di `_shared/`; strategy-specific hanya boleh menambahkan semantics, constraints, procedures, dan acceptance areas yang memang khusus untuk strategy tersebut.

## Non-Authority Statement

Dokumen ini tidak menjadi owner kontrak strategy maupun owner governance root. Jika ada perbedaan antara katalog ini dan dokumen owner normatif yang dirujuk, dokumen owner normatif selalu menang.
