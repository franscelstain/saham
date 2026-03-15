# 10 — Weekly Swing CONFIRM Overlay

## Purpose

Dokumen ini adalah owner normatif untuk behavior CONFIRM overlay Weekly Swing. Dokumen ini menetapkan bagaimana watchlist mengevaluasi snapshot intraday terhadap hasil PLAN yang sudah final tanpa mengubah PLAN tersebut.

## Scope

Dokumen ini mengunci:

- boundary CONFIRM terhadap PLAN,
- input snapshot yang dipakai oleh CONFIRM,
- rule evaluasi overlay per ticker,
- output semantics label CONFIRM,
- dan relationship terhadap data model, snapshot tables, dan acceptance tests.

Dokumen ini tidak menetapkan kontrak publication, readiness, atau validity upstream.

## Boundary to PLAN

CONFIRM adalah tahap downstream yang berdiri di atas hasil PLAN yang sudah final.

Rule (LOCKED):
- CONFIRM tidak boleh mengubah `rank`, `group_semantic`, `score_total`, `levels.*`, `flags.*`, atau `reasons[]` milik PLAN.
- CONFIRM hanya menghasilkan keputusan overlay terpisah dengan shape runtime yang mengikuti `03_WS_DATA_MODEL_MARIADB.md`.
- Invariant pair dan write-scope tetap mengikuti `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`.

## Upstream Boundary

Dokumen ini mengasumsikan bahwa input intraday atau snapshot yang dipakai watchlist telah tersedia bagi consumer sesuai kontrak authoritative pada domain `market_data`.

Dokumen ini tidak mendefinisikan ulang:

- publication contract upstream,
- readiness contract upstream,
- validity contract upstream,
- atau semantics asli data upstream yang dikonsumsi.

Dokumen ini hanya menetapkan logic overlay Weekly Swing yang diterapkan di atas snapshot watchlist-owned atau input downstream-ready yang sudah tersedia.

## Input Contract for CONFIRM

Input persistence yang dipakai CONFIRM adalah representasi snapshot milik watchlist pada:

- `watchlist_confirm_snapshots`
- `watchlist_confirm_snapshot_items`

Owner persistence semantics untuk tabel di atas berada pada `11_WS_INTRADAY_SNAPSHOT_TABLES.md`.

Field input minimum yang relevan untuk overlay adalah:

- header snapshot: `snapshot_id`, `trade_date`, `captured_at`, `inserted_at`, `source`
- item snapshot: `ticker_code`, `last_price`, `chg_pct`, `volume_shares`, `turnover_idr`
- context runtime: `checked_at`
- context PLAN: kandidat PLAN yang sedang dievaluasi beserta `levels.entry_ref`, `levels.entry_band_low`, `levels.entry_band_high`

Rule (LOCKED):
- `effective_captured_at = LEAST(captured_at, inserted_at)` dipakai sebagai acuan umur snapshot.
- `snapshot_age_sec = TIMESTAMPDIFF(SECOND, effective_captured_at, checked_at)`.
- Jika snapshot tidak ditemukan untuk run CONFIRM yang dimaksud, hasil per ticker yang terdampak wajib `label = DELAY` dengan reason `WS_SNAPSHOT_MISSING`.
- Jika `snapshot_age_sec > confirm_overlay.snapshot_max_age_sec`, hasil per ticker yang terdampak wajib `label = DELAY` dengan reason `WS_STALE`.

## Overlay Evaluation Steps

### A. Candidate Binding

**Purpose**  
Mengikat item snapshot ke kandidat PLAN yang sedang dievaluasi.

**Rule (LOCKED)**  
- Binding identity akhir dilakukan dengan `ticker_code` / ticker runtime (`ticker`) yang mereferensikan kandidat PLAN yang sama.
- Snapshot untuk ticker yang tidak termasuk kandidat PLAN tidak membentuk kandidat CONFIRM baru.
- CONFIRM tidak boleh memperluas universe PLAN.

**Outputs**  
Pasangan `(plan_item, snapshot_item)` atau state `snapshot missing`.

### B. Snapshot Freshness Gate

**Purpose**  
Memastikan snapshot masih layak dipakai.

**Rule (LOCKED)**  
- Freshness dihitung dari `effective_captured_at`, bukan dari `captured_at` saja jika `inserted_at` lebih awal.
- TTL canonical mengikuti paramset active: `confirm_overlay.snapshot_max_age_sec`.
- Snapshot yang stale atau missing menghasilkan `DELAY` dan menghentikan evaluasi yang membutuhkan snapshot valid.

**Outputs**  
Status snapshot valid / stale / missing beserta reason code terkait.

### C. Drift Evaluation

**Purpose**  
Menilai apakah harga snapshot masih berada pada deviasi yang dapat diterima terhadap level entry PLAN.

**Rule (LOCKED)**  
- Acuan drift dihitung terhadap `levels.entry_ref` milik PLAN.
- Batas drift mengikuti `confirm_overlay.max_drift_from_entry_pct` dari paramset aktif.
- Dokumen ini menetapkan kebutuhan bahwa drift harus dievaluasi secara deterministik; rincian field output runtime tetap mengikuti `03_WS_DATA_MODEL_MARIADB.md` dan acceptance-nya mengikuti `13_WS_CONTRACT_TEST_CHECKLIST.md`.
- Field non-contract seperti order-book ladder tidak boleh memengaruhi keputusan drift.

**Outputs**  
Reason overlay yang menjelaskan apakah snapshot masih within-band atau sudah drift terlalu jauh.

### D. Confirm Label Resolution

**Purpose**  
Menetapkan label final CONFIRM untuk setiap item.

**Rule (LOCKED)**  
Label runtime CONFIRM yang resmi adalah:
- `CONFIRMED`
- `NEUTRAL`
- `CAUTION`
- `DELAY`

Aturan minimum label resolution:
- `DELAY` dipakai bila snapshot missing atau stale.
- Label selain `DELAY` hanya boleh dipakai bila snapshot valid dan evaluasi overlay selesai dijalankan.
- Implementasi tidak boleh membuat label runtime baru tanpa pembaruan normatif pada dokumen owner Weekly Swing yang relevan.

**Outputs**  
`label` dan `reasons[]` per ticker pada runtime output CONFIRM.

## Strictness Boundary

Rule (LOCKED):
- Unknown top-level field pada payload/runtime CONFIRM dianggap schema drift.
- Field input non-contract yang bersifat orderbook-specific seperti `bid1_price`, `ask1_price`, `spread`, atau `orderbook_json` boleh hadir pada input/fixture, tetapi harus diabaikan penuh dan tidak boleh mengubah keputusan overlay.

Boundary strictness ini harus dibaca bersama `03_WS_DATA_MODEL_MARIADB.md` dan `13_WS_CONTRACT_TEST_CHECKLIST.md`.

## Relationship to Snapshot Tables

Jika CONFIRM menggunakan persistence snapshot milik watchlist, shape tabel dan semantics field wajib dibaca bersama `11_WS_INTRADAY_SNAPSHOT_TABLES.md`. Dokumen ini menetapkan behavior overlay; dokumen `11` menetapkan rumah persistence semantics untuk snapshot watchlist-owned.

## Final Rule

Tidak ada behavior CONFIRM yang boleh dianggap resmi hanya karena muncul di example, fixture, atau implementasi teknis apabila behavior tersebut tidak dapat ditelusuri ke dokumen ini, `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`, `03_WS_DATA_MODEL_MARIADB.md`, atau `11_WS_INTRADAY_SNAPSHOT_TABLES.md`.
