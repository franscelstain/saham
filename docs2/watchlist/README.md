# Watchlist Documentation

> **Status:** LOCKED (Normative)
> **Doc Role:** System governance index


## Purpose
Entry point dokumentasi watchlist tingkat sistem.

## Scope
Pembaca mulai dari sini untuk memahami governance, struktur folder, dan urutan baca.

## Inputs
- Pembaca, reviewer, implementer, dan auditor yang akan menelusuri dokumen watchlist.

## Outputs
- Peta baca dan batas source of truth lintas layer watchlist.

Folder utama: [`docs/watchlist/`](./README.md).

Dokumentasi ini mencakup sistem watchlist secara global: governance, database watchlist aplikasi, kontrak data sumber, dan dokumen per-policy.

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
- [`policy.md`](policy.md) — governance lintas policy + aturan cara baca
- [`db/`](db/README.md) — schema/DDL database watchlist aplikasi
- [`../db/`](../db/README.md) — shared foundation lintas domain (market calendar, ticker master)
- [`../market_data/`](../market_data/README.md) — kontrak data upstream market-data (bars, indicators, publication/read model, readiness)
- [`policies/_shared/`](policies/_shared/README.md) — kontrak global lintas policy
- `policies/<policy>/` — aturan bisnis policy spesifik

## Navigasi
- [`policy.md`](policy.md) — governance policy dan standar dokumentasi.
- [`db/`](db/README.md) — schema + seed database watchlist (baca berurutan mulai 01).
- [`policies/`](policies/README.md) — katalog policy (lihat [`policies/README.md`](policies/README.md)).
