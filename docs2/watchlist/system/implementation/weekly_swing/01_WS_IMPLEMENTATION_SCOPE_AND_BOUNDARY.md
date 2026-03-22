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
3. watchlist hanya memakai input upstream producer-facing yang sah dari domain lain atau input manual yang sah
4. watchlist tidak boleh membaca raw internals, pipeline state antara, atau artifact teknis market-data sebagai pengganti intake producer-facing
5. `RECOMMENDATION` dibentuk hanya dari `PLAN`
6. `CONFIRM` dibentuk dari candidate `PLAN`
7. `CONFIRM` tidak mengubah `RECOMMENDATION`
8. aplikasi tetap harus stabil walau input berasal dari API gratis atau manual

## Implementation Principle

Urutan implementasi aplikasi watchlist adalah:
1. materialize `PLAN`
2. materialize `RECOMMENDATION`
3. materialize `CONFIRM`
4. compose consumer view

Tidak boleh ada code path yang melompati urutan semantik di atas.


## Upstream intake baseline
Untuk scope aktif Weekly Swing, input upstream dari `market-data` harus diperlakukan sebagai publication-aware producer-facing intake. Implementasi harus mengikat intake minimal ke kontrak berikut:
- `docs/market_data/book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `docs/market_data/book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `docs/market_data/book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `docs/market_data/book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`

Implementasi watchlist tidak boleh membuat shortcut baru yang mengubah makna intake upstream itu.
