# Watchlist Docs

Folder `docs/watchlist/` dipisah menjadi dua jalur utama:

- `system/` = source of truth untuk membangun fitur watchlist
- `audit/` = guardrail review untuk menjaga scope, boundary, dan sinkronisasi dokumen watchlist

## Current Active Scope

Saat ini scope aktif watchlist adalah:

- domain: `watchlist`
- active policy: `weekly_swing`

Policy lain di luar `weekly_swing` tidak dibahas sampai Weekly Swing benar-benar matang.

## Boundary

Watchlist hanya membahas **saran**.

Watchlist bukan owner untuk:

- execution order
- transaksi beli/jual aktual
- holdings / portfolio state
- market-data ingestion internals
- provider fetch pipeline

Watchlist hanya memakai data yang sudah tersedia dari domain `market-data` atau input manual yang sah.


## Folder Layout

- `system/` = source of truth watchlist
- `audit/` = audit baseline watchlist
- `system/implementation/` = implementation guidance yang tunduk pada baseline system docs


Audit implementasi watchlist berada di `docs/watchlist/audit/implementation/`.
