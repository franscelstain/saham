# Watchlist Audit Foundation

## Purpose

Dokumen ini mengunci fondasi audit untuk domain `watchlist`.

Audit watchlist dipakai untuk memastikan dokumen system:
- tetap berada dalam domain watchlist;
- tidak melebar ke portfolio, execution, atau market-data internals;
- cukup jelas untuk membangun fitur watchlist berbasis trading;
- tetap stabil walau data berasal dari provider gratis atau input manual.

## Current Active Scope

- domain aktif: `watchlist`
- active policy: `weekly_swing`
- active output layers: `PLAN`, `RECOMMENDATION`, `CONFIRM`

Policy lain di luar `weekly_swing` berada di luar scope audit aktif sampai policy `weekly_swing` dianggap matang.

## Core Scope Lock

### In Scope
- system docs watchlist untuk `weekly_swing`;
- boundary `PLAN / RECOMMENDATION / CONFIRM`;
- runtime shape, contract, acceptance, dan implementation blueprint watchlist;
- examples, fixtures, refs, dan db support yang langsung mendukung `weekly_swing`.

### Out of Scope
- portfolio state, holdings, entry nyata, sell lifecycle;
- order placement, fill, broker integration, execution orchestration;
- market-data ingestion internals, provider scheduler, retry pipeline, fetch pipeline;
- policy lain di luar `weekly_swing`.

## Data Constraint

Audit wajib menilai apakah algoritma dan desain watchlist tetap realistis untuk constraint data berikut:
- API provider gratis;
- input manual yang sah.

Audit wajib menurunkan nilai jika dokumen diam-diam mengasumsikan:
- orderbook / microstructure premium;
- real-time dependency yang tidak tersedia secara realistis;
- upstream market-data internals sebagai bagian dari owner watchlist.

## Audit Layers

### Layer A — Document Audit
Menilai owner docs, boundary, scope, dan sinkronisasi dokumen system.

### Layer B — Build Translation Audit
Menilai apakah implementation blueprint dan build guidance menerjemahkan system docs dengan benar.

### Layer C — Implementation Audit
Menilai apakah aplikasi nyata dibangun sesuai system docs.

Untuk fase aktif saat ini, fokus utama adalah Layer A dan Layer B. Layer C akan dibangun setelah `weekly_swing` system docs dianggap matang.

Support artifacts seperti `_refs`, `examples`, `fixtures`, `db/*.sql`, `db/*.md`, dan sample JSON **tidak otomatis mengaktifkan Layer C**. Artifact tersebut tetap dihitung sebagai bukti/support untuk Layer A/B sampai ada code aplikasi nyata, runtime payload nyata dari app, atau persistence runtime nyata.

## Mandatory Watchlist Rules

Audit wajib memastikan seluruh system docs konsisten terhadap rule berikut:

1. watchlist hanya menampilkan saran;
2. watchlist bukan portfolio;
3. watchlist bukan execution engine;
4. watchlist bukan owner market-data internals;
5. recommendation berasal dari PLAN only;
6. recommendation dapat tersedia tanpa confirm;
7. recommendation dapat kosong walau `TOP_PICKS` / `SECONDARY` tidak kosong;
8. confirm eligibility berasal dari candidate PLAN;
9. non-recommended candidate tetap dapat di-confirm;
10. confirm tidak mengubah recommendation.

## Expected Outputs of Audit

Setiap audit minimal harus menghasilkan:
- status per file: `PASS / PARTIAL / FAIL / N/A`;
- rekap rule inti lintas file;
- daftar drift / conflict;
- patch prioritas berikutnya.

## Final Rule

Audit watchlist tidak boleh menilai dokumen sebagai matang jika boundary scope belum terkunci, walaupun wording terlihat rapi.
