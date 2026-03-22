# Domain Compute

Dokumen ini mengatur batas tanggung jawab, larangan, bentuk input dan output, serta aturan implementasi untuk domain compute sebagai rumah resmi rule bisnis murni, kalkulasi domain, klasifikasi, scoring, dan keputusan domain yang tidak boleh bergantung pada detail transport, persistence, atau presentasi.

## Ruang lingkup

Dokumen ini mengatur:
- apa itu domain compute;
- apa yang bukan domain compute;
- jenis logic yang layak ditempatkan di domain compute;
- hubungan domain compute dengan application service, repository, transport boundary, DTO, dan response layer;
- bentuk input dan output domain compute;
- perbedaan antara rule domain, transformasi mekanis, dan orchestration aplikasi;
- aturan determinisme pada logic domain;
- anti-pattern umum pada domain compute.

Dokumen ini tidak mengatur:
- query database;
- kontrak response publik secara rinci;
- detail framework tertentu;
- detail logging operasional secara rinci;
- strategi persistence.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- rule bisnis murni punya rumah yang jelas;
- service tidak berubah menjadi tempat semua keputusan domain;
- repository tidak menyelundupkan keputusan bisnis ke dalam query atau hasil persistence;
- controller atau handler tidak memuat if/else bisnis yang seharusnya hidup lebih bawah;
- kalkulasi, klasifikasi, dan scoring dapat diuji secara fokus;
- keputusan bisnis tetap deterministik, dapat dijelaskan, dan dapat ditinjau.

## Definisi

Domain compute adalah komponen yang menjalankan rule bisnis murni, kalkulasi domain, klasifikasi, scoring, penentuan eligibility, penentuan transisi yang sah, atau keputusan domain lain yang maknanya berasal dari aturan bisnis, bukan dari detail transport, persistence, atau presentasi.

Domain compute ada untuk menjawab pertanyaan seperti:
- apakah aksi ini sah menurut rule bisnis?
- bagaimana nilai domain dihitung?
- kategori apa yang seharusnya dihasilkan?
- state transisi apa yang diizinkan?
- prioritas, ranking, atau klasifikasi apa yang benar menurut rule domain?

Domain compute bukan:
- application service;
- repository;
- controller atau handler;
- response mapper;
- DTO itu sendiri;
- logger umum;
- adapter integrasi;
- tempat query database;
- tempat request framework hidup.

## Prinsip umum

- Domain compute adalah rumah rule bisnis murni.
- Domain compute harus fokus pada makna bisnis, bukan pada protokol, storage, atau format output publik.
- Domain compute harus menerima input yang sudah dipersempit dan relevan terhadap keputusan bisnis yang akan diambil.
- Domain compute harus mengembalikan hasil keputusan yang eksplisit.
- Domain compute harus sedapat mungkin deterministik terhadap input dan konfigurasi yang sah.
- Domain compute tidak boleh memaksa layer lain memahami detail internal kalkulasi melalui side effect tersembunyi.
- Bila suatu rule bisnis cukup penting untuk memengaruhi hasil sistem, rule itu tidak boleh tercecer liar di service, repository, atau controller.

## Aturan keras

- Query database dilarang di domain compute.
- Request framework object dilarang di domain compute.
- Response publik final dilarang dibentuk di domain compute.
- Logging liar, network call liar, cache call liar, queue call liar, dan file system call liar dilarang di domain compute.
- Domain compute dilarang memegang detail tabel, join, index, route, header, session, atau format transport.
- Domain compute dilarang memetakan error ke HTTP status atau bentuk response publik.
- Domain compute dilarang menyimpan side effect persistence sebagai cara utama menghasilkan keputusan.
- Domain compute dilarang bergantung pada lazy loading atau object hidup yang memicu IO tersembunyi.
- Rule bisnis utama tidak boleh dipindahkan ke repository atau service hanya demi kenyamanan implementasi.

## Yang layak ada di domain compute

Logic yang layak ditempatkan di domain compute meliputi:
- scoring;
- klasifikasi;
- ranking bisnis;
- eligibility;
- state transition yang sah;
- validasi bisnis yang maknanya berasal dari aturan domain, bukan bentuk input;
- kalkulasi angka domain;
- penentuan rekomendasi domain;
- policy decision yang murni;
- evaluasi syarat dan batas bisnis;
- normalisasi bermakna bisnis bila itu mengubah makna domain, bukan sekadar bentuk teknis.

## Yang tidak layak ada di domain compute

Logic berikut tidak layak ditempatkan di domain compute:
- parsing request;
- validasi format atau shape input transport;
- query database;
- join tabel;
- pagination;
- sorting teknis persistence;
- serialization publik;
- pemetaan ke response HTTP;
- logging operasional besar-besaran;
- retry policy teknis;
- pengambilan keputusan berdasarkan detail framework atau storage;
- orchestration langkah use case end-to-end.

## Perbedaan rule domain, transformasi mekanis, dan orchestration

### Rule domain
Rule domain menjawab makna bisnis.

Contoh:
- order pada status tertentu tidak boleh dibatalkan;
- investor hanya eligible untuk kategori tertentu bila syarat domain terpenuhi;
- score dihitung dari faktor bisnis tertentu;
- rekomendasi dihasilkan dari kombinasi policy domain.

Ini milik domain compute.

### Transformasi mekanis
Transformasi mekanis hanya mengubah bentuk data, bukan makna bisnis.

Contoh:
- trim string;
- parse tanggal;
- cast integer;
- ubah field kosong menjadi `null`;
- rename field saat mapping.

Ini bukan milik domain compute kecuali perubahan tersebut benar-benar mengubah makna bisnis.

### Orchestration aplikasi
Orchestration menjawab urutan langkah sistem.

Contoh:
- ambil data A lalu B;
- panggil repository;
- panggil domain compute;
- bila sukses, simpan hasil dan kirim response.

Ini milik application service, bukan domain compute.

## Input domain compute

Input domain compute harus fokus dan eksplisit.

Bentuk yang direkomendasikan:
- domain input DTO;
- value object;
- result object yang sudah dipersempit;
- primitive yang sangat terbatas bila rule sangat kecil dan jelas.

Aturan:
- input harus hanya membawa data yang relevan untuk keputusan domain;
- input tidak boleh memaksa domain compute memilah noise transport atau persistence;
- input harus bebas dari object teknis yang memicu side effect tersembunyi;
- field yang dipakai harus jelas dan stabil.

Input domain compute tidak boleh:
- berupa request framework object;
- berupa ORM model mentah;
- berupa query builder;
- berupa payload campur-aduk yang memaksa domain compute menebak isi penting;
- berupa object yang bergantung pada koneksi atau context teknis tertentu.

## Output domain compute

Output domain compute harus eksplisit dan fokus pada hasil keputusan domain.

Bentuk yang direkomendasikan:
- domain result DTO;
- value object;
- result object;
- primitive yang sangat terbatas bila maknanya tetap jelas.

Aturan:
- output harus menyatakan hasil keputusan domain secara eksplisit;
- output tidak boleh menjadi response publik final;
- output tidak boleh memerlukan query tambahan untuk dipahami;
- output harus aman dipakai service tanpa side effect tersembunyi.

Output domain compute tidak boleh:
- berupa model persistence mentah;
- berupa response HTTP final;
- berupa object yang saat dibaca memicu IO;
- berupa shape liar yang berubah-ubah tanpa kontrak.

## Hubungan dengan application service

Application service adalah pengatur alur use case. Domain compute adalah rumah rule bisnis.

Aturan:
- service menyiapkan input domain compute;
- domain compute menjalankan keputusan bisnis murni;
- service meneruskan hasil domain ke langkah berikutnya;
- service tidak boleh menyerap rule domain berat ke if/else panjang hanya karena “lebih cepat”;
- domain compute tidak boleh mengambil alih orchestration penuh dari service.

## Hubungan dengan repository

Repository menyediakan data yang dibutuhkan oleh domain compute melalui service atau kontrak resmi lain.

Aturan:
- repository boleh mengambil data;
- domain compute boleh memakai data yang sudah disiapkan;
- domain compute tidak boleh mengambil data sendiri;
- repository tidak boleh mengambil alih keputusan domain hanya karena sebagian rule bisa ditulis sebagai filter query.

### Batas penting
Filter persistence yang sekadar mempersempit kandidat bukan berarti keputusan domain telah selesai.  
Keputusan domain tetap milik domain compute bila maknanya adalah hasil bisnis, bukan hasil teknis query semata.

## Hubungan dengan transport boundary

Transport boundary boleh memvalidasi bentuk input dan auth/authz boundary tertentu, tetapi tidak boleh menjalankan rule domain utama.

Aturan:
- controller atau handler tidak boleh menjadi tempat scoring, klasifikasi, atau eligibility;
- payload untuk domain compute harus sudah dibentuk secara eksplisit sebelum sampai ke service atau domain;
- domain compute tidak boleh tahu dari route, header, query param, atau framework mana input berasal.

## Hubungan dengan response layer

Response layer mempresentasikan hasil. Domain compute menentukan hasil bisnis.

Aturan:
- domain compute tidak boleh membentuk response publik final;
- domain compute tidak boleh menentukan envelope sukses atau error publik;
- hasil domain boleh dipetakan ke response layer melalui service, tetapi pemetaan presentasi tetap harus eksplisit.

## Determinisme di domain compute

Domain compute sebaiknya deterministik terhadap input dan konfigurasi resmi yang diberikan.

Aturan:
- input yang sama dan policy version yang sama harus menghasilkan hasil yang konsisten;
- bila rule bergantung pada waktu, sumber waktu harus eksplisit;
- bila rule bergantung pada konfigurasi, versi konfigurasi harus dapat ditelusuri;
- random source harus dihindari kecuali memang bagian resmi dari rule dan dapat dijelaskan;
- domain compute tidak boleh diam-diam mengambil `now()`, random, atau data eksternal live tanpa kontrak yang jelas.

## Konfigurasi dan policy

Domain compute boleh memakai konfigurasi atau policy resmi, tetapi aturannya harus jelas.

Aturan:
- konfigurasi yang memengaruhi keputusan domain harus eksplisit;
- versi policy, threshold, weight, atau parameter domain harus dapat ditelusuri bila hasil bisnis bergantung padanya;
- domain compute tidak boleh membaca env atau config liar langsung dari mana-mana tanpa kontrak yang jelas;
- parameter yang mengubah hasil bisnis tidak boleh tersembunyi.

## Error handling di domain compute

Domain compute boleh:
- mengembalikan domain result yang menyatakan valid, invalid, allowed, denied, eligible, ineligible, atau hasil domain sejenis;
- melempar atau mengembalikan domain exception/error object yang maknanya bisnis, bila arsitektur resmi mengizinkan.

Domain compute tidak boleh:
- memetakan error ke response publik final;
- menelan kegagalan penting lalu mengubahnya menjadi hasil palsu;
- mencampur error teknis persistence atau transport seolah domain error murni.

## Kapan rule harus dipindah ke domain compute

Sebuah logic harus dipertimbangkan pindah ke domain compute bila:
- mengandung keputusan bisnis nyata;
- dipakai ulang di lebih dari satu use case;
- membuat service dipenuhi if/else bisnis panjang;
- membuat repository mulai memutuskan hasil bisnis;
- perlu diuji terpisah sebagai policy atau rule;
- hasilnya memengaruhi keluaran bisnis utama sistem.

## Kapan logic tidak perlu jadi domain compute terpisah

Tidak semua if harus dipindahkan ke file domain terpisah.

Tidak perlu dipisah bila:
- logikanya sangat kecil, murni lokal, dan tidak mengandung makna bisnis yang kuat;
- hanya sekadar penggabungan nilai mekanis tanpa policy bisnis;
- tidak dipakai ulang dan tidak membuat boundary kabur.

Tetapi bila logic kecil itu tumbuh atau mulai dipakai ulang, pisahkan sebelum service berubah jadi god component.

## Contoh rule domain vs bukan

### Termasuk domain compute
- menentukan apakah pesanan boleh dibatalkan;
- menghitung score dari faktor bisnis;
- menentukan segmentasi pelanggan;
- menentukan prioritas proses berdasarkan aturan bisnis;
- menentukan hasil rekomendasi atau klasifikasi internal.

### Bukan domain compute
- parse tanggal dari request;
- ambil data order dari database;
- bentuk JSON response;
- pilih query pagination;
- tulis log request;
- validasi bahwa field body wajib ada.

## Naming domain compute

Penamaan harus menjelaskan keputusan atau kalkulasi domainnya.

Contoh yang lebih sehat:
- `OrderCancellationPolicy`
- `InvestorEligibilityEvaluator`
- `RiskScoringCalculator`
- `SettlementTransitionRule`
- `WatchlistClassifier`

Contoh yang buruk:
- `CommonLogic`
- `DataProcessor`
- `GeneralHelper`
- `DecisionService`

## Anti-pattern

- Rule domain utama ditulis di controller.
- Service dipenuhi if/else bisnis panjang.
- Repository menentukan recommendation atau eligibility.
- Domain compute query database sendiri.
- Domain compute membaca request framework object.
- Domain compute membaca `now()` atau random secara liar tanpa kontrak.
- Domain compute membentuk response publik final.
- Domain compute dipakai sebagai helper campur-aduk yang menampung semua logic non-query.
- Kalkulasi domain tersebar di banyak file tanpa rumah resmi.
- Policy domain diparameterisasi diam-diam tanpa dokumentasi atau versi yang jelas.

## Active-System Domain Compute Shape

Untuk sistem aktif, kandidat canonical domain compute minimal adalah:
- PLAN engine/builder
- RECOMMENDATION engine/builder
- CONFIRM evaluator/overlay engine

## Clean Inputs Required Before Domain Compute Runs

Sebelum domain compute berjalan, input harus sudah:
- berasal dari intake producer-facing yang sah atau artifact consumer yang sah;
- bebas dari request transport mentah;
- bebas dari row query mentah yang dijadikan kontrak aktif;
- cukup stabil sebagai compute input DTO/result object.

## Weekly Swing Example

Pada weekly_swing:
- `WsPlanEngine` menghitung keputusan PLAN dari input bersih;
- `WsRecommendationEngine` menghitung recommendation hanya dari PLAN yang sah;
- `WsConfirmOverlayEngine` mengevaluasi confirm dari binding PLAN candidate + confirm input yang sah.

Domain compute tidak boleh:
- query `market_data` langsung;
- menulis persistence artifact langsung;
- membentuk response DTO final.

