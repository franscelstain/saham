# Logging Operasional

Dokumen ini mengatur prinsip, batas tanggung jawab, dan aturan implementasi untuk logging operasional dalam arsitektur API dan backend agar log berguna untuk observabilitas, audit, troubleshooting, dan pengendalian proses tanpa berubah menjadi sumber kebocoran data, noise, atau coupling teknis yang liar.

## Ruang lingkup

Dokumen ini mengatur:
- tujuan logging operasional;
- perbedaan logging operasional dan logging debugging;
- field minimum yang harus ada pada log penting;
- identitas korelasi seperti request id, run id, event id, atau trace id;
- kapan log wajib dibuat, kapan opsional, dan kapan harus dihindari;
- larangan mencatat data sensitif;
- aturan level log;
- aturan untuk log proses sinkron, async, batch, queue, scheduler, dan integrasi;
- hubungan logging dengan error handling, idempotensi, dan observabilitas.

Dokumen ini tidak mengatur:
- format dashboard monitoring;
- detail implementasi vendor log tertentu;
- detail query database;
- rule domain murni;
- kontrak response publik secara rinci.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- kejadian penting dalam sistem dapat ditelusuri;
- kegagalan dapat dianalisis tanpa menebak-nebak;
- alur request, job, batch, dan event dapat dikorelasikan;
- duplicate processing, retry, partial failure, dan rerun dapat dilihat dengan jelas;
- data sensitif tidak bocor ke log;
- log tetap bernilai operasional dan tidak dipenuhi noise;
- logging tidak menjadi side effect liar yang merusak boundary arsitektur.

## Definisi

### Logging operasional

Logging operasional adalah pencatatan kejadian sistem yang relevan untuk menjalankan, memantau, mengaudit, dan men-debug perilaku sistem pada level operasi nyata.

### Logging debugging

Logging debugging adalah pencatatan sementara atau detail teknis yang berguna saat investigasi lokal atau fase pengembangan, tetapi belum tentu layak dipertahankan permanen di production.

### Structured logging

Structured logging adalah log yang ditulis dalam bentuk field yang eksplisit dan konsisten sehingga dapat dicari, difilter, diagregasi, dan dikorelasikan secara andal.

## Prinsip umum

- Log harus punya tujuan operasional yang jelas.
- Log harus membantu menjawab pertanyaan nyata saat terjadi masalah.
- Log penting harus terstruktur.
- Log tidak boleh menjadi dump sembarangan dari object, request, atau exception mentah.
- Log tidak boleh membocorkan data sensitif.
- Log tidak boleh dipakai sebagai pengganti kontrak error, kontrak response, atau kontrak data.
- Log harus cukup untuk penelusuran, tetapi tidak boleh berlebihan hingga mengubur sinyal penting.
- Logging harus mengikuti boundary arsitektur. Suatu layer hanya boleh melog hal yang memang menjadi concern-nya.

## Aturan keras

- Setiap log penting wajib memiliki konteks identitas korelasi yang cukup.
- Logging operasional penting wajib memakai field yang eksplisit dan konsisten.
- Data sensitif dilarang dicatat ke log kecuali ada alasan yang sangat kuat, legal, aman, dan terdokumentasi. Secara default, anggap data sensitif tidak boleh masuk log.
- Raw SQL, credential, token, secret, password, session secret, private key, payload pribadi, dan stack trace mentah tidak boleh dicatat ke log operasional publik tanpa sanitasi.
- Log tidak boleh menjadi tempat side effect tambahan yang tidak perlu.
- Log level harus dipakai secara konsisten.
- Log tidak boleh dibuat berulang-ulang untuk kejadian yang sama tanpa nilai tambah.
- Log tidak boleh dipakai untuk menyembunyikan desain yang buruk, seperti mengandalkan log karena kontrak state internal tidak jelas.

## Tujuan log yang sah

Log dianggap sah bila membantu salah satu atau beberapa tujuan berikut:
- menelusuri request atau proses;
- mengaudit perubahan state penting;
- memahami penyebab kegagalan;
- membedakan sukses, gagal, retry, duplicate, dan partial failure;
- mengukur langkah penting pada proses batch atau integrasi;
- mendukung incident response;
- mendukung observability dan troubleshooting.

## Tujuan log yang tidak sah

Log tidak layak dibuat bila hanya:
- menyalin seluruh payload tanpa seleksi;
- menuliskan semua hal “supaya aman” tanpa tujuan;
- mencatat setiap baris loop tanpa manfaat operasional;
- menggantikan kontrak status internal yang seharusnya eksplisit di sistem;
- membocorkan detail internal yang tidak perlu;
- menjadi pengganti test.

## Structured logging

Log operasional penting sebaiknya ditulis dalam bentuk terstruktur.

### Field minimum yang direkomendasikan

Untuk log penting, field minimum yang direkomendasikan:
- `timestamp`
- `level`
- `message`
- `event_name` atau `action`
- `request_id`, `trace_id`, `run_id`, `event_id`, atau identitas korelasi lain yang relevan
- `component` atau `layer`
- `status`
- `entity_type` dan `entity_id` bila relevan
- `error_code` bila relevan
- `attempt` atau `retry_count` bila relevan

### Aturan structured logging

- Nama field harus konsisten.
- Satu konsep tidak boleh punya banyak nama field berbeda tanpa alasan resmi.
- Nilai field harus ringkas dan bermakna.
- Field besar atau sangat detail harus dipilih dengan hati-hati.
- Message bebas boleh ada, tetapi field struktural tetap harus menjadi sumber utama penelusuran.

## Identitas korelasi

Log operasional harus mendukung korelasi antar kejadian.

Identitas korelasi yang umum:
- `request_id`
- `trace_id`
- `correlation_id`
- `run_id`
- `job_id`
- `event_id`
- `idempotency_key`

### Aturan identitas korelasi

- Pilih identitas yang relevan dengan jenis proses.
- Identitas korelasi harus konsisten dari awal sampai akhir alur yang sama sejauh memungkinkan.
- Retry atau rerun harus tetap dapat dikaitkan ke operasi induknya.
- Batch besar boleh punya identitas run dan identitas item secara terpisah.
- Log tanpa identitas korelasi untuk operasi penting dianggap lemah secara operasional.

## Kapan log wajib dibuat

Log wajib dibuat untuk kejadian seperti:
- request masuk dan selesai, bila sistem memang memerlukan access or request log resmi;
- perubahan state penting;
- start dan end job, batch, scheduler, import, sinkronisasi, atau proses async;
- duplicate detection;
- retry;
- rerun;
- partial failure;
- dependency failure;
- keputusan skip, merge, overwrite, rollback, atau kompensasi yang penting;
- error yang mempengaruhi operasi nyata;
- operasi yang berdampak pada audit trail bisnis atau teknis.

## Kapan log opsional

Log opsional dapat dibuat untuk:
- milestone internal yang berguna saat troubleshooting;
- metrik langkah besar;
- warning dini atas kondisi yang belum gagal tetapi berisiko;
- peristiwa domain tertentu yang memang perlu diamati, bila tidak lebih cocok sebagai event atau metric.

## Kapan log harus dihindari

Log harus dihindari untuk:
- setiap iterasi item yang sangat besar tanpa nilai operasional;
- setiap field payload mentah;
- setiap langkah kecil yang tidak membantu analisis;
- nilai yang sensitif;
- dump object besar;
- stack trace mentah di log yang tidak aman;
- event berulang yang sama tanpa perbedaan konteks.

## Level log

Gunakan level log secara konsisten.

### DEBUG
Dipakai untuk informasi detail yang berguna saat investigasi teknis, biasanya lebih cocok dibatasi atau tidak selalu aktif di production.

### INFO
Dipakai untuk kejadian normal yang penting secara operasional, seperti start, finish, status penting, dan hasil langkah besar.

### WARNING
Dipakai saat terjadi kondisi tak ideal, tetapi proses masih dapat berjalan atau diselamatkan.

### ERROR
Dipakai saat terjadi kegagalan yang mempengaruhi operasi saat ini, tetapi sistem atau proses yang lebih besar mungkin masih berjalan.

### CRITICAL atau FATAL
Dipakai saat terjadi kegagalan berat yang membuat komponen atau proses besar tidak dapat melanjutkan kerja secara layak.

### Aturan level

- Jangan memakai ERROR untuk semua hal.
- Jangan memakai INFO untuk noise yang sangat besar.
- Jangan menyembunyikan kegagalan nyata di level DEBUG.
- Warning harus dipakai untuk kondisi yang memang perlu perhatian, bukan sekadar variasi normal.

## Data sensitif dan sanitasi

Data sensitif tidak boleh dicatat ke log secara sembrono.

Contoh data yang harus dianggap sensitif:
- password;
- token;
- secret key;
- private key;
- session secret;
- access credential;
- nomor identitas pribadi;
- data finansial pribadi;
- data kesehatan;
- payload pribadi yang tidak perlu untuk operasi;
- raw request body yang memuat data rahasia.

### Aturan sanitasi

- Redact atau mask field sensitif.
- Jangan log seluruh payload bila hanya sebagian kecil yang dibutuhkan.
- Exception internal harus disanitasi sebelum dicatat bila berpotensi membawa informasi sensitif.
- Stack trace boleh dicatat hanya di tempat yang aman dan sesuai kebijakan, bukan secara liar di semua sink log.
- Bila ragu apakah suatu field sensitif, perlakukan sebagai sensitif.

## Logging error

Log error harus membantu investigasi, bukan sekadar mencetak exception.

Log error yang sehat sebaiknya mencakup:
- jenis kegagalan;
- error code internal atau publik yang relevan;
- komponen yang gagal;
- identitas korelasi;
- entity atau operasi yang terdampak;
- attempt atau retry count bila relevan;
- status proses saat gagal.

### Aturan logging error

- Jangan log exception mentah berulang di banyak layer tanpa nilai tambah.
- Satu error tidak boleh menghasilkan ledakan log duplikat lintas layer tanpa kontrol.
- Layer yang paling relevan harus menambahkan konteks yang benar, bukan semua layer menulis ulang hal yang sama.
- Error publik dan log internal boleh berbeda detailnya; log internal boleh lebih kaya, tetapi tetap harus aman.

## Logging request dan response

Bila sistem membutuhkan request log resmi, kontraknya harus jelas.

### Aturan

- Jangan log seluruh request dan response body secara default.
- Log hanya field yang relevan untuk operasi dan aman disimpan.
- Request id atau trace id harus selalu ada.
- Status akhir, durasi, dan route atau action sebaiknya dicatat.
- Response body sensitif tidak boleh dicatat mentah.

## Logging batch, scheduler, queue, dan async process

Boundary async butuh logging yang lebih disiplin.

### Field yang direkomendasikan

- `run_id`
- `job_name`
- `job_id`
- `event_id`
- `attempt`
- `item_count`
- `success_count`
- `failed_count`
- `duplicate_count`
- `skipped_count`
- `duration_ms`

### Aturan

- Log start dan end proses besar.
- Log perubahan status besar, bukan setiap detail kecil tanpa nilai.
- Bila proses berjalan per item dalam jumlah besar, gunakan ringkasan periodik atau agregat bila itu lebih bernilai daripada log per item.
- Untuk item kritikal yang gagal, log item identity yang relevan tanpa membocorkan payload sensitif.
- Retry dan rerun harus terlihat jelas di log.

## Logging perubahan state

Perubahan state penting layak dilog bila:
- ada dampak operasional;
- dibutuhkan untuk audit teknis;
- membantu membedakan status proses;
- berhubungan dengan operasi penting seperti activate, deactivate, cancel, approve, settle, import, reconcile, atau sinkronisasi.

### Aturan

- Log harus menyebut entity yang relevan.
- Log harus menyebut perubahan status yang penting.
- Jangan log seluruh before and after snapshot bila terlalu besar atau sensitif, kecuali memang diperlukan dan aman.
- Bila perbedaan field perlu dicatat, pilih field penting saja.

## Logging retry, duplicate, dan idempotensi

Sistem yang punya retry atau idempotensi perlu log yang bisa menjelaskan perilaku tersebut.

### Aturan

- Log saat retry terjadi.
- Log identitas operasi yang di-retry.
- Log alasan retry bila diketahui.
- Log duplicate detection.
- Log keputusan sistem saat mendeteksi duplicate: skip, merge, return existing, fail, atau overwrite.
- Log rerun batch dengan run id yang jelas.
- Log partial success dan partial failure secara eksplisit.

## Logging integrasi eksternal

Integrasi dengan provider, service pihak ketiga, atau boundary eksternal perlu logging yang cukup untuk investigasi tanpa membocorkan data.

### Aturan

- Log nama provider atau dependency yang relevan.
- Log operation name atau endpoint logical name, bukan harus selalu full raw URL.
- Log status hasil integrasi.
- Log latency atau durasi bila relevan.
- Jangan log credential.
- Jangan log payload mentah bila sensitif.
- Log correlation id lintas sistem bila ada.

## Logging dan boundary arsitektur

Logging harus mengikuti boundary.

### Transport boundary
Boleh log:
- route atau action;
- request id;
- status hasil;
- durasi;
- validation failure summary yang aman.

Tidak boleh:
- memutuskan logika audit bisnis yang seharusnya dilakukan di layer lain;
- menyalin seluruh payload sensitif mentah.

### Application service
Boleh log:
- start atau finish use case penting;
- keputusan orchestration penting;
- retry, rerun, duplicate detection, partial failure.

Tidak boleh:
- log detail query SQL;
- dump object besar tanpa tujuan.

### Repository
Boleh log:
- kejadian persistence penting;
- kegagalan query atau write yang relevan secara operasional;
- jumlah row yang diproses bila relevan.

Tidak boleh:
- log SQL mentah atau parameter sensitif secara sembarangan;
- log berulang untuk setiap operasi kecil tanpa nilai.

### Domain compute
Domain compute sebaiknya sangat hemat log. Bila perlu log, hanya untuk kejadian yang memang bernilai operasional dan tidak merusak purity desain lebih dari yang dibutuhkan oleh sistem.

### Response layer
Boleh log:
- hasil mapping error besar atau kontrak output penting bila relevan secara operasional.

Tidak boleh:
- log body response sensitif mentah;
- mengulang log error yang sama tanpa tambahan konteks.

## Retensi, volume, dan noise

Logging yang sehat memperhatikan biaya dan noise.

Aturan:
- volume log harus proporsional dengan nilai operasional;
- noisy log harus dipangkas;
- log dengan nilai jangka panjang harus mudah dicari;
- retensi dan sink log harus mengikuti kebutuhan audit, keamanan, dan biaya;
- log besar yang tidak pernah dipakai harus ditinjau dan dikurangi.

## Anti-pattern

- Log semua payload mentah “untuk jaga-jaga”.
- Log password, token, secret, atau data pribadi.
- Mencetak stack trace mentah ke semua sink log.
- Setiap layer menulis error yang sama tanpa kontrol.
- Menggunakan log untuk mengganti status proses yang seharusnya eksplisit di database atau state machine.
- Log per item untuk jutaan item tanpa strategi agregasi.
- Tidak ada request id, run id, atau correlation id untuk operasi penting.
- Semua hal ditulis pada level ERROR.
- Message log tidak konsisten dan tidak bisa dicari.
- Field structured logging berubah-ubah antar komponen.
