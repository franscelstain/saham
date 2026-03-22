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

### Allowed Upstream Intake
Untuk scope aktif, intake upstream `watchlist` dari `market-data` harus mengikuti kontrak producer-facing yang jelas dari domain `market_data`, terutama kontrak consumer-readable dan publication-aware yang memang ditujukan untuk downstream consumption.

`watchlist` tidak boleh menganggap hal berikut sebagai jalur intake yang setara tanpa kontrak producer yang eksplisit:
- raw internal tables / raw bars / raw indicators
- pipeline state antara
- technical switching artifacts
- implementation shortcut yang hanya nyaman untuk kode

Aturan ini ada agar arti input upstream tetap dimiliki producer, sedangkan `watchlist` hanya memiliki perilaku setelah input itu diterima.



## Layer Reading Guard

Folder ini bisa memuat system docs, audit docs, implementation guidance, examples, fixtures, SQL support, schema markdown, dan sample payload.
Keberadaan artefak-artefak tersebut **tidak otomatis** berarti paket ini sudah masuk audit Layer C.
Tanpa bukti code aplikasi nyata, runtime payload nyata dari aplikasi, atau persistence runtime nyata yang bisa ditelusuri, pembacaan audit **harus tetap A/B**, bukan C.

## Folder Layout

- `system/` = source of truth watchlist
- `audit/` = audit baseline watchlist
- `system/implementation/` = implementation guidance yang tunduk pada baseline system docs


Audit implementasi watchlist berada di `docs/watchlist/audit/implementation/`.


## Layer Activation Reference

Gunakan [`LAYER_ACTIVATION_RULE.md`](LAYER_ACTIVATION_RULE.md) untuk menentukan apakah paket harus dibaca sebagai Layer A, B, atau C.
