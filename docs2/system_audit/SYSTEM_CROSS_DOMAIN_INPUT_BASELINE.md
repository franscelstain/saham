# System Cross-Domain Input Baseline

## Purpose

Dokumen ini mengunci baseline intake input lintas domain pada sistem dokumentasi aktif.

Tujuan utamanya adalah memastikan bahwa domain consumer tidak membangun jalur baca upstream secara liar, tidak membuat kontrak intake paralel, dan tidak mengambil alih ownership domain producer.

Dokumen ini bersifat netral terhadap nama aplikasi dan hanya mengatur prinsip serta baseline intake lintas domain yang sah.

Dokumen ini tidak menggantikan owner contract pada domain masing-masing.  
Dokumen ini hanya menjadi bridge normatif untuk memastikan hubungan producer -> consumer cukup jelas dan tidak ambigu saat diterjemahkan ke implementasi.

## Current Active Focus

Untuk fase aktif saat ini, baseline ini terutama berlaku pada hubungan berikut:

- producer domain: `docs/market_data/`
- consumer domain: `docs/watchlist/`

Namun struktur baseline ini sengaja ditulis netral agar tetap berlaku bila nanti ada consumer domain lain yang membaca input dari producer domain yang sama.

## Why this document exists

Dokumen ini diperlukan karena:

- domain producer bisa sudah rapi sendiri;
- domain consumer bisa sudah rapi sendiri;
- tetapi hubungan input di antara keduanya masih bisa longgar;
- dan kelonggaran itu sering menjadi sumber drift saat implementasi nyata dimulai.

Tanpa baseline ini, masalah yang paling sering terjadi adalah:

- consumer membaca sumber upstream yang berbeda-beda;
- consumer membuat asumsi sendiri tentang input yang dianggap sah;
- adapter, DTO, dan repository dibangun tidak konsisten;
- ownership producer terlihat benar di dokumen, tetapi bocor saat build nyata.

## Scope

Dokumen ini hanya mengatur:

- prinsip input lintas domain;
- posisi owner producer dan owner consumer;
- bentuk authority saat consumer membaca upstream;
- batasan apa yang boleh dan tidak boleh dilakukan consumer terhadap data producer;
- kebutuhan minimum agar input lintas domain dianggap cukup siap dipakai implementasi.

## Out of Scope

Dokumen ini tidak mengatur:

- business rule internal producer;
- business rule internal consumer;
- strategi ranking / scoring / grouping consumer;
- execution, broker, portfolio, order management;
- bentuk detail persistence per modul;
- payload endpoint final kecuali hanya sebagai turunan dari kontrak intake yang sah.

## Core Principle

### 1. Producer owns the source contract
Domain producer adalah owner kontrak data yang dihasilkan.

Artinya:
- producer menentukan bentuk output upstream yang sah;
- consumer tidak boleh mendefinisikan ulang makna output producer;
- consumer tidak boleh menjadikan summary atau adapter lokal sebagai authority baru atas output producer.

### 2. Consumer owns consumer behavior, not producer meaning
Domain consumer hanya boleh mengatur:
- bagaimana input producer dipakai;
- bagaimana input producer diterjemahkan ke proses internal consumer;
- bagaimana keputusan internal consumer dibentuk dari input yang sah.

Consumer tidak boleh mengubah arti kontrak producer.

### 3. Cross-domain input must be explicit
Hubungan producer -> consumer harus cukup eksplisit sehingga implementer tidak perlu menebak:
- input upstream mana yang sah;
- dari dokumen mana authority intake berasal;
- apakah consumer boleh membaca tabel/folder/sumber tertentu secara langsung;
- boundary data minimum yang harus tersedia sebelum consumer berjalan.

### 4. Input baseline must reduce interpretation drift
Baseline intake lintas domain harus dibuat untuk mengurangi:
- multiple read paths;
- parallel DTO contracts;
- assumption-based integration;
- shadow ownership.

## Authority Model

### Producer-side authority
Authority utama untuk input lintas domain tetap berada di domain producer.

Untuk fase aktif saat ini, bila consumer membutuhkan input dari market-data, maka authority utama harus mengikuti owner docs pada `docs/market_data/`, terutama kontrak yang memang ditujukan untuk consumer-readable output.

### Consumer-side authority
Authority consumer hanya berlaku pada:
- validasi bahwa input producer cukup untuk consumer process;
- pembentukan model internal consumer;
- perilaku internal consumer setelah input diterima.

### Bridge authority
Dokumen ini hanya berfungsi sebagai:
- penegas jalur baca yang sah;
- penegas batas ownership;
- penegas bahwa input lintas domain harus berbasis kontrak producer yang eksplisit.

Dokumen ini bukan owner data semantics.

## Intake Rule

### Rule 1 — Consumer must read from explicit producer-facing contracts
Consumer wajib membaca dari kontrak producer yang memang ditujukan untuk downstream consumption.

Consumer tidak boleh memilih jalur upstream berdasarkan kenyamanan implementasi semata.

### Rule 2 — Consumer must not read raw upstream internals unless explicitly allowed
Consumer tidak boleh membaca:
- tabel internal mentah,
- state internal producer,
- artifact antara,
- atau struktur teknis lain yang tidak secara eksplisit dinyatakan sebagai consumer-facing input,

kecuali producer contract secara jelas memang mengizinkannya.

### Rule 3 — Consumer-facing read path must be single in meaning
Jalur baca boleh saja secara teknis memiliki beberapa lapisan implementasi, tetapi makna kontraknya harus tunggal.

Tidak boleh ada situasi di mana:
- satu implementasi membaca publication-aware read model;
- implementasi lain membaca raw table set;
- dan keduanya sama-sama dianggap sah tanpa baseline yang jelas.

### Rule 4 — Consumer must treat producer input as sealed meaning, not editable meaning
Begitu kontrak input producer sudah ditetapkan, consumer wajib memperlakukan makna input itu sebagai sealed meaning pada boundary intake.

Consumer boleh menurunkan model internal, tetapi tidak boleh mengubah definisi input producer.

## Minimum Requirements for a Valid Cross-Domain Input Baseline

Suatu hubungan input lintas domain baru boleh dianggap cukup jelas bila minimal menjawab hal berikut:

1. **Producer owner**
   - domain mana yang menjadi owner kontrak input?

2. **Consumer owner**
   - domain mana yang menjadi owner perilaku setelah input diterima?

3. **Allowed read path**
   - jalur baca upstream mana yang sah untuk consumer?

4. **Disallowed read path**
   - jalur baca apa yang tidak boleh dipakai consumer?

5. **Input boundary**
   - jenis data apa yang dianggap sebagai bahan baku intake?

6. **Input timing / state validity**
   - kapan input dianggap valid untuk dibaca consumer?

7. **Translation boundary**
   - di layer mana input producer diterjemahkan ke model internal consumer?

8. **No-shadow-contract rule**
   - bagaimana mencegah adapter, DTO, atau summary lokal berubah menjadi kontrak paralel?

## Current Application to Active Domains

### Producer domain
- `docs/market_data/`

### Consumer domain
- `docs/watchlist/`

### Current baseline meaning
Untuk fase aktif saat ini:

- `market_data` adalah owner kontrak upstream data yang dapat dikonsumsi downstream;
- `watchlist` adalah owner perilaku watchlist setelah input upstream yang sah diterima;
- `watchlist` tidak boleh mendefinisikan ulang arti kontrak output `market_data`;
- `watchlist` tidak boleh membaca struktur internal `market_data` secara bebas hanya karena secara teknis bisa.

## Reading Baseline for Current Active Domains

Untuk hubungan aktif `market_data -> watchlist`, pembaca wajib memulai dari jalur berikut:

1. `docs/market_data/README.md`
2. owner docs market-data yang menjelaskan consumer-readable / downstream-facing contract
3. `docs/watchlist/README.md`
4. owner docs watchlist yang menjelaskan bagaimana PLAN / proses internal dibangun dari input upstream yang sah
5. `docs/api_architecture/README.md`
6. dokumen layer implementation yang menjelaskan translasi kontrak ke kode

Urutan ini penting agar:
- arti input datang dari producer;
- arti perilaku datang dari consumer;
- cara menerjemahkan ke kode datang dari architecture guide.

## Layer Translation Rule

Kontrak input lintas domain tidak boleh diterjemahkan sembarangan di semua layer.

Aturan minimumnya:

- **repository / read adapter** boleh mengambil data sesuai jalur baca yang sah;
- **application/service orchestration** boleh menyusun alur proses;
- **domain compute consumer** boleh memakai input untuk rule internal consumer;
- **transport layer** tidak boleh menciptakan ulang makna input producer;
- **DTO internal** tidak boleh diam-diam berubah menjadi owner contract baru.

## No Parallel Contract Rule

Hal-hal berikut dilarang:

- consumer membuat definisi “input sah” sendiri yang tidak diturunkan jelas dari producer contract;
- summary file consumer mengambil alih authority producer;
- implementation note berubah menjadi kontrak intake baru;
- beberapa adapter berbeda mendefinisikan field semantik dengan makna berbeda.

Kalau beberapa bentuk implementasi memang dibutuhkan, semua itu tetap harus tunduk pada satu makna intake yang sama.

## Signs That Cross-Domain Input Is Still Too Loose

Suatu area harus dianggap masih `PARTIAL` bila salah satu kondisi berikut masih ada:

- pembaca belum bisa menunjuk satu jalur intake yang jelas;
- consumer masih tampak bisa membaca beberapa sumber upstream yang artinya tidak identik;
- boundary “boleh baca” vs “tidak boleh baca” belum tegas;
- translation boundary ke layer kode belum cukup jelas;
- implementer masih harus menebak field atau state minimum agar consumer bisa berjalan.

## Signs That Cross-Domain Input Is Ready

Suatu area baru boleh dianggap `PASS` bila:

- producer owner jelas;
- consumer owner jelas;
- jalur intake sah jelas;
- jalur baca yang dilarang jelas;
- translation boundary cukup jelas;
- tidak ada kontrak intake paralel;
- implementer tidak perlu menebak meaning dasar dari input producer.

## Current Audit Interpretation

Berdasarkan kondisi fase aktif saat ini:

- hubungan `market_data -> watchlist` sudah benar secara arah;
- tetapi readiness intake lintas domain masih harus dianggap `PARTIAL` sampai baseline intake benar-benar cukup eksplisit dan tidak memberi ruang implementasi paralel.

Dokumen ini ada untuk membantu menutup gap tersebut.

## Update Rule

Dokumen ini boleh diperbarui bila:

- ada domain producer baru;
- ada domain consumer baru;
- ada perubahan authority pada jalur intake lintas domain;
- ada audit yang menunjukkan bahwa baseline intake masih terlalu longgar.

Dokumen ini tidak boleh diperbarui hanya untuk menyalin ulang isi owner docs.  
Perubahan harus tetap fokus pada kejelasan bridge lintas domain.

## Final Rule

Sistem tidak boleh dinilai matang penuh pada level lintas domain bila consumer masih bisa membangun intake dari producer melalui lebih dari satu makna kontrak yang sama-sama tampak sah.
