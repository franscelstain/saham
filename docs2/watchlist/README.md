# Watchlist Documentation

> **Status:** LOCKED (Normative)
> **Doc Role:** System governance index

## Purpose
Entry point dokumentasi watchlist tingkat sistem.

## Scope
Pembaca mulai dari sini untuk memahami governance, struktur folder, batas ownership domain, dan jalur baca awal.

## Inputs
- Pembaca, reviewer, implementer, dan auditor yang akan menelusuri dokumen watchlist.

## Outputs
- Peta baca awal.
- Batas source of truth lintas layer watchlist.
- Penunjuk domain owner untuk area yang memang berada di luar watchlist.

Folder utama: [`docs/watchlist/`](./README.md).

Dokumentasi ini mencakup sistem watchlist secara global: governance, database watchlist aplikasi, kontrak lintas-policy, dan dokumen policy spesifik.

## Start here
Urutan baca yang paling aman:
1. [`policy.md`](policy.md)
2. [`00_LINK_INTEGRITY_CHECK_LOCKED.md`](00_LINK_INTEGRITY_CHECK_LOCKED.md)
3. [`db/01_DB_OVERVIEW.md`](db/01_DB_OVERVIEW.md)
4. [`policies/README.md`](policies/README.md)
5. [`policies/_shared/README.md`](policies/_shared/README.md)
6. policy spesifik yang mau diimplementasikan

## Prinsip sistem (scope saat ini: Weekly Swing EOD)
- Berbasis **EOD (end-of-day)** untuk menghasilkan rekomendasi **Weekly Swing**.
- Dua tahap tegas:
  - **PLAN** dibuat dari data EOD hari ini untuk rekomendasi **besok** (next trading day), disimpan sebagai snapshot.
  - **CONFIRM** memakai data runtime/intraday sebagai pengecekan keyakinan yang sifatnya sesaat dan **tidak boleh mengubah atau mempengaruhi hasil PLAN**.
- Keputusan eksekusi tetap **manual** di luar aplikasi; CONFIRM hanya saran tambahan.

## Source of truth per layer
- [`policy.md`](policy.md) — governance lintas policy dan aturan cara baca.
- [`db/`](db/README.md) — schema/DDL database watchlist aplikasi.
- shared foundation lintas domain — berada di luar paket `docs/watchlist/`; jangan diperlakukan sebagai owner aturan watchlist.
- `docs/market_data/` — owner authoritative untuk kontrak upstream market-data seperti bars, indicators, publication/read model, dan readiness.
- [`policies/_shared/`](policies/_shared/README.md) — kontrak global lintas policy watchlist.
- `policies/<policy>/` — aturan bisnis policy spesifik.

## Navigasi
- [`policy.md`](policy.md) — governance policy dan standar dokumentasi.
- [`db/`](db/README.md) — schema + seed database watchlist (baca berurutan mulai 01).
- [`policies/`](policies/README.md) — katalog policy.
