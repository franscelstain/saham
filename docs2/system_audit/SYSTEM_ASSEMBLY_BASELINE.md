# System Assembly Baseline

## Purpose

Dokumen ini mengunci cara merakit paket dokumentasi aktif menjadi satu jalur build sistem yang jelas. Fokusnya bukan owner rule per domain, melainkan urutan assembly lintas-domain dari kontrak sampai translation ke implementasi.

Dokumen ini netral terhadap nama aplikasi dan tidak menggantikan owner docs pada `market_data`, `watchlist`, maupun `api_architecture`.

## Active Scope

Baseline assembly aktif hanya mencakup:

- `docs/market_data/`
- `docs/watchlist/`
- `docs/api_architecture/`
- `docs/system_audit/`
- `docs/README.md`

## Assembly Principle

Sistem aktif harus dirakit dengan arah authority berikut:

- producer contract lebih dulu daripada consumer behavior;
- consumer behavior lebih dulu daripada implementation translation;
- implementation translation lebih dulu daripada code layout detail.

Tidak boleh ada fase implementasi yang mengabaikan kontrak domain dengan alasan efisiensi coding.

## Assembly Phases

### Phase 1 — Root Orientation
Pembaca mengunci root ownership, source-of-truth precedence, dan build direction dari `docs/README.md`.

### Phase 2 — Producer Contract Lock
Pembaca mengunci `market_data` sebagai owner kontrak upstream dan publication-ready output yang dapat dibaca consumer.

### Phase 3 — Cross-Domain Readiness Lock
Pembaca mengunci `docs/system_audit/` untuk memastikan intake lintas-domain, readiness status, dan forbidden shortcuts sudah cukup jelas.

### Phase 4 — Consumer Behavior Lock
Pembaca mengunci `watchlist` sebagai owner perilaku consumer yang berjalan di atas input upstream yang sah.

### Phase 5 — Implementation Entry
Pembaca baru boleh masuk ke `watchlist/system/implementation/` setelah Phase 2 sampai Phase 4 stabil.

### Phase 6 — Architecture Translation
Pembaca masuk ke `docs/api_architecture/` untuk menerjemahkan kontrak domain ke controller/service/repository/domain compute tanpa menciptakan policy baru.

## Global Reading Order

1. `docs/README.md`
2. `docs/market_data/README.md`
3. owner contracts market-data yang consumer-facing
4. `docs/system_audit/SYSTEM_READINESS_AUDIT_BASELINE.md`
5. `docs/system_audit/SYSTEM_READINESS_CONTRACT_TRACKER.md`
6. `docs/system_audit/SYSTEM_CROSS_DOMAIN_INPUT_BASELINE.md`
7. `docs/watchlist/README.md`
8. `docs/watchlist/system/README.md`
9. owner policy docs watchlist
10. `docs/watchlist/system/implementation/README.md`
11. `docs/api_architecture/README.md`
12. architecture guidance yang relevan

## Global Build Order

1. stabilkan producer upstream contract;
2. stabilkan consumer intake dan behavior contract;
3. stabilkan system readiness dan assembly baseline;
4. baru bangun translation layer;
5. baru turunkan ke module, service, repository, dan transport.

## What Must Be Locked Before Coding Starts

Sebelum coding dimulai, minimal hal berikut harus sudah locked secara meaning:

- producer-facing upstream intake;
- consumer behavior owner docs;
- forbidden shortcuts lintas-domain;
- phase transition dari domain contract ke implementation translation;
- peran `api_architecture` sebagai guardrail, bukan policy owner.

## Forbidden Shortcuts

Hal berikut dilarang:

- memulai build dari implementation README tanpa membaca owner docs;
- memulai build dari architecture docs tanpa membaca domain contracts;
- membangun consumer seolah producer contract bisa ditentukan ulang di layer repository;
- memperlakukan contoh implementasi generik sebagai source of truth atas sistem aktif.

## Relation to Other Documents

- `docs/README.md` = root ownership dan source of truth
- `docs/system_audit/*` = readiness and anti-drift controls
- `docs/market_data/*` = producer contract owner
- `docs/watchlist/*` = consumer behavior owner
- `docs/api_architecture/*` = implementation translation guardrail

## Final Rule

Sistem tidak boleh dianggap matang pada level assembly bila implementer masih harus menyusun sendiri urutan authority lintas-domain dari nol.
