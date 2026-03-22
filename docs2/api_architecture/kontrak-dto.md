# Kontrak DTO

Dokumen ini mengatur definisi, batas tanggung jawab, jenis yang diakui, larangan, dan kontrak perubahan untuk DTO sebagai kontrak data eksplisit antar layer dalam arsitektur API dan backend.

## Ruang lingkup

Dokumen ini mengatur:
- apa itu DTO;
- apa yang bukan DTO;
- tujuan penggunaan DTO;
- jenis DTO yang diakui;
- isi yang boleh dan tidak boleh ada di DTO;
- perbedaan validasi bentuk data dan rule bisnis;
- kapan array masih boleh dipakai dan kapan tidak;
- aturan perubahan kontrak DTO;
- hubungan DTO dengan request framework, model persistence, entity, dan response object.

Dokumen ini tidak mengatur:
- rule domain yang murni;
- detail response publik final;
- detail query database;
- keputusan layer transport;
- detail implementasi framework tertentu.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- perpindahan data antar layer punya bentuk yang eksplisit;
- kontrak data stabil dan tidak berubah liar;
- array bebas tidak menjadi payload utama di seluruh sistem;
- typo key, field tersembunyi, dan shape tak konsisten berkurang;
- refactor lebih aman;
- test kontrak data dapat dibuat dengan jelas;
- DTO tidak disalahgunakan menjadi tempat side effect, query, atau business logic.

## Definisi

DTO adalah Data Transfer Object, yaitu object yang dipakai untuk membawa data secara eksplisit dari satu layer ke layer lain tanpa membawa perilaku aplikasi yang bukan tugasnya.

DTO ada untuk mengangkut data, bukan untuk mengendalikan sistem.

DTO bukan:
- request framework object;
- model ORM atau persistence model;
- entity domain;
- repository;
- service;
- presenter atau response mapper;
- object utilitas serba guna;
- tempat side effect;
- tempat keputusan bisnis utama.

## Prinsip umum

- DTO adalah kontrak data eksplisit.
- DTO harus fokus pada shape data, bukan pada perilaku aplikasi.
- DTO harus stabil, dapat diprediksi, dan mudah diuji.
- DTO tidak boleh menjadi saluran diam-diam untuk menyuntik detail framework, persistence, atau service ke layer lain.
- DTO tidak boleh tumbuh menjadi “object serbaguna” yang memegang terlalu banyak tanggung jawab.
- Bila suatu alur sudah menetapkan DTO sebagai kontrak resmi, maka payload utama antar layer pada alur tersebut tidak boleh kembali ke array bebas tanpa alasan resmi dan terdokumentasi.

## Tujuan penggunaan DTO

DTO digunakan untuk:
- membentuk kontrak input dan output antar layer;
- memastikan field yang dipindahkan eksplisit;
- mengurangi array campur-aduk;
- mendukung validasi bentuk data;
- menjaga konsistensi field name dan tipe data;
- memudahkan mapping dari satu boundary ke boundary lain;
- mempermudah test dan refactor.

DTO tidak digunakan untuk:
- query database;
- memanggil service;
- membuat keputusan domain;
- menjalankan proses;
- menyimpan state persistence;
- memodelkan perilaku domain yang kaya.

## Jenis DTO yang diakui

Sistem boleh memakai beberapa jenis DTO. Jenis ini harus dibedakan dengan jelas agar tidak semua object data diberi nama DTO tanpa arti.

### Request DTO
Dipakai untuk membawa data dari transport boundary ke application service atau use case.

Contoh:
- payload input create/update;
- input action tertentu;
- input yang sudah lolos validasi bentuk minimum.

### Command or Input DTO
Dipakai untuk membawa perintah atau input eksplisit ke use case atau proses aplikasi internal.

Biasanya dipakai saat layer transport bukan satu-satunya sumber input.

### Query or Filter DTO
Dipakai untuk membawa parameter pencarian, filter, sort, pagination, dan batasan query secara eksplisit.

### Result or Read DTO
Dipakai untuk membawa hasil baca dari persistence layer atau application service dalam bentuk yang sudah dipersempit dan eksplisit.

### Response DTO
Dipakai untuk membawa output aplikasi ke response layer sebelum dibentuk menjadi response publik final.

### Batch or Process DTO
Dipakai untuk payload antar proses, job, event internal, atau batch step, bila sistem memang membutuhkannya.

## Yang bukan DTO

Hal berikut tidak boleh dianggap DTO hanya karena bentuknya object:
- request object dari framework;
- ORM model;
- entity domain yang memuat perilaku domain;
- response HTTP final;
- query builder;
- cache object;
- service locator payload;
- object yang punya side effect;
- object yang menyimpan koneksi, repository, logger, HTTP client, atau dependency teknis lain.

## Isi yang diperbolehkan dalam DTO

DTO boleh berisi:
- field data yang eksplisit;
- constructor;
- static factory atau named constructor;
- getter sederhana bila memang gaya implementasi membutuhkannya;
- casting mekanis;
- normalisasi mekanis;
- validasi bentuk data;
- serialization atau conversion yang terbatas dan jelas, seperti ke array atau JSON-safe structure, bila memang diperlukan oleh kontrak sistem.

### Contoh normalisasi mekanis yang boleh
- trim string;
- cast integer, float, bool, atau date representation;
- normalisasi enum input ke bentuk internal yang resmi;
- normalisasi field opsional ke `null`;
- mengurutkan field internal tertentu bila itu murni kebutuhan stabilitas representasi, bukan keputusan bisnis.

## Isi yang dilarang dalam DTO

DTO tidak boleh berisi:
- query database;
- call ke repository;
- call ke service;
- call ke HTTP client;
- call ke cache, logger, queue, file system, atau network;
- side effect;
- keputusan bisnis;
- kalkulasi domain utama;
- method `handle`, `process`, `execute`, `decide`, `save`, `load`, `fetch`, `sync`, `publish`, atau method lain yang menunjukkan perilaku aplikasi;
- dependency injection container;
- state tersembunyi yang memengaruhi hasil sistem.

## Validasi bentuk data vs rule bisnis

Perbedaan ini wajib tegas. DTO hanya boleh memegang validasi bentuk data, bukan rule bisnis.

### Validasi bentuk data yang boleh ada di DTO
- field wajib ada atau nullable;
- tipe data harus sesuai;
- string tidak boleh kosong bila kontraknya memang mewajibkan itu;
- format tanggal harus benar;
- nilai enum harus termasuk daftar yang diizinkan;
- array harus berbentuk list atau map yang sesuai;
- nested field harus punya shape yang benar.

### Rule bisnis yang tidak boleh ada di DTO
- batas nilai berdasarkan aturan bisnis;
- eligibility;
- scoring;
- klasifikasi;
- status transisi yang sah atau tidak sah;
- keputusan boleh atau tidak boleh menjalankan aksi;
- logika approval;
- prioritas bisnis;
- penentuan hasil rekomendasi;
- keputusan domain lain yang bergantung pada makna bisnis data.

### Contoh pembeda
Boleh di DTO:
- `birth_date` harus format `YYYY-MM-DD`.
- `status` harus salah satu dari nilai enum resmi.
- `quantity` harus integer.

Tidak boleh di DTO:
- `quantity` minimal 100 karena aturan promo.
- `status` tertentu tidak boleh dipakai oleh user kategori tertentu.
- order tidak boleh dibatalkan bila sudah `settled`.

## Kapan array masih boleh dipakai

Array masih boleh dipakai bila:
- hanya hidup sangat lokal di dalam satu fungsi atau satu langkah yang sangat terbatas;
- belum melintasi boundary antar layer;
- kompleksitasnya sangat kecil dan kontraknya belum perlu diresmikan;
- dipakai sebagai hasil sementara dari parsing internal yang langsung dibungkus menjadi DTO resmi.

Array tidak boleh menjadi payload utama bila:
- data melintasi boundary antar layer;
- field sudah cukup banyak atau rawan typo;
- kontrak data perlu stabil;
- alur tersebut sudah menetapkan DTO resmi;
- output akan dipakai ulang di lebih dari satu tempat.

### Aturan keras array vs DTO

- Begitu suatu handoff resmi memakai DTO, payload utama untuk handoff itu tidak boleh kembali ke array bebas.
- Array bebas tidak boleh dipakai untuk menghindari disiplin kontrak data.
- Array bebas tidak boleh menjadi alasan untuk menyelundupkan field tak terdokumentasi.

## Input dan output DTO harus eksplisit

DTO harus membuat field penting terlihat jelas.

Aturan:
- field wajib harus tampak jelas;
- field opsional harus tampak jelas;
- tipe data harus dapat ditentukan dengan tegas;
- nested structure harus dapat dijelaskan;
- tidak boleh ada field “misterius” yang kadang ada kadang tidak tanpa kontrak.

## Immutability dan perubahan data

DTO sebaiknya immutable atau mendekati immutable.

Prinsip:
- setelah dibuat, DTO sebaiknya tidak berubah sembarangan;
- bila perubahan data diperlukan, lebih aman membuat instance baru;
- setter bebas yang membuat shape berubah-ubah harus dihindari;
- jika implementasi tidak sepenuhnya immutable, batas mutasi harus sangat jelas dan terbatas.

Tujuannya:
- menjaga prediktabilitas;
- mencegah perubahan tak sengaja di tengah alur;
- membuat test lebih mudah.

## Constructor, factory, dan pembentukan DTO

Sistem boleh memilih constructor langsung, named constructor, static factory, atau mapper resmi. Pilihannya harus konsisten per gaya proyek.

Aturan:
- pembentukan DTO harus eksplisit;
- input pembentukan DTO harus tervalidasi bentuk minimum;
- factory tidak boleh menjadi service terselubung;
- mapper pembentuk DTO tidak boleh menyelundupkan rule bisnis.

## Serialization dan conversion

DTO boleh diubah ke bentuk serializable bila memang diperlukan.

Aturan:
- conversion harus mempertahankan makna field;
- nama field hasil serialization harus konsisten dengan kontraknya;
- DTO tidak boleh berubah makna saat di-convert;
- conversion tidak boleh menjadi tempat logika bisnis baru;
- serialization publik final tetap menjadi tanggung jawab response layer bila itu memang arsitektur resmi.

## Perubahan kontrak DTO

Perubahan DTO adalah perubahan kontrak data. Ini harus diperlakukan serius.

### Perubahan yang harus ditinjau
- tambah field;
- hapus field;
- rename field;
- ubah tipe field;
- ubah default behavior;
- ubah makna field;
- ubah nested structure.

### Aturan perubahan
- perubahan harus mempertimbangkan consumer yang memakai DTO tersebut;
- dokumentasi terkait harus diperbarui;
- test yang bergantung pada shape DTO harus diperbarui;
- perubahan tidak boleh dilakukan diam-diam hanya karena compiler atau runtime masih lolos;
- bila DTO dipakai lintas proses atau lintas service, backward compatibility harus dipertimbangkan lebih ketat.

## Hubungan DTO dengan request framework

DTO bukan request framework object.

Aturan:
- request framework hanya hidup di transport boundary;
- DTO menerima data yang sudah dipersempit dan dinormalisasi sesuai kontrak;
- DTO tidak boleh mewarisi request framework object;
- DTO tidak boleh menyimpan referensi ke request mentah.

## Hubungan DTO dengan model persistence

DTO bukan ORM model atau persistence row object mentah.

Aturan:
- DTO tidak boleh membawa kemampuan lazy loading;
- DTO tidak boleh mengandung koneksi persistence;
- DTO tidak boleh dipakai sebagai alias terselubung untuk model database;
- bila data berasal dari repository, hasilnya harus dipersempit sesuai kontrak DTO, bukan model persistence mentah yang dilempar lintas layer.

## Hubungan DTO dengan entity domain

DTO bukan entity domain.

Perbedaan utamanya:
- DTO membawa data;
- entity domain membawa identitas dan/atau perilaku domain;
- DTO tidak menjadi pusat invarian domain yang kaya;
- entity domain tidak boleh direduksi begitu saja menjadi DTO bila yang dibutuhkan sebenarnya perilaku domain.

## Hubungan DTO dengan response publik

Response DTO boleh dipakai sebelum response layer, tetapi response publik final tetap harus mengikuti kontrak response resmi.

Aturan:
- DTO internal tidak otomatis sama dengan shape response publik;
- bila response DTO memang menjadi sumber response publik, mapping-nya harus tetap eksplisit;
- perubahan response DTO tidak boleh dilakukan tanpa mempertimbangkan kontrak response publik.

## Penamaan DTO

Penamaan DTO harus menjelaskan perannya, bukan sekadar menempelkan akhiran `Dto` ke semua object.

Contoh yang lebih jelas:
- `CreateOrderRequestDto`
- `OrderFilterDto`
- `OrderSummaryResultDto`
- `CancelOrderResponseDto`

Contoh yang buruk:
- `DataDto`
- `CommonDto`
- `GeneralDto`
- `PayloadDto`

## Anti-pattern

- DTO query database.
- DTO memanggil service.
- DTO memegang logger, repository, atau HTTP client.
- DTO memiliki method `process`, `decide`, `save`, atau method perilaku aplikasi lain.
- DTO dipakai sebagai dump object untuk field apa saja.
- DTO mewarisi request framework object.
- DTO sebenarnya adalah ORM model yang diganti nama.
- DTO dipakai untuk menyelundupkan field tak terdokumentasi.
- DTO berubah-ubah shape-nya tergantung cabang logika tanpa kontrak resmi.
- DTO dipakai sebagai pengganti entity domain hanya agar implementasi terlihat sederhana.

## Contoh DTO yang sehat

DTO yang sehat umumnya:
- hanya membawa field yang perlu;
- punya constructor atau factory yang jelas;
- tidak punya side effect;
- punya validasi bentuk yang tegas bila dibutuhkan;
- tidak tahu dari mana data berasal;
- tidak tahu ke mana data akan dipersist atau dipresentasikan;
- mudah diuji dengan data statis.

## Active-System DTO Categories

Untuk sistem aktif, boundary object minimal harus dibedakan menjadi:

1. **Upstream intake DTO / result object**
   - berasal dari producer-facing intake read;
   - dipakai sebelum domain compute berjalan.

2. **Compute input DTO**
   - bentuk bersih yang masuk ke PLAN / RECOMMENDATION / CONFIRM compute.

3. **Artifact persistence payload**
   - bentuk data yang siap disimpan sebagai artifact consumer.

4. **Response DTO**
   - bentuk keluaran untuk transport/API/read consumer.

## Intake DTO vs Compute Input vs Response DTO

| Object category | Source layer | Consumer layer | Allowed content | Forbidden content |
|---|---|---|---|---|
| Upstream intake DTO | intake read adapter | application service / domain compute preparation | publication-aware producer-facing data yang sudah dinormalisasi | request mentah, response formatting |
| Compute input DTO | application preparation/binder | domain compute | input bersih untuk keputusan internal | row query mentah, ORM entity liar |
| Artifact persistence payload | assembler/application | repository/persistence adapter | artifact yang sudah selesai diputuskan | response-only decoration, transport concern |
| Response DTO | presenter/transport shaping | transport/API consumer | data exposure untuk consumer | rule bisnis baru, write concern |

## What Must Never Stay as Untyped Arrays

Yang tidak boleh dibiarkan sebagai array liar lintas layer:
- hasil intake upstream aktif;
- input compute PLAN / RECOMMENDATION / CONFIRM;
- payload artifact yang dipersist;
- response contract API utama.

