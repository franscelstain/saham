# Watchlist Scope Lock

## Current Active Scope

- domain: `watchlist`
- active policy: `weekly_swing`
- active layers: `PLAN`, `RECOMMENDATION`, `CONFIRM`

## In Scope

- Weekly Swing watchlist docs;
- PLAN / RECOMMENDATION / CONFIRM sebagai lapisan watchlist;
- deterministic watchlist suggestions;
- recommendation berbasis PLAN;
- confirm berbasis candidate PLAN;
- examples / fixtures / refs / db support yang langsung mendukung `weekly_swing`.

## Out of Scope

- portfolio;
- execution;
- order lifecycle;
- position management;
- holdings / PnL / trade journal;
- market-data internals;
- provider acquisition / fetch / retry / scheduler;
- policy lain di luar `weekly_swing`.

## Hard Boundary Rules

1. `watchlist` hanya menampilkan saran, bukan transaksi nyata.
2. `weekly_swing` adalah satu-satunya policy aktif yang boleh dinilai matang pada fase ini.
3. system docs watchlist tidak boleh mengambil alih owner domain market-data.
4. system docs watchlist tidak boleh mengambil alih owner domain portfolio.
5. audit wajib menurunkan nilai jika dokumen menyentuh domain out-of-scope secara normatif, bukan sekadar menyebut dependency.

## Data Reality Lock

Dokumen harus tetap realistis untuk:
- provider gratis;
- input manual.

Audit wajib menurunkan nilai jika desain watchlist secara normatif membutuhkan sumber data yang tidak sesuai constraint tersebut.
