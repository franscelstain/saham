# System Translation Baseline

## Purpose

Dokumen ini mengunci baseline translation dari kontrak domain aktif ke blueprint implementasi lintas layer.

Tujuannya adalah memastikan bahwa:
- kontrak producer-facing dari `market_data` tidak berubah makna saat diterjemahkan ke kode;
- perilaku owner `watchlist` tidak berpindah diam-diam ke layer lain;
- `api_architecture` dipakai sebagai vocabulary translation, bukan pembuat policy baru;
- implementer memiliki peta yang cukup tegas tentang siapa membaca apa, siapa memutuskan apa, dan siapa hanya mengekspos hasil.

Dokumen ini bersifat netral terhadap nama aplikasi.

## Active Scope

Baseline ini berlaku untuk fase aktif saat ini:
- producer domain: `docs/market_data/`
- consumer domain: `docs/watchlist/`
- implementation vocabulary: `docs/api_architecture/`

Fokus aktifnya adalah translasi flow `market_data -> watchlist weekly_swing -> transport/read model consumer`.

## Out of Scope

Dokumen ini tidak mengatur:
- policy bisnis internal market-data;
- policy bisnis internal watchlist sebagai owner rule;
- execution / order / broker / portfolio;
- detail infrastruktur runtime;
- class name final di kode.

## Core Translation Chain

Urutan translation yang sah untuk sistem aktif adalah:

1. **Producer-facing intake meaning**
   - meaning input berasal dari kontrak producer-facing `market_data`.
2. **Consumer behavior meaning**
   - arti PLAN / RECOMMENDATION / CONFIRM berasal dari owner docs `watchlist`.
3. **Read access translation**
   - read adapter / repository intake mengambil data sesuai jalur baca yang sah.
4. **Application orchestration**
   - application service menyusun urutan call, bukan membuat rule.
5. **Domain compute**
   - domain compute menjalankan rule internal watchlist pada input yang sudah bersih.
6. **Persistence translation**
   - repository/persistence adapter menyimpan dan membaca artifact consumer tanpa mengubah rule meaning.
7. **Transport exposure**
   - transport membentuk response DTO dan error contract, bukan policy baru.

## Canonical Layer Responsibilities

### 1. Producer-facing read adapter / intake repository
Boleh:
- membaca publication-aware upstream consumer-facing output;
- menormalisasi hasil baca ke intake DTO / result object.

Tidak boleh:
- mengubah meaning producer contract;
- membaca raw internals sebagai shortcut baru;
- menjalankan rule watchlist.

### 2. Application service / orchestration
Boleh:
- menyusun urutan PLAN -> RECOMMENDATION -> CONFIRM sesuai flow yang sah;
- memanggil intake reader, domain compute, dan persistence adapter;
- mengelola transaction boundary / use-case orchestration bila diperlukan.

Tidak boleh:
- menciptakan policy baru;
- mengganti arti eligibility/publication producer;
- menghitung rule ranking/scoring internal watchlist.

### 3. Domain compute
Boleh:
- membentuk PLAN;
- membentuk RECOMMENDATION;
- mengevaluasi CONFIRM;
- mengeluarkan result object keputusan internal.

Tidak boleh:
- melakukan query ke upstream source langsung;
- membaca request mentah transport;
- menulis persistence langsung;
- mengembalikan response transport final.

### 4. Persistence adapter / repository artifact
Boleh:
- menyimpan artifact PLAN / RECOMMENDATION / CONFIRM;
- membaca artifact untuk consumer read atau audit.

Tidak boleh:
- menggabungkan semantik intake upstream dan semantik artifact write menjadi kontrak tunggal serba bisa;
- membuat keputusan bisnis internal.

### 5. Transport / response shaping
Boleh:
- validasi bentuk request;
- memanggil application service;
- membentuk response DTO / error contract.

Tidak boleh:
- memanggil domain compute secara liar tanpa orchestration;
- mengubah meaning artifact atau input producer;
- menjadikan request array mentah sebagai kontrak lintas layer.

## Active-System Module Translation

Untuk weekly_swing, canonical translation minimumnya adalah:
- `WsPlanInputProvider` = producer-facing intake read adapter
- `WsPlanEngine` = domain compute untuk PLAN formation
- `WsPlanAssembler` = assembler result/artifact shape setelah keputusan domain tersedia
- `WsRecommendationEngine` = domain compute untuk recommendation formation
- `WsConfirmOverlayEngine` = domain compute untuk confirm evaluation
- `RuntimeArtifactRepository` = persistence adapter untuk artifact watchlist
- `WsWatchlistReadService` = application/read orchestration entry
- `WsWatchlistApiPresenter` = transport-facing response shaper / presenter

Nama implementasi nyata boleh berbeda, tetapi makna layer-nya tidak boleh berubah.

## DTO / Result Boundary Expectations

Minimal harus ada pembedaan kategori object berikut:
- **Upstream intake DTO/result object**: hasil baca producer-facing intake
- **Compute input DTO**: input bersih untuk PLAN / RECOMMENDATION / CONFIRM compute
- **Artifact persistence payload**: payload untuk penyimpanan artifact consumer
- **Response DTO**: bentuk keluaran transport/API

Aturan minimumnya:
- request mentah tidak boleh langsung masuk domain compute;
- raw row/query result tidak boleh dibiarkan sebagai kontrak aktif antar-layer;
- response DTO tidak boleh menjadi rumah rule bisnis;
- persistence payload tidak boleh diperlakukan sebagai upstream intake contract.

## Forbidden Layer Drift

Yang dilarang:
1. application service menghitung scoring/ranking owner watchlist;
2. repository intake mengubah arti kontrak producer-facing;
3. domain compute membaca market-data source langsung;
4. transport membangun keputusan PLAN/RECOMMENDATION/CONFIRM sendiri;
5. satu object array liar dipakai sekaligus sebagai intake, compute input, persistence payload, dan response;
6. artifact repository dipakai sebagai dalih untuk membaca raw internals producer;
7. presenter/serializer menjadi tempat policy baru.

## Reading Path for Translation Work

Sebelum menerjemahkan ke kode, pembaca minimal harus melewati urutan ini:
1. `docs/market_data/README.md`
2. kontrak producer-facing yang relevan di `docs/market_data/book/`
3. `docs/watchlist/system/README.md`
4. owner docs weekly_swing yang relevan
5. `docs/watchlist/system/implementation/weekly_swing/02_WS_MODULE_MAPPING.md`
6. `docs/watchlist/system/implementation/weekly_swing/03_WS_RUNTIME_ARTIFACT_FLOW.md`
7. `docs/api_architecture/README.md`
8. dokumen layer terkait di `docs/api_architecture/`

## Relation to Other Docs

- `SYSTEM_CROSS_DOMAIN_INPUT_BASELINE.md` mengunci intake producer -> consumer.
- `SYSTEM_ASSEMBLY_BASELINE.md` mengunci assembly lintas domain.
- Dokumen ini mengunci translation lintas layer setelah intake dan assembly sudah jelas.

## Final Rule

Translation implementasi baru boleh dianggap cukup rapat bila implementer tidak perlu menebak lagi:
- siapa membaca input producer-facing,
- siapa hanya mengorkestrasi,
- siapa menjalankan rule watchlist,
- siapa menyimpan artifact,
- dan siapa hanya membentuk response.
