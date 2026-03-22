# Application Service

Dokumen ini mengatur batas tanggung jawab, larangan, dan kontrak implementasi untuk application service atau use case orchestration dalam arsitektur API dan backend.

## Ruang lingkup

Dokumen ini mengatur:
- apa itu application service;
- tanggung jawab utamanya;
- apa yang boleh dan tidak boleh dilakukan oleh application service;
- hubungan application service dengan transport boundary, repository, domain compute, dan response layer;
- batas transaksi aplikasi;
- pola error handling pada level application service;
- indikator service yang sehat dan indikator service yang mulai rusak.

Dokumen ini tidak mengatur:
- detail query database;
- kontrak response publik;
- bentuk teknis DTO secara rinci;
- rule domain yang murni;
- format log operasional;
- detail implementasi framework tertentu.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- alur use case memiliki satu pengendali yang jelas;
- logic aplikasi tidak tercecer ke controller, repository, atau response layer;
- application service tidak berubah menjadi tempat semua logic;
- transaction boundary tetap terkendali;
- service dapat diuji sebagai pengatur alur, bukan sebagai tempat side effect tak terbatas;
- perbedaan antara orchestration, persistence, dan domain decision tetap tegas.

## Definisi

Application service adalah komponen yang mengorkestrasi satu use case atau satu kelompok use case yang sangat berdekatan. Application service bertugas mengatur urutan langkah aplikasi, memanggil dependency yang relevan, menjaga batas transaksi bila diperlukan, dan mengembalikan hasil aplikasi dalam bentuk yang siap diteruskan ke layer berikutnya.

Application service bukan:
- controller;
- repository;
- domain compute;
- presenter atau response mapper;
- validator framework;
- helper serba guna.

## Prinsip umum

- Application service adalah pengatur alur aplikasi, bukan rumah utama rule domain.
- Application service boleh membuat keputusan orchestration, tetapi tidak boleh mengambil alih seluruh logika domain.
- Application service harus menerima input yang eksplisit dan mengembalikan output yang eksplisit.
- Application service tidak boleh bergantung pada detail transport seperti request object framework.
- Application service tidak boleh bergantung pada detail persistence seperti SQL, nama tabel, bentuk join, atau strategi index.
- Application service harus menjaga agar setiap dependency dipakai untuk perannya masing-masing, bukan saling tumpang tindih.

## Tanggung jawab utama

Application service bertanggung jawab untuk:
- menerima input use case dari layer di atas dalam bentuk yang sudah eksplisit;
- memvalidasi prasyarat aplikasi tingkat alur bila memang itu bukan validasi bentuk transport dan bukan rule domain murni;
- menentukan urutan langkah eksekusi;
- memanggil repository untuk kebutuhan baca atau tulis data;
- memanggil domain compute untuk rule, perhitungan, klasifikasi, atau keputusan bisnis murni;
- menggabungkan hasil dari dependency menjadi hasil use case;
- mengelola transaction boundary bila use case tersebut memerlukannya;
- mengembalikan output aplikasi yang siap dipetakan ke response layer;
- meneruskan atau mengklasifikasikan error aplikasi sesuai kontrak sistem.

## Yang bukan tanggung jawab application service

Application service bukan tempat untuk:
- menyusun query database;
- memahami detail tabel, index, join, window function, atau hint query;
- melakukan kalkulasi domain yang berat dan murni;
- membentuk response publik final;
- melakukan serialization publik;
- membaca request framework secara langsung;
- mengakses cache, network, queue, logger, atau file system secara liar tanpa alasan use case yang jelas;
- menjadi gudang utility umum lintas konteks.

## Aturan keras

- Application service wajib memiliki tujuan use case yang jelas.
- Application service wajib punya input dan output yang eksplisit.
- Application service dilarang melakukan query database langsung.
- Application service dilarang memanggil ORM query builder, raw SQL, atau bentuk akses persistence lain secara langsung.
- Application service dilarang menyimpan rule domain yang seharusnya berada di domain compute.
- Application service dilarang menerima request object framework sebagai kontrak utama.
- Application service dilarang mengembalikan model persistence mentah sebagai output lintas layer.
- Application service dilarang mengubah response HTTP atau status transport secara langsung, kecuali memang arsitektur sistem menetapkannya secara eksplisit dan konsisten.
- Application service dilarang menjadi titik penyebaran keputusan teknis yang seharusnya terpusat, seperti error-to-response mapping.

## Input application service

Input application service harus eksplisit. Bentuk yang direkomendasikan:
- request DTO;
- command/input DTO;
- criteria/filter object;
- primitive yang sangat terbatas dan jelas, bila kompleksitasnya memang belum memerlukan object khusus.

Input application service tidak boleh:
- berupa request framework object mentah;
- berupa payload campur-aduk yang memaksa service menebak isi;
- berupa model ORM mentah dari layer transport;
- bergantung pada field tersembunyi atau side channel yang tidak menjadi bagian kontrak.

## Output application service

Output application service harus eksplisit. Bentuk yang direkomendasikan:
- response DTO;
- result object;
- output object yang secara resmi diakui sebagai kontrak lintas layer.

Output application service tidak boleh:
- berupa response HTTP final publik secara langsung tanpa response layer resmi;
- berupa model ORM mentah;
- berupa object yang memicu lazy loading atau side effect tersembunyi;
- berupa shape liar yang berbeda-beda untuk use case yang setara tanpa alasan resmi.

## Hubungan dengan transport boundary

Transport boundary bertugas menerima request mentah, melakukan validasi bentuk minimum sesuai kontrak transport, dan membentuk input eksplisit untuk application service.

Aturan hubungan:
- Request framework harus berhenti di transport boundary.
- Application service hanya menerima data yang sudah dibentuk secara eksplisit.
- Application service tidak boleh membaca header, session, request bag, uploaded file wrapper, atau object framework lain secara langsung kecuali itu memang dibungkus resmi oleh boundary transport.
- Keputusan yang murni teknis transport tidak boleh bocor ke service tanpa alasan yang jelas.

## Hubungan dengan repository

Repository adalah rumah resmi untuk query dan persistence.

Aturan hubungan:
- Application service boleh meminta repository membaca atau menulis data.
- Application service tidak boleh menyusun query langsung.
- Application service tidak boleh tahu lebih dari yang diperlukan tentang tabel, kolom, index, hint, atau strategi query.
- Application service harus meminta kebutuhan data secara eksplisit, bukan mengirim object liar lalu memaksa repository menebak.
- Application service boleh melakukan orchestration atas beberapa repository bila use case memang membutuhkannya, tetapi tidak boleh mengambil alih detail persistence.

## Hubungan dengan domain compute

Domain compute adalah rumah resmi untuk rule bisnis murni, perhitungan, klasifikasi, scoring, dan keputusan domain yang seharusnya deterministik.

Aturan hubungan:
- Application service memanggil domain compute untuk keputusan bisnis yang murni.
- Application service boleh menyiapkan input domain compute agar fokus dan eksplisit.
- Application service tidak boleh menyerap rule domain yang berat ke dalam blok if/else panjang hanya demi kenyamanan implementasi.
- Domain compute tidak boleh digantikan oleh service hanya karena service sudah “terlanjur tahu banyak”.

## Hubungan dengan response layer

Response layer bertugas mengubah hasil aplikasi menjadi output publik.

Aturan hubungan:
- Application service mengembalikan hasil aplikasi, bukan response publik final.
- Application service tidak boleh membentuk envelope publik seenaknya.
- Application service tidak boleh menentukan detail penyajian yang seharusnya menjadi urusan response layer.
- Application service boleh mengembalikan error aplikasi terklasifikasi, tetapi tidak boleh memetakan semuanya menjadi format response publik final secara liar.

## Batas transaksi

Transaction boundary harus jelas. Dalam banyak sistem, application service adalah tempat yang paling wajar untuk mengelola transaksi pada level use case.

### Aturan transaksi

- Use case yang melakukan beberapa perubahan state yang harus atomik harus memiliki transaction boundary yang jelas.
- Application service boleh membuka dan menutup transaksi bila use case tersebut memang pemilik alur tulis.
- Repository tidak boleh diam-diam menetapkan transaction boundary sendiri tanpa kontrak yang jelas.
- Application service tidak boleh membuka transaksi terlalu lebar sehingga mencakup proses yang tidak perlu berada dalam satu transaksi.
- Proses network, panggilan eksternal, atau pekerjaan lambat sebaiknya tidak ditahan di dalam transaksi database tanpa alasan yang sangat kuat dan terdokumentasi.

### Indikator transaksi sehat

- ruang lingkup transaksi sesuai kebutuhan use case;
- hanya langkah yang memang perlu atomik yang dimasukkan;
- dependency luar yang lambat tidak memperpanjang lock tanpa alasan;
- batas mulai dan akhir transaksi bisa dijelaskan dengan jelas.

## Service memanggil service lain

Service boleh memanggil service lain hanya bila ada alasan arsitektural yang jelas. Ini bukan default.

### Diperbolehkan bila:
- ada sub-use-case yang memang berdiri jelas dan dipakai ulang secara sah;
- pemanggilan tidak menciptakan siklus dependensi;
- tanggung jawab tetap bisa dibedakan;
- transaction boundary dan error flow tetap jelas.

### Harus dihindari bila:
- service lain dipakai hanya untuk “meminjam sebagian kode”;
- chaining service membuat alur sulit ditelusuri;
- service atas dan bawah memiliki tanggung jawab yang sebenarnya tumpang tindih;
- keputusan domain tersebar di beberapa service tanpa pusat yang jelas.

### Larangan
- Tidak boleh membuat jaringan service-to-service yang membuat siapa pemilik use case menjadi kabur.
- Tidak boleh memakai service lain sebagai pengganti repository, domain compute, atau helper teknis.

## Kapan service perlu dipecah

Satu service harus dipertimbangkan untuk dipecah bila:
- menangani terlalu banyak use case berbeda;
- memiliki terlalu banyak dependency;
- memiliki branching yang panjang dan sulit dibaca;
- memegang baca, tulis, validasi alur, mapping teknis, dan rule domain sekaligus;
- sulit diuji tanpa setup yang besar;
- perubahan kecil pada satu flow sering merusak flow lain;
- namanya terlalu umum dan tidak menjelaskan tugas inti.

### Tanda service mulai rusak

- nama seperti `GeneralService`, `CommonService`, `ProcessService`, atau `ManagerService` tanpa batas yang jelas;
- satu method sangat panjang dan berisi query terselubung, rule domain, response shaping, dan logging campur;
- ada terlalu banyak boolean flag untuk mengubah perilaku method;
- output method berubah-ubah tergantung cabang tanpa kontrak tetap;
- service menjadi tempat semua integrasi teknis hanya karena “mudah diletakkan di sana”.

## Read use case dan write use case

Read use case dan write use case boleh memiliki karakter yang berbeda, tetapi prinsip boundary tetap sama.

### Read use case
- fokus pada pengambilan dan penyusunan hasil baca;
- tidak boleh diam-diam melakukan perubahan state;
- boleh memakai repository read model yang lebih optimal bila itu bagian resmi dari persistence layer.

### Write use case
- fokus pada perubahan state yang sah;
- transaction boundary harus lebih diperhatikan;
- idempotensi dan partial failure harus dipikirkan bila operasi dapat diulang atau di-retry;
- efek samping harus dikendalikan dengan jelas.

## Validasi pada application service

Validasi di application service hanya boleh mencakup prasyarat alur aplikasi yang memang bukan:
- validasi bentuk data transport;
- rule domain murni.

Contoh yang bisa berada di level service:
- memastikan resource yang diperlukan memang ada sebelum alur dilanjutkan;
- memastikan dependency minimum untuk menjalankan use case tersedia;
- memastikan urutan langkah aplikasi terpenuhi.

Contoh yang tidak boleh diletakkan di service bila sudah ada rumah yang lebih tepat:
- format tanggal salah;
- enum input tidak valid;
- scoring domain;
- klasifikasi bisnis;
- keputusan eligibility murni.

## Error handling di application service

Application service boleh:
- menangkap error tertentu untuk menambah konteks aplikasi;
- mengubah exception teknis rendah menjadi error aplikasi yang lebih bermakna;
- menghentikan alur use case saat prasyarat tidak terpenuhi;
- memastikan rollback terjadi bila kontrak transaksi mewajibkannya.

Application service tidak boleh:
- memetakan error langsung ke shape response publik secara liar;
- menelan semua error lalu mengganti semuanya menjadi hasil sukses palsu;
- menyembunyikan error penting hanya agar flow terlihat “aman”;
- mencampur klasifikasi error dengan detail presentasi publik.

## Idempotensi dan efek samping

Bila use case write dapat dipanggil ulang, di-retry, atau dijalankan dalam job/scheduler, service harus menghormati kontrak idempotensi sistem.

Aturan:
- service tidak boleh mengasumsikan satu request hanya akan datang sekali;
- service harus bekerja sama dengan repository, domain, dan mekanisme identitas operasi bila kontrak use case membutuhkan idempotensi;
- side effect seperti publish event, enqueue job, atau panggilan eksternal tidak boleh dikelola secara sembarangan bila risiko duplikasi ada.

## Anti-pattern

- Service menulis query langsung.
- Service memanggil ORM query builder langsung.
- Service memuat raw SQL.
- Service menjadi rumah rule domain utama.
- Service menerima request framework object sebagai kontrak utama.
- Service mengembalikan model persistence mentah.
- Service membentuk response publik final sendiri.
- Service memanggil banyak dependency tanpa batas yang jelas hingga menjadi god service.
- Service memakai banyak flag untuk mengontrol banyak mode perilaku dalam satu method.
- Service menjadi tempat shortcut teknis yang melanggar boundary “untuk sementara”, lalu permanen.

## Active-System Orchestration Example

Dalam flow aktif `market_data -> watchlist`, application service yang sehat melakukan hal berikut:
- menerima intent/use-case dari boundary;
- memanggil producer-facing intake reader yang sah;
- meneruskan input bersih ke domain compute PLAN / RECOMMENDATION / CONFIRM;
- memanggil persistence adapter bila artifact perlu disimpan;
- menyusun response lewat presenter/response DTO.

## What Orchestration May Coordinate

Application service boleh:
- mengatur urutan PLAN -> RECOMMENDATION -> CONFIRM;
- mengikat candidate PLAN ke confirm input yang sah;
- menggabungkan hasil artifact untuk kebutuhan consumer read.

## What Orchestration Must Not Decide

Application service tidak boleh:
- menentukan arti eligibility/publication producer;
- menentukan scoring/ranking/grouping owner watchlist;
- menggantikan DTO boundary seenaknya;
- melakukan query/persistence sebagai tempat policy tersembunyi.

