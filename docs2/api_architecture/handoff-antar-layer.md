# Handoff Antar Layer

Dokumen ini mengatur kontrak perpindahan data antar layer dalam arsitektur API dan backend agar bentuk data tetap eksplisit, batas concern tetap terjaga, dan detail internal dari suatu layer tidak bocor ke layer lain tanpa alasan resmi.

## Ruang lingkup

Dokumen ini mengatur:
- apa yang dimaksud handoff antar layer;
- kenapa handoff harus dikunci;
- bentuk data yang diperbolehkan dan dilarang saat berpindah layer;
- peta handoff resmi antar layer utama;
- tanggung jawab tiap layer saat menerima dan mengirim data;
- aturan agar detail transport, persistence, dan framework tidak bocor lintas layer;
- anti-pattern umum dalam handoff data.

Dokumen ini tidak mengatur:
- detail rule domain;
- detail query database;
- bentuk response publik final secara rinci;
- detail implementasi framework tertentu;
- format log operasional.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- perpindahan data antar layer punya bentuk yang jelas;
- layer tidak saling menyusupkan concern yang bukan miliknya;
- request framework, model persistence, dan object teknis tidak bocor sembarangan;
- lazy loading, side effect tersembunyi, dan kontrak implisit bisa dicegah;
- perubahan alur lebih aman dan lebih mudah ditinjau;
- boundary yang sudah didefinisikan oleh dokumen lain dapat dipertahankan secara nyata.

## Definisi

Handoff antar layer adalah perpindahan payload utama dari satu layer ke layer lain sebagai bagian dari alur aplikasi.

Yang dimaksud payload utama adalah object, DTO, result object, criteria object, atau bentuk data resmi lain yang menjadi kontrak komunikasi antar layer.

Handoff bukan:
- variabel lokal yang hidup sangat singkat di dalam satu fungsi;
- hasil parsing sementara yang belum melintasi boundary;
- state internal helper yang tidak menjadi kontrak lintas layer.

## Prinsip umum

- Setiap handoff lintas layer harus memiliki bentuk yang eksplisit.
- Setiap handoff harus menjaga agar concern layer asal tidak bocor ke layer tujuan.
- Layer penerima tidak boleh dipaksa memahami detail teknis yang bukan urusannya.
- Handoff tidak boleh menjadi saluran diam-diam untuk lazy loading, query tersembunyi, atau side effect tersembunyi.
- Bila sebuah handoff resmi sudah ditetapkan, bentuk payload utamanya harus konsisten.
- Perubahan bentuk handoff adalah perubahan kontrak dan harus diperlakukan serius.

## Aturan keras

- Request framework object tidak boleh melewati transport boundary sebagai payload utama.
- Model ORM atau persistence model mentah tidak boleh dipakai sebagai payload utama lintas layer.
- Query builder, cursor, connection, transaction handle, atau object teknis persistence lain tidak boleh dibawa lintas layer sebagai payload bisnis.
- Payload handoff tidak boleh memicu akses database tersembunyi saat dibaca.
- Payload handoff tidak boleh membawa dependency teknis seperti logger, repository, HTTP client, cache client, atau container.
- Handoff tidak boleh memakai array bebas bila kontrak resmi untuk alur tersebut sudah ditetapkan.
- Setiap handoff harus dapat dijelaskan bentuk input dan outputnya secara eksplisit.
- Layer penerima berhak mengasumsikan bahwa payload yang diterimanya memang sesuai kontrak resmi, bukan campuran concern tersembunyi.

## Peta handoff resmi

Peta handoff resmi minimum dalam arsitektur ini adalah:
- Transport Boundary -> Application Service
- Application Service -> Repository
- Repository -> Application Service
- Application Service -> Domain Compute
- Domain Compute -> Application Service
- Application Service -> Response Layer

Sistem boleh memiliki handoff lain, seperti:
- Application Service -> Queue or Job Boundary
- Job Boundary -> Application Service
- Application Service -> External Provider Boundary
- External Provider Boundary -> Application Service

Handoff tambahan harus tetap mengikuti prinsip pada dokumen ini.

## Bentuk payload yang diperbolehkan

Bentuk payload yang diperbolehkan dalam handoff resmi meliputi:
- request DTO;
- command or input DTO;
- query or filter DTO;
- result or read DTO;
- response DTO;
- criteria object;
- primitive yang sangat terbatas dan eksplisit, bila kompleksitasnya memang kecil dan kontrak tetap jelas;
- entity atau value object internal hanya bila memang secara resmi diizinkan oleh arsitektur dan tidak membawa concern layer lain.

### Aturan bentuk yang diperbolehkan

- Payload harus eksplisit.
- Payload harus punya shape yang stabil.
- Payload harus bebas side effect tersembunyi.
- Payload harus tidak memaksa layer penerima memahami transport framework atau persistence framework.
- Payload harus hanya membawa data atau perilaku yang memang sah untuk layer tujuan.

## Bentuk payload yang dilarang

Yang dilarang dijadikan payload utama handoff:
- request object framework;
- ORM model mentah;
- model dengan lazy loading aktif;
- query builder;
- raw DB cursor;
- object yang memegang koneksi database;
- response HTTP final;
- service locator payload;
- helper object yang menyembunyikan dependency teknis;
- exception internal yang dipakai sebagai payload normal;
- array bebas yang berubah-ubah tanpa kontrak.

## Handoff dari transport boundary ke application service

Transport boundary bertugas menerima request mentah dan mengubahnya menjadi input eksplisit untuk application service.

### Bentuk yang direkomendasikan
- request DTO;
- command/input DTO;
- filter DTO untuk endpoint query;
- primitive eksplisit yang sangat terbatas bila memang masih masuk akal.

### Aturan
- Request framework harus berhenti di transport boundary.
- Data yang dikirim ke application service harus sudah lolos validasi bentuk minimum sesuai kontrak transport.
- Nama field yang dikirim harus konsisten dengan kontrak input resmi.
- Application service tidak boleh dipaksa menafsir ulang payload yang kabur.
- Transport boundary tidak boleh menyelundupkan state teknis framework ke payload utama.

### Larangan
- Mengirim request object framework mentah ke service.
- Mengirim object uploaded file wrapper mentah sebagai kontrak lintas layer tanpa boundary resmi.
- Mengirim array campur-aduk yang membuat service menebak field yang tersedia.
- Mengirim model persistence yang diambil langsung dari route binding atau mekanisme framework lain.

## Handoff dari application service ke repository

Application service berkomunikasi dengan repository untuk kebutuhan baca dan tulis data.

### Bentuk yang direkomendasikan
- query or filter DTO;
- criteria object;
- command/input DTO yang memang relevan untuk persistence;
- primitive eksplisit yang sedikit dan jelas.

### Aturan
- Service harus menyatakan kebutuhan data secara eksplisit.
- Repository tidak boleh dipaksa menebak niat service dari payload yang kabur.
- Payload ke repository tidak boleh membawa concern response publik.
- Payload ke repository tidak boleh membawa object transport.
- Bila repository memerlukan parameter query, parameter itu harus terstruktur dan stabil.

### Larangan
- Service melempar request framework object ke repository.
- Service melempar model ORM dari layer lain ke repository sebagai kontrak utama.
- Service mengirim array bebas besar yang bentuknya tidak jelas.
- Service mengirim object yang berisi dependency teknis tersembunyi.

## Handoff dari repository ke application service

Repository mengembalikan hasil persistence ke application service.

### Bentuk yang direkomendasikan
- result or read DTO;
- row/result object yang eksplisit dan inert;
- entity atau value object bila arsitektur resmi memang mengizinkannya dan bentuknya tidak bocor detail persistence.

### Aturan
- Hasil repository harus siap dipakai service tanpa memicu query tersembunyi.
- Hasil repository harus tidak bergantung pada koneksi persistence yang masih hidup.
- Hasil repository harus dipersempit sesuai kebutuhan use case, bukan melempar semua hal “sekalian”.
- Service tidak boleh dipaksa memahami detail tabel hanya untuk membaca hasil repository.

### Larangan
- Repository mengembalikan ORM model mentah dengan lazy loading aktif.
- Repository mengembalikan query builder untuk dilanjutkan di service.
- Repository mengembalikan object yang baru valid bila koneksi tertentu masih aktif.
- Repository mengembalikan hasil yang bentuknya berubah-ubah tanpa kontrak.

## Handoff dari application service ke domain compute

Application service menyiapkan input untuk domain compute agar rule bisnis dapat dieksekusi secara fokus.

### Bentuk yang direkomendasikan
- domain input DTO;
- value object;
- result object yang sudah dipersempit;
- primitive eksplisit yang kecil dan jelas.

### Aturan
- Domain compute harus menerima input yang fokus pada keputusan bisnis, bukan payload teknis yang penuh noise.
- Domain compute tidak perlu tahu apakah data berasal dari DB, HTTP, cache, queue, atau sumber lain.
- Service harus memisahkan data yang relevan untuk domain dari data yang hanya relevan untuk transport atau persistence.
- Shape input domain harus stabil dan dapat diuji.

### Larangan
- Service mengirim request framework object ke domain compute.
- Service mengirim ORM model mentah ke domain compute.
- Service mengirim payload besar campur-aduk yang memaksa domain memilah sendiri concern teknis.
- Service menyerahkan object yang dapat memicu IO tersembunyi saat domain membaca field-nya.

## Handoff dari domain compute ke application service

Domain compute mengembalikan hasil keputusan bisnis ke service.

### Bentuk yang direkomendasikan
- domain result DTO;
- value object;
- result object yang eksplisit;
- primitive yang sangat terbatas bila hasilnya memang kecil dan jelas.

### Aturan
- Hasil domain harus fokus pada keputusan atau perhitungan domain.
- Hasil domain tidak boleh memuat concern transport publik.
- Hasil domain tidak boleh memuat dependency teknis.
- Hasil domain harus deterministik terhadap input yang diberikan sesuai kontrak domain.

### Larangan
- Domain mengembalikan response HTTP final.
- Domain mengembalikan object persistence mentah.
- Domain mengembalikan object yang memerlukan query tambahan untuk dipahami.
- Domain menyisipkan detail presentasi publik ke payload hasilnya tanpa alasan resmi.

## Handoff dari application service ke response layer

Application service menyerahkan hasil aplikasi ke response layer untuk dipetakan menjadi output publik.

### Bentuk yang direkomendasikan
- response DTO;
- result object yang siap dipresentasikan;
- error aplikasi terklasifikasi yang resmi.

### Aturan
- Response layer menerima hasil yang sudah final pada level aplikasi.
- Response layer tidak boleh dipaksa menghitung ulang rule bisnis yang seharusnya sudah selesai di bawahnya.
- Payload ke response layer harus tidak membawa lazy loading atau query tersembunyi.
- Hasil yang dipresentasikan harus sesuai dengan kontrak response resmi.

### Larangan
- Service melempar model ORM mentah ke response layer.
- Service melempar query builder ke response layer.
- Service melempar object teknis persistence dan berharap response layer bisa “menyesuaikan”.
- Service melempar payload yang shape-nya tidak stabil.

## Handoff ke queue, job, event, atau proses async

Bila sistem memakai boundary async, payload yang dikirim ke sana juga harus mengikuti prinsip handoff resmi.

### Aturan
- Payload async harus eksplisit dan stabil.
- Payload async tidak boleh bergantung pada object yang hanya valid di proses pengirim.
- Payload async harus aman diserialisasi dan dibaca ulang.
- Payload async harus mempertimbangkan versioning bila umur antrean atau event dapat panjang.
- Id operasi atau identitas deduplication harus disertakan bila kontrak proses membutuhkannya.

### Larangan
- Mengirim model ORM hidup ke queue.
- Mengirim request framework object ke job.
- Mengirim connection handle, cursor, atau object yang tidak serializable.
- Mengirim payload yang hanya bisa dipahami bila konteks memori proses asal masih ada.

## Stabilitas nama field dan shape

Handoff yang sehat harus menjaga stabilitas nama field dan shape.

Aturan:
- field name harus konsisten antar handoff yang memang berbicara tentang konsep yang sama, kecuali ada alasan resmi untuk mapping eksplisit;
- perubahan nama field tidak boleh dilakukan diam-diam;
- nested shape harus dapat dijelaskan;
- field opsional dan wajib harus jelas;
- mapper antar payload harus eksplisit bila transformasinya tidak satu banding satu.

## Mapper antar layer

Mapper boleh dipakai untuk mengubah payload dari satu kontrak ke kontrak lain.

Aturan:
- mapper harus eksplisit;
- mapper tidak boleh menjadi tempat query baru;
- mapper tidak boleh menyelundupkan rule bisnis berat;
- mapper tidak boleh menjadi tempat side effect;
- bila mapper mengubah nama atau bentuk field, alasan perubahan harus jelas.

Mapper berguna bila:
- kontrak layer asal dan tujuan memang berbeda;
- payload perlu dipersempit;
- naming antar layer harus dibedakan secara sengaja.

## Perubahan kontrak handoff

Perubahan bentuk payload handoff adalah perubahan kontrak.

Perubahan yang harus ditinjau serius:
- tambah field;
- hapus field;
- rename field;
- ubah tipe field;
- ubah nested structure;
- ubah status wajib atau opsional;
- ubah object menjadi array atau sebaliknya tanpa alasan resmi.

Aturan:
- perubahan harus ditinjau terhadap layer pengirim dan penerima;
- dokumentasi terkait harus diperbarui;
- test integrasi atau contract test yang relevan harus diperbarui;
- perubahan tidak boleh dilakukan diam-diam hanya karena implementasi saat ini “masih jalan”.

## Anti-pattern

- Controller atau handler mengirim request framework object ke service.
- Service mengirim ORM model mentah ke response layer.
- Repository mengembalikan object yang memicu lazy loading saat disentuh.
- Service mengirim payload campur-aduk ke domain compute.
- Domain mengembalikan object persistence mentah.
- Queue menerima model atau connection handle hidup.
- Handoff berubah-ubah bentuk antar endpoint yang seharusnya setara.
- Array bebas dipakai untuk menghindari DTO resmi.
- Query builder dilempar lintas layer agar “fleksibel”, lalu semua boundary jadi kabur.
- Mapper dipakai sebagai tempat logika bisnis dan query tambahan.
