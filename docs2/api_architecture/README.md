# API Architecture

Folder ini adalah source of truth untuk pedoman arsitektur API dan backend yang framework-agnostic. Dokumen di dalamnya mengatur pembagian tanggung jawab antar layer, kontrak perpindahan data, akses persistence, bentuk response, penanganan error, performa data besar, idempotensi, dan logging operasional.

Dokumen-dokumen di folder ini tidak dimaksudkan sebagai catatan teori umum. Dokumen-dokumen ini adalah pedoman implementasi. Bila ada keputusan desain atau implementasi yang bertentangan dengan dokumen di folder ini, maka implementasi harus dianggap menyimpang sampai ada pengecualian resmi yang terdokumentasi.

## Tujuan folder ini

Folder ini dibuat agar:
- struktur backend dan API punya rumah aturan yang jelas;
- tim tahu harus membuka file mana untuk topik tertentu;
- boundary antar layer tidak kabur;
- query, DTO, service, response, dan concern operasional tidak bercampur liar;
- perubahan desain dapat ditinjau terhadap kontrak yang jelas;
- sistem tetap dapat berkembang tanpa menjadikan controller, service, atau repository sebagai tempat semua hal.

## Cara memakai folder ini

- Gunakan `aturan-global-arsitektur-api.md` sebagai payung prinsip dan batas umum sistem.
- Gunakan file topik spesifik sebagai sumber aturan implementasi untuk concern tertentu.
- Bila dua dokumen tampak berkaitan, dokumen yang lebih spesifik harus diprioritaskan untuk aturan teknis concern tersebut.
- Bila sebuah implementasi membutuhkan pengecualian, pengecualian itu harus sempit, jelas, dan terdokumentasi. Pengecualian tidak boleh dipakai untuk membatalkan aturan umum secara diam-diam.

## Urutan baca yang direkomendasikan

Urutan baca ini paling tepat untuk anggota tim baru atau saat melakukan review arsitektur:

1. `aturan-global-arsitektur-api.md`
2. `transport-boundary.md`
3. `application-service.md`
4. `repository.md`
5. `domain-compute.md`
6. `kontrak-dto.md`
7. `handoff-antar-layer.md`
8. `response-dan-error.md`
9. `performa-data-besar.md`
10. `determinisme-dan-idempotensi.md`
11. `logging-operasional.md`

Urutan ini bukan berarti file di bawah lebih tidak penting. Urutan ini dipilih agar pembaca memahami:
- batas global dulu;
- lalu pintu masuk sistem;
- lalu orchestration use case;
- lalu persistence;
- lalu rule domain;
- lalu kontrak data dan perpindahannya;
- lalu output publik;
- lalu concern operasional besar.

## Peta dokumen

### `aturan-global-arsitektur-api.md`
Buka file ini bila ingin memahami:
- prinsip arsitektur keseluruhan;
- pembagian rumah concern;
- aturan lintas layer yang berlaku umum;
- cara menyelesaikan konflik antar dokumen.

### `transport-boundary.md`
Buka file ini bila ingin memahami:
- apa yang boleh dan tidak boleh dilakukan controller, handler, endpoint adapter, webhook receiver, queue consumer, atau boundary transport lain;
- bagaimana request mentah diproses;
- bagaimana input dipetakan ke kontrak internal;
- bagaimana auth, validasi bentuk, dan error transport ditempatkan.

### `application-service.md`
Buka file ini bila ingin memahami:
- apa itu application service;
- apa yang boleh dan tidak boleh dilakukan service;
- siapa pemilik orchestration use case;
- kapan service terlalu gemuk;
- siapa memegang transaction boundary pada level aplikasi.

### `repository.md`
Buka file ini bila ingin memahami:
- di mana query dan persistence resmi harus berada;
- bentuk input dan output repository;
- read vs write repository;
- bulk access, pagination, streaming, batching, dan lazy loading;
- perbedaan repository dengan DAO, query object, read model, dan persistence adapter.

### `domain-compute.md`
Buka file ini bila ingin memahami:
- rumah resmi rule bisnis murni;
- batas domain compute terhadap service, repository, dan transport;
- apa yang termasuk keputusan domain dan apa yang bukan.

### `kontrak-dto.md`
Buka file ini bila ingin memahami:
- apa itu DTO dan apa yang bukan;
- jenis DTO yang diakui;
- validasi bentuk vs rule bisnis;
- kapan array masih boleh dipakai;
- hubungan DTO dengan request framework, model persistence, dan response.

### `handoff-antar-layer.md`
Buka file ini bila ingin memahami:
- bentuk perpindahan data antar layer;
- payload apa yang boleh dan tidak boleh melintasi boundary;
- bagaimana mencegah framework object, ORM model, query builder, atau lazy loading bocor lintas layer.

### `response-dan-error.md`
Buka file ini bila ingin memahami:
- kontrak response publik;
- bentuk sukses dan error;
- klasifikasi error;
- mapping error ke status transport;
- batas response layer dan error mapping.

### `performa-data-besar.md`
Buka file ini bila ingin memahami:
- kapan data sudah cukup besar untuk butuh strategi khusus;
- pagination, chunking, streaming, cursor, batching, dan bulk write;
- kapan precompute, materialization, dan read model dibenarkan;
- profiling dan benchmark minimum.

### `determinisme-dan-idempotensi.md`
Buka file ini bila ingin memahami:
- retry, rerun, duplicate request, duplicate event, partial failure, dan deduplication;
- kapan operasi dianggap idempotent;
- bagaimana menjaga hasil tetap konsisten.

### `logging-operasional.md`
Buka file ini bila ingin memahami:
- tujuan logging operasional;
- structured logging;
- correlation id;
- level log;
- batas aman terhadap data sensitif;
- bagaimana log dipakai untuk observabilitas tanpa berubah jadi noise.


## Posisi checklist

Checklist review tidak lagi disebar di setiap file utama.

Seluruh checklist review dipusatkan di:
- `checklist-review-arsitektur.md`

Artinya:
- file utama dipakai sebagai panduan arsitektur dan aturan implementasi;
- file checklist dipakai saat review desain, review PR, audit, atau pengecekan cepat.

## Dokumen tambahan

Selain dokumen aturan utama, folder ini juga berisi dokumen pendukung berikut:

- `contoh-implementasi-nyata.md`
  - contoh alur implementasi sehat dan pola buruk sebagai pembanding.
- `template-pengecualian-arsitektur.md`
  - template resmi untuk mencatat penyimpangan yang sah dan terkontrol.
- `template-review-per-jenis-perubahan.md`
  - template review praktis untuk endpoint baru, batch job, perubahan repository, perubahan domain, perubahan DTO, integrasi eksternal, dan pengecualian arsitektur.

## Aturan konflik antar dokumen

- Dokumen yang lebih spesifik mengalahkan dokumen yang lebih umum untuk topik yang sama.
- `aturan-global-arsitektur-api.md` adalah payung prinsip, bukan lisensi untuk mengabaikan aturan spesifik.
- Bila dua dokumen terlihat bertentangan, anggap ada masalah sinkronisasi dokumentasi yang harus dibenahi. Jangan memilih aturan sesuka implementator.
- Bila sebuah file spesifik menyatakan larangan yang lebih tegas daripada dokumen umum, larangan yang lebih tegas harus dipakai.
- Bila ada kebutuhan pengecualian yang sah, tulis pengecualian itu secara eksplisit di dokumen yang paling relevan, bukan sebagai asumsi lisan.

## Aturan istilah

Agar tidak terjadi tafsir liar, istilah berikut dipakai secara konsisten:
- **transport boundary**: pintu masuk komunikasi eksternal seperti controller, handler, webhook receiver, CLI boundary, atau queue consumer boundary;
- **application service**: pengatur alur use case;
- **repository**: rumah resmi query dan persistence;
- **domain compute**: rumah rule bisnis murni dan keputusan domain;
- **DTO**: kontrak data eksplisit antar layer;
- **response layer**: rumah kontrak output publik;
- **persistence layer**: area resmi untuk akses storage, repository, DAO, query object, read model, dan adapter setara;
- **handoff**: perpindahan payload utama antar layer.

## Aturan penerapan

- Jangan membuat file atau komponen baru yang sebenarnya menyalin tanggung jawab file yang sudah ada hanya karena nama terdengar lebih nyaman.
- Jangan memindahkan query ke service, controller, atau DTO dengan alasan praktis sementara.
- Jangan memindahkan rule domain ke repository atau transport boundary dengan alasan cepat selesai.
- Jangan mengandalkan konvensi tim lisan bila aturan tertulis di folder ini sudah ada.
- Bila implementasi butuh deviasi, dokumentasikan deviasi itu.

## Tanda dokumen perlu diperbarui

Dokumentasi di folder ini harus ditinjau ulang bila:
- ada layer baru atau boundary baru;
- ada pola integrasi baru seperti async pipeline, external provider, atau materialized read path yang besar;
- ada kontrak response baru yang berbeda secara resmi;
- ada perubahan besar pada aturan persistence;
- ada pengecualian yang mulai berulang dan bukan lagi kasus sempit;
- istilah di kode mulai tidak cocok dengan istilah pada dokumen.

## Hasil akhir yang diharapkan

Bila folder ini diterapkan dengan benar:
- request mentah berhenti di transport boundary;
- service mengorkestrasi, bukan query;
- repository mengurus persistence, bukan keputusan domain;
- domain compute memegang rule bisnis murni;
- DTO menjaga shape data tetap eksplisit;
- handoff antar layer tetap tertib;
- response publik konsisten;
- data besar diproses dengan strategi yang masuk akal;
- retry dan rerun tidak merusak state;
- logging cukup untuk observabilitas tanpa membocorkan data sensitif.

## Dokumen penyempurnaan akhir

- `pseudo-code-contoh-arsitektur.md`
  - pseudo-code ringkas untuk pola sehat dan pola buruk.
- `glosarium-istilah.md`
  - definisi singkat istilah utama yang dipakai di seluruh paket.
- `panduan-adopsi-minimum.md`
  - panduan bertahap agar tim kecil atau proyek awal tetap bisa memakai dokumen ini tanpa terasa terlalu berat.



## Placement in System Assembly

Folder ini bukan titik awal memahami sistem aktif. `docs/api_architecture/` harus dipakai setelah kontrak domain pada producer dan consumer sudah cukup stabil dan jalur assembly lintas-domain sudah jelas.

Pada sistem aktif, posisi folder ini adalah **translation phase**, yaitu menerjemahkan kontrak domain menjadi struktur implementasi seperti controller, application service, repository, domain compute, dan transport boundary.

## When Architecture Guidance Becomes Mandatory

Architecture guidance menjadi wajib setelah pembaca telah mengunci:

- root ownership dan source-of-truth;
- producer-facing contract pada `market_data`;
- consumer behavior contract pada `watchlist`;
- system assembly dan cross-domain readiness baseline.

Setelah titik itu, implementasi kode tidak boleh dibuat tanpa guardrail arsitektur ini.

## What This Folder May Translate and What It Must Not Redefine

Folder ini boleh:

- menerjemahkan kontrak domain ke struktur layer kode;
- mengatur boundary controller/service/repository/domain compute;
- mengatur determinisme, idempotensi, DTO, transport, dan handoff antar-layer.

Folder ini tidak boleh:

- mengubah arti kontrak producer;
- mengubah perilaku owner consumer;
- membuat policy bisnis baru yang bersaing dengan owner docs.
