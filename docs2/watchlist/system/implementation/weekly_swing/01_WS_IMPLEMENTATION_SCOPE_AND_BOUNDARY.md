# 01 — WS Implementation Scope and Boundary

## Purpose

Dokumen ini menetapkan boundary implementasi Weekly Swing agar penerjemahan ke aplikasi tetap tunduk pada baseline system docs.

## Scope

Panduan implementasi ini mencakup:
- pembentukan artifact `PLAN`
- pembentukan artifact `RECOMMENDATION`
- pembentukan artifact `CONFIRM`
- penyajian output watchlist ke API/UI internal
- persistence artifact watchlist
- testing implementasi watchlist

Panduan implementasi ini tidak mencakup:
- ingestion market-data
- scheduler provider
- order placement
- buy/sell execution
- holdings / average buy / PnL
- portfolio lifecycle

## Locked Boundary

1. aplikasi watchlist hanya menghasilkan **saran**
2. aplikasi watchlist tidak mencatat transaksi aktual
3. watchlist hanya memakai data yang sudah tersedia dari domain lain atau input manual yang sah
4. `RECOMMENDATION` dibentuk hanya dari `PLAN`
5. `CONFIRM` dibentuk dari candidate `PLAN`
6. `CONFIRM` tidak mengubah `RECOMMENDATION`
7. aplikasi tetap harus stabil walau input berasal dari API gratis atau manual

## Implementation Principle

Urutan implementasi aplikasi watchlist adalah:
1. materialize `PLAN`
2. materialize `RECOMMENDATION`
3. materialize `CONFIRM`
4. compose consumer view

Tidak boleh ada code path yang melompati urutan semantik di atas.
