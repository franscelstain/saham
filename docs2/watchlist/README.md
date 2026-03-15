# Watchlist Documentation

> **Status:** LOCKED (Normative)
> **Doc Role:** System governance index

## Purpose

`docs/watchlist/` adalah domain owner untuk aturan, kontrak, dan governance sistem watchlist. Domain ini mendefinisikan bagaimana strategi watchlist dibaca, divalidasi, dijalankan, diuji, dan dihubungkan ke artefak persistence yang dimiliki watchlist.

## Scope

Domain watchlist mencakup:

- governance domain watchlist,
- policy lintas strategy,
- policy strategy-specific,
- kontrak output dan persistence yang dimiliki watchlist,
- contract-test anchors,
- dokumen referensial,
- contoh output,
- golden fixtures,
- dan artefak database milik watchlist.

Strategy yang saat ini terdokumentasi penuh di domain ini adalah **Weekly Swing**. Kontrak strategy-specific yang bersifat wajib dibaca hidup di `docs/watchlist/policies/weekly_swing/`, sedangkan dokumen pada root domain ini menetapkan governance dan jalur baca tingkat domain.

## Relationship to Other Domains

Watchlist adalah consumer downstream terhadap `market_data`. Kontrak upstream seperti bars/OHLCV, indicators, publication, readiness, dan validity tetap dimiliki secara authoritative oleh `docs/market_data/` dan tidak didefinisikan ulang di domain watchlist.

Dokumen watchlist hanya boleh menyatakan ketergantungan terhadap kontrak upstream yang telah tersedia bagi consumer watchlist.

## Structure

Struktur domain ini dibagi menjadi:

- `policy.md`  
  Governance tertinggi domain watchlist.

- `policies/_shared/`  
  Aturan lintas strategy yang berlaku bersama.

- `policies/<strategy>/`  
  Dokumen normatif strategy-specific.

- `policies/<strategy>/_refs/`  
  Dokumen referensial strategy-scoped yang menjelaskan atau merangkum kontrak normatif strategy terkait.

- `policies/<strategy>/examples/`  
  Contoh output atau representasi runtime strategy-scoped yang tunduk pada kontrak normatif strategy terkait.

- `policies/<strategy>/fixtures/`  
  Golden test assets strategy-scoped untuk validasi determinism, acceptance, dan contract behavior.

- `db/`  
  Artefak persistence dan implementasi database global milik watchlist.

- `policies/<strategy>/db/`  
  Artefak persistence atau SQL strategy-scoped yang merealisasikan kebutuhan strategy tertentu tanpa mengambil alih kontrak upstream atau governance domain.

## Recommended Reading Order

Jalur baca yang dianjurkan adalah:

1. `docs/watchlist/policy.md`
2. `docs/watchlist/README.md`
3. `docs/watchlist/policies/README.md`
4. strategy entry README yang relevan
5. file normatif bernomor pada strategy yang relevan
6. blueprint implementasi strategy bila tersedia
7. `_refs/`, `examples/`, `fixtures/`, dan `db/` sebagai dokumen pendukung

## Reading Rule

Dokumen pada root domain ini tidak menggantikan kontrak strategy-specific. Untuk implementasi Weekly Swing, pembaca harus melanjutkan ke `docs/watchlist/policies/weekly_swing/` dan mengikuti file normatif bernomor sebagai source of truth utama di level strategy.
