# 08 — Weekly Swing PLAN Algorithm

## Purpose

Dokumen ini adalah owner normatif untuk algoritma PLAN strategy Weekly Swing. Dokumen ini menetapkan bagaimana Weekly Swing mengonsumsi input upstream yang telah tersedia bagi watchlist untuk menghasilkan PLAN output milik watchlist.

## Scope

Dokumen ini mencakup:

- input assumptions yang relevan bagi consumer watchlist,
- langkah algoritmik pembentukan PLAN,
- rule pemilihan, penyaringan, dan pengelompokan yang dimiliki Weekly Swing,
- serta relationship terhadap deterministic selection, runtime output, dan contract-test acceptance.

Dokumen ini tidak mendefinisikan ulang kontrak upstream yang dimiliki `market_data`.

## Upstream Boundary

Dokumen ini mengasumsikan ketersediaan input upstream yang telah dipublikasikan secara authoritative oleh domain `market_data`.

Semantics berikut tetap dimiliki secara authoritative oleh `docs/market_data/` dan tidak didefinisikan ulang di dokumen ini:

- bars / OHLCV,
- indicators,
- publication,
- readiness,
- validity,
- dan kontrak upstream lain yang menyediakan input ke consumer watchlist.

Dokumen ini hanya menetapkan bagaimana Weekly Swing mengonsumsi input upstream tersebut untuk menghasilkan PLAN output milik watchlist.

## Reading Rule

Jika terdapat kebutuhan untuk memahami semantics input upstream, pembaca harus merujuk ke dokumen owner authoritative di `docs/market_data/`. Dokumen ini tidak menjadi source of truth bagi definisi upstream tersebut.

## Algorithm Structure

Untuk setiap tahap algoritma PLAN, gunakan struktur dokumentasi berikut:

### Step Name

**Purpose**  
Tujuan langkah.

**Inputs**  
Input downstream-ready yang dikonsumsi oleh langkah ini.

**Rule**  
Perilaku normatif Weekly Swing yang diterapkan pada langkah ini.

**Outputs**  
Keluaran langkah yang relevan terhadap tahapan berikutnya atau terhadap PLAN output akhir.

**Related Normative Documents**  
Dokumen owner lain yang relevan, misalnya dynamic selection, data model, atau contract-test checklist.

## Selection and Grouping Rule

Dokumen ini menetapkan rule PLAN yang dimiliki Weekly Swing, termasuk:

- screening behavior yang relevan bagi strategy,
- scoring atau prioritization behavior yang memang menjadi milik strategy,
- grouping result yang dihasilkan sebagai output PLAN,
- dan branch behavior seperti `NO_TRADE`, `WATCH_ONLY`, atau hasil final lain yang memang dikunci oleh strategy.

Rule tersebut harus dibaca bersama dokumen deterministic selection bila tahap tersebut memerlukan penguncian deterministik terhadap ranking atau ties.

## Relationship to Deterministic Selection

Aspek deterministik yang terkait ranking, tie handling, cutoff behavior, atau result ordering harus dibaca bersama `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`. Dokumen ini menetapkan alur algoritmik PLAN, sedangkan aspek deterministik yang lebih rinci tetap harus tunduk pada owner normatif yang relevan.

## Relationship to Output Shape

Bentuk output PLAN yang dihasilkan oleh algoritma ini harus konsisten dengan model data dan acceptance requirements Weekly Swing. Examples dan fixtures dapat membantu verifikasi, tetapi tidak menggantikan kontrak normatif yang ditetapkan di dokumen ini dan di dokumen owner terkait.

## Final Rule

Tidak ada perilaku PLAN yang boleh dianggap resmi hanya karena muncul di example, fixture, atau implementasi teknis apabila perilaku tersebut tidak dapat ditelusuri ke dokumen normatif ini atau ke owner normatif Weekly Swing yang relevan.
