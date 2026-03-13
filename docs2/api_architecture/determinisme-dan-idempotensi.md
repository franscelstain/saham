# Determinisme dan Idempotensi

Dokumen ini mengatur prinsip, batas tanggung jawab, dan aturan implementasi untuk determinisme dan idempotensi dalam arsitektur API dan backend agar operasi tetap aman saat terjadi retry, rerun, duplikasi request, partial failure, dan eksekusi ulang proses.

## Ruang lingkup

Dokumen ini mengatur:
- apa yang dimaksud determinisme;
- apa yang dimaksud idempotensi;
- kenapa keduanya penting;
- jenis operasi yang membutuhkan determinisme atau idempotensi;
- identitas operasi dan kunci deduplikasi;
- retry policy;
- rerun policy;
- partial failure handling;
- deduplication;
- hubungan transaksi, lock, dan unique constraint dengan idempotensi;
- aturan untuk batch, scheduler, queue, event, dan proses async.

Dokumen ini tidak mengatur:
- detail query database;
- format log operasional secara rinci;
- kontrak response publik secara rinci;
- rule domain murni;
- detail framework tertentu.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- input yang sama menghasilkan hasil yang konsisten sesuai kontrak;
- operasi yang dinyatakan aman untuk diulang tidak menghasilkan efek ganda;
- retry tidak merusak state;
- rerun tidak membuat duplikasi diam-diam;
- kegagalan parsial dapat dijelaskan dan ditangani;
- batch, queue, event, dan scheduler tidak menghasilkan data liar;
- sistem dapat diuji dan diaudit dengan lebih baik.

## Definisi

### Determinisme

Determinisme adalah sifat bahwa suatu proses menghasilkan output yang konsisten untuk input dan kondisi kontrak yang sama.

Determinisme tidak berarti semua sistem harus selalu menghasilkan hasil yang sama di segala keadaan. Determinisme berarti:
- bila input, aturan, snapshot, dan kondisi kontrak yang relevan sama;
- maka hasil yang diharapkan juga sama atau konsisten dalam batas kontrak resminya.

### Idempotensi

Idempotensi adalah sifat bahwa suatu operasi yang diulang dengan identitas operasi yang sama tidak menghasilkan efek tambahan yang tidak sah.

Idempotensi tidak berarti:
- operasi diulang berkali-kali tanpa biaya;
- semua proses harus bebas efek samping;
- setiap operasi harus memberi response yang identik byte-per-byte.

Idempotensi berarti:
- operasi yang sama tidak boleh menciptakan state ganda yang tidak sah;
- pengulangan yang sah harus menghasilkan keadaan akhir yang tetap sesuai kontrak.

## Prinsip umum

- Tidak semua operasi harus idempotent, tetapi setiap operasi harus jelas apakah ia idempotent atau tidak.
- Operasi yang mungkin di-retry, di-rerun, dijalankan lewat scheduler, diproses lewat queue, atau menerima duplikasi request harus mempertimbangkan idempotensi secara serius.
- Proses yang membutuhkan auditabilitas harus menjaga determinisme hasil sejauh kontraknya memungkinkan.
- Current time, random value, dan dependency eksternal yang berubah harus diperlakukan hati-hati karena dapat merusak determinisme.
- Tidak boleh menganggap “tidak error” berarti aman dari duplikasi.
- Tidak boleh menganggap “sekali panggil pasti sekali proses” sebagai asumsi default.

## Aturan keras

- Setiap operasi penting harus jelas statusnya: idempotent, semi-idempotent dengan syarat tertentu, atau non-idempotent.
- Operasi yang dinyatakan idempotent wajib punya identitas operasi yang jelas.
- Retry untuk operasi tulis tidak boleh dilakukan sembarangan tanpa mekanisme identitas atau deduplikasi.
- Rerun batch atau job tidak boleh menghasilkan duplikasi diam-diam.
- Partial failure tidak boleh disembunyikan seolah seluruh operasi sukses penuh.
- Proses async tidak boleh bergantung pada object atau konteks yang hanya valid di proses pengirim.
- Determinisme tidak boleh dikorbankan diam-diam oleh penggunaan waktu sekarang, random, atau dependency eksternal tanpa kontrak yang jelas.
- Idempotensi tidak boleh hanya bergantung pada niat developer; harus ada mekanisme nyata yang bisa diverifikasi.

## Jenis operasi dan kebutuhan idempotensi

### Read operation

Operasi baca umumnya tidak mengubah state dan secara alami lebih dekat ke idempotent. Namun tetap harus menjaga determinisme hasil sesuai snapshot dan kontrak baca.

Perhatian:
- hasil baca dapat berubah karena data sumber berubah;
- bila kontrak mengharuskan snapshot tertentu, snapshot itu harus eksplisit.

### Create operation

Create adalah operasi yang paling rawan duplikasi.

Aturan:
- bila create dapat dipanggil ulang akibat retry atau duplikasi request, harus ada identitas operasi atau mekanisme deduplikasi yang jelas;
- create tidak boleh membuat resource ganda tanpa terdeteksi hanya karena request dikirim dua kali.

### Update operation

Update bisa idempotent atau tidak, tergantung bentuk operasinya.

Contoh yang lebih dekat ke idempotent:
- set status menjadi `active`.

Contoh yang tidak otomatis idempotent:
- tambah saldo sebesar 100;
- increment counter;
- append item ke list tanpa proteksi.

### Delete or deactivate operation

Delete atau deactivate sering diharapkan idempotent, tetapi kontraknya tetap harus jelas.

Contoh:
- menandai `is_active = false` biasanya bisa idempotent;
- menghapus resource yang sudah tidak ada bisa diperlakukan aman hanya bila kontraknya resmi.

### Batch processing

Batch sangat rawan:
- item diproses dua kali;
- rerun sebagian batch;
- batch berhenti di tengah.

Batch wajib mempertimbangkan identitas item, status proses, dan aturan rerun.

### Event handling

Event bisa terkirim lebih dari sekali. Handler tidak boleh mengasumsikan exactly-once delivery kecuali sistem benar-benar menjaminnya.

### Scheduled job

Scheduler dapat:
- menjalankan job ulang;
- overlap;
- retry setelah gagal.

Job harus punya kontrak rerun dan identitas proses yang jelas.

### Import and synchronization

Import dan sinkronisasi rawan:
- input duplikat;
- input datang terlambat;
- proses berhenti di tengah;
- data lama tertimpa tak sah.

Import dan sinkronisasi harus punya kunci identitas dan strategi merge yang jelas.

## Identitas operasi

Idempotensi membutuhkan cara mengenali bahwa dua eksekusi adalah “operasi yang sama”.

Bentuk identitas operasi bisa berupa:
- idempotency key;
- natural key;
- business key;
- composite key;
- message or event id;
- run id ditambah item identity untuk batch.

### Aturan identitas operasi

- Identitas operasi harus cukup kuat untuk membedakan operasi yang sama dan yang berbeda.
- Identitas operasi harus stabil untuk seluruh retry yang mewakili niat operasi yang sama.
- Identitas operasi tidak boleh kabur atau bergantung pada field yang berubah-ubah tanpa aturan.
- Bila operasi tidak punya identitas yang layak, jangan mengklaim operasi itu idempotent.

### Contoh

Contoh identitas yang baik:
- `payment_request_id`;
- kombinasi `account_id + external_reference`;
- `event_id`;
- `job_run_id + source_item_id`.

Contoh identitas yang lemah:
- timestamp saat ini tanpa konteks;
- random value baru tiap retry;
- sequence internal yang baru dibuat setelah operasi jalan.

## Retry policy

Retry harus dibedakan antara aman dan berbahaya.

### Retry aman bila:
- operasi read-only;
- operasi write memiliki idempotency key atau mekanisme deduplikasi kuat;
- efek samping lain sudah dikendalikan;
- kontrak state akhir jelas.

### Retry berbahaya bila:
- operasi create tanpa identitas operasi;
- operasi increment atau append tanpa proteksi;
- operasi yang memanggil dependency eksternal lalu mengubah state lokal tanpa koordinasi yang jelas;
- tidak jelas titik kegagalan terjadi sebelum atau sesudah commit.

### Aturan retry

- Retry tidak boleh otomatis dilakukan hanya karena timeout tanpa memahami apakah server sudah commit.
- Retry harus mempertimbangkan apakah request asli mungkin sudah berhasil sebagian atau penuh.
- Retry policy harus terdokumentasi pada operasi penting.
- Retry count, backoff, dan kondisi penghentian harus jelas pada proses otomatis.

## Rerun policy

Rerun adalah menjalankan ulang proses yang sama di waktu berbeda, baik penuh maupun parsial.

### Aturan rerun

- Rerun harus punya definisi apakah ia:
  - memproses semua dari nol;
  - melanjutkan dari checkpoint;
  - hanya memproses item yang gagal;
  - melakukan rekonsiliasi terhadap hasil sebelumnya.
- Rerun tidak boleh diam-diam menghasilkan insert tambahan yang tidak sah.
- Rerun harus bisa dijelaskan terhadap output yang sudah telanjur ada.
- Rerun harus mempertimbangkan versioning rule atau snapshot bila hasil bergantung pada data referensi yang berubah.

### Rerun dari awal

Boleh dilakukan bila:
- operasi dirancang untuk overwrite secara aman;
- atau ada mekanisme clear-and-rebuild yang resmi dan aman;
- atau hasil lama memang boleh digantikan penuh.

### Resume dari checkpoint

Lebih tepat bila:
- batch besar;
- sebagian item sudah sukses;
- pengulangan total terlalu mahal atau terlalu berisiko.

## Partial failure handling

Partial failure terjadi saat sebagian operasi berhasil, sebagian gagal, atau titik kegagalan tidak jelas.

### Aturan

- Partial failure harus terlihat, tidak boleh disamarkan sebagai sukses total.
- Sistem harus bisa menjelaskan item mana yang sukses, mana yang gagal, dan mana yang statusnya tidak pasti bila relevan.
- Strategi penanganan harus jelas:
  - rollback penuh;
  - commit parsial lalu tandai item gagal;
  - kompensasi;
  - resume;
  - rekonsiliasi.

### Titik kegagalan yang harus dipahami

- gagal sebelum commit;
- gagal sesudah commit lokal tetapi sebelum ack ke caller;
- gagal saat memanggil dependency eksternal;
- gagal setelah sebagian item batch berhasil.

### Larangan

- Menganggap timeout selalu berarti gagal total.
- Menganggap tidak ada exception berarti semua efek samping pasti sudah konsisten.
- Menjalankan ulang seluruh proses tanpa tahu item mana yang sudah sukses.

## Deduplication

Deduplication adalah mekanisme mencegah atau mendeteksi proses ganda untuk operasi yang semestinya satu.

### Bentuk deduplication

- unique constraint;
- idempotency key store;
- processed-event registry;
- natural key matching;
- status marker per item;
- merge strategy yang eksplisit.

### Aturan deduplication

- Deduplication harus sesuai dengan identitas operasi.
- Deduplication harus dilakukan di titik yang cukup dekat dengan perubahan state penting.
- Deduplication di layer aplikasi saja bisa tidak cukup bila persistence layer tidak memberi jaminan apa pun.
- Unique constraint membantu, tetapi tidak selalu cukup untuk seluruh efek samping sistem.

## Deterministic processing

Proses dianggap lebih deterministik bila:
- input yang dipakai jelas;
- aturan yang dipakai jelas;
- sumber waktu dikendalikan;
- random source dikendalikan atau dicatat;
- urutan pemrosesan yang relevan ditetapkan;
- dependency eksternal yang tidak stabil ditangani dengan kontrak yang jelas.

### Sumber ketidakdeterministikan yang umum

- `now()` atau current timestamp yang dipakai bebas;
- random generator tanpa seed atau tanpa pencatatan;
- iterasi tanpa urutan resmi padahal urutan memengaruhi hasil;
- dependency eksternal yang memberi data live berubah-ubah;
- query tanpa order yang sah saat urutan hasil dipakai dalam logika.

### Aturan determinisme

- Bila urutan memengaruhi hasil, urutan harus resmi.
- Bila waktu memengaruhi hasil, sumber waktu harus jelas.
- Bila snapshot data penting, snapshot itu harus eksplisit.
- Bila rule bergantung pada konfigurasi, versi konfigurasi harus bisa ditelusuri.
- Bila dependency eksternal membuat hasil berubah, kontrak itu harus diakui, bukan disembunyikan.

## Hubungan transaksi, lock, dan unique constraint

Transaksi, lock, dan unique constraint dapat membantu idempotensi, tetapi bukan pengganti seluruh desain idempotensi.

### Transaksi membantu untuk:
- menjaga atomisitas perubahan state lokal;
- mencegah sebagian write lokal tercommit tanpa kontrol;
- mengurangi race tertentu.

### Unique constraint membantu untuk:
- mencegah create ganda pada identitas tertentu;
- memperkuat deduplication di persistence layer.

### Lock membantu untuk:
- mencegah overlap operasi yang tidak boleh bersamaan;
- mengontrol konkurensi pada resource tertentu.

### Batasnya

- Transaksi tidak otomatis menyelesaikan duplikasi dari retry yang datang di waktu berbeda.
- Unique constraint tidak otomatis mengurus efek samping eksternal seperti publish event atau panggilan ke provider.
- Lock yang terlalu lebar dapat merusak performa dan belum tentu menyelesaikan masalah deduplication historis.

## Operasi batch dan async

Batch, queue, event, dan proses async wajib dipikirkan per item maupun per run.

### Aturan

- Tentukan apakah idempotensi berlaku per batch, per item, atau keduanya.
- Payload async harus menyertakan identitas yang cukup.
- Status proses harus dapat ditelusuri per run dan, bila perlu, per item.
- Resume marker atau checkpoint harus jelas untuk batch besar.
- Sistem harus jelas apakah duplicate item akan:
  - di-skip;
  - di-merge;
  - memicu error;
  - di-overwrite.

### Larangan

- Mengasumsikan queue atau event broker menjamin hanya sekali tanpa verifikasi.
- Mengirim payload async tanpa identitas operasi.
- Menjalankan rerun batch tanpa tahu bagaimana item yang sudah sukses diperlakukan.

## Hubungan dengan response dan observabilitas

Idempotensi dan determinisme perlu bisa diamati.

Aturan:
- request id, run id, event id, atau idempotency key harus dapat ditelusuri di log dan observability system bila kontraknya relevan;
- hasil retry dan rerun harus dapat dibedakan dari eksekusi awal;
- status partial success atau duplicate detection harus dapat dilihat secara internal;
- response publik boleh memberi sinyal yang relevan sesuai kontrak, tetapi detail internal tetap harus dijaga.

## Contoh kasus

### Duplicate create request

Kasus:
- client timeout lalu mengirim ulang request create.

Aturan:
- bila niat operasinya sama, request harus memakai identitas operasi yang sama;
- server harus bisa mendeteksi bahwa operasi itu sudah pernah diproses atau sedang diproses;
- create kedua tidak boleh membuat resource ganda.

### Event diproses dua kali

Kasus:
- broker mengirim event yang sama dua kali.

Aturan:
- handler harus memeriksa identitas event;
- efek state tidak boleh terduplikasi;
- jika event sudah pernah diproses, hasilnya harus aman sesuai kontrak.

### Job harian dijalankan ulang

Kasus:
- scheduler memicu job lagi setelah kegagalan.

Aturan:
- job harus tahu apakah akan rebuild, resume, atau skip item yang sudah selesai;
- hasil run pertama dan run kedua harus dapat dijelaskan;
- tidak boleh ada duplikasi diam-diam.

### Timeout di client tetapi server sudah commit

Kasus:
- client tidak mendapat response, lalu retry.

Aturan:
- retry harus memakai identitas operasi yang sama;
- server harus bisa mengembalikan hasil yang konsisten atau status yang aman;
- sistem tidak boleh membuat efek ganda hanya karena ack ke client gagal.

## Anti-pattern

- Mengandalkan retry tanpa identitas operasi.
- Menganggap create request aman diulang tanpa deduplication.
- Menggunakan current time bebas sebagai satu-satunya pembeda operasi.
- Menggunakan random value baru setiap retry untuk operasi yang seharusnya sama.
- Menganggap timeout pasti berarti operasi belum jalan.
- Menganggap exactly-once delivery padahal sistem tidak menjaminnya.
- Menjalankan rerun batch yang selalu insert baru.
- Menyembunyikan partial failure di balik status sukses umum.
- Tidak mencatat identitas run, event, atau request untuk operasi penting.
- Mengklaim operasi idempotent padahal hanya “jarang gagal”.
