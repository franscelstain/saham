# Aturan Global Arsitektur API

Dokumen ini adalah payung prinsip dan aturan global untuk arsitektur API dan backend. Dokumen ini menjelaskan rumah utama tiap concern, aturan lintas layer, dan batas umum yang berlaku di seluruh sistem.

Dokumen ini bukan pengganti dokumen spesifik seperti `repository.md`, `kontrak-dto.md`, atau `response-dan-error.md`. Dokumen ini menetapkan kerangka umum. Aturan teknis yang lebih rinci tetap berada di dokumen yang fokus pada concern masing-masing.

## Ruang lingkup

Dokumen ini mengatur:
- tujuan arsitektur secara umum;
- rumah concern utama dalam sistem;
- prinsip lintas layer;
- larangan umum yang berlaku lintas concern;
- cara memutuskan lokasi tanggung jawab;
- hubungan antar layer utama;
- cara memperlakukan kontrak, perubahan, dan pengecualian.

Dokumen ini tidak mengatur secara rinci:
- bentuk response publik;
- detail query database;
- detail DTO per jenis;
- detail retry policy teknis;
- detail logging field;
- detail framework tertentu.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- sistem memiliki pembagian tanggung jawab yang tegas;
- perubahan teknis pada satu area tidak merusak area lain;
- controller, service, repository, dan komponen lain tidak berubah menjadi tempat semua hal;
- kontrak antar layer jelas;
- perilaku sistem dapat dijelaskan dan diuji;
- pertumbuhan kompleksitas tidak diatasi dengan shortcut yang merusak struktur.

## Prinsip utama

- Setiap concern besar harus punya rumah yang jelas.
- Concern yang berbeda tidak boleh dicampur hanya karena berada pada alur use case yang sama.
- Query, rule bisnis, request mentah, dan response publik adalah concern berbeda dan harus ditempatkan di rumah yang berbeda.
- Kontrak antar layer harus eksplisit.
- Perubahan kontrak harus diperlakukan serius.
- Pengecualian boleh ada, tetapi harus sempit, jelas, dan terdokumentasi.
- Implementasi yang “jalan” tetapi melanggar boundary tetap dianggap desain yang buruk.

## Rumah concern utama

### Transport Boundary
Rumah untuk:
- menerima input mentah dari dunia luar;
- parsing awal;
- validasi bentuk minimum;
- auth dan authz boundary;
- mapping request ke kontrak input eksplisit;
- meneruskan hasil ke response layer resmi.

Bukan rumah untuk:
- query database;
- orchestration panjang;
- rule domain utama.

### Application Service
Rumah untuk:
- orchestration use case;
- pengaturan urutan langkah aplikasi;
- koordinasi repository, domain compute, dan dependency resmi lain;
- transaction boundary tingkat aplikasi bila relevan.

Bukan rumah untuk:
- query database langsung;
- request framework mentah;
- rule domain murni yang berat;
- response publik final.

### Repository atau Persistence Layer
Rumah untuk:
- query;
- read access;
- write access;
- bulk persistence;
- pagination, chunking, streaming, cursor, batching;
- mapping hasil persistence ke bentuk yang sah bagi layer di atas.

Bukan rumah untuk:
- rule domain utama;
- response publik;
- orchestration use case;
- request framework.

### Domain Compute
Rumah untuk:
- rule bisnis murni;
- scoring;
- klasifikasi;
- keputusan domain;
- kalkulasi domain;
- invarian keputusan yang tidak bergantung pada transport atau persistence detail.

Bukan rumah untuk:
- query database;
- response mapping;
- request framework;
- logging liar;
- network call liar.

### DTO
Rumah kontrak data eksplisit antar layer.

DTO dipakai untuk:
- membawa data dengan shape yang jelas;
- menstabilkan handoff antar layer;
- membatasi payload liar.

DTO bukan rumah untuk:
- query;
- service call;
- rule bisnis;
- side effect.

### Response Layer
Rumah untuk:
- membentuk output publik;
- menjaga konsistensi sukses dan error;
- menerapkan kontrak response resmi;
- memetakan error aplikasi ke bentuk publik yang sah.

Bukan rumah untuk:
- query;
- rule bisnis baru;
- lazy loading tersembunyi;
- koreksi hasil domain.

## Aturan lintas layer

- Request framework object harus berhenti di transport boundary.
- Query resmi hanya boleh berada di persistence layer.
- Rule domain utama hanya boleh berada di domain compute atau rumah domain resmi yang setara.
- Response publik hanya boleh dibentuk di response layer atau adapter resmi yang setara.
- DTO hanya boleh membawa data dan validasi bentuk, bukan perilaku aplikasi.
- Layer penerima tidak boleh dipaksa memahami detail teknis layer asal yang bukan concern-nya.
- Handoff lintas layer harus eksplisit.
- Lazy loading yang bocor lintas layer dianggap pelanggaran boundary.
- Error tidak boleh dipetakan liar di banyak tempat tanpa kontrak yang jelas.
- Concern operasional seperti retry, rerun, duplicate handling, dan logging harus mengikuti boundary yang sudah ditetapkan, bukan menjadi alasan untuk melanggar struktur.

## Cara menentukan suatu logika harus ditempatkan di mana

Gunakan pertanyaan berikut:

### Apakah ini detail protokol atau request mentah?
Kalau ya, itu milik transport boundary.

### Apakah ini urutan langkah use case?
Kalau ya, itu milik application service.

### Apakah ini query, write, join, filter, aggregate, pagination, bulk access, atau strategi persistence?
Kalau ya, itu milik persistence layer.

### Apakah ini keputusan bisnis murni, scoring, klasifikasi, atau perhitungan domain?
Kalau ya, itu milik domain compute.

### Apakah ini sekadar bentuk data yang berpindah layer?
Kalau ya, itu milik DTO atau kontrak handoff.

### Apakah ini bentuk output publik atau mapping error ke output publik?
Kalau ya, itu milik response layer.

Bila jawaban masih bercampur, jangan paksa satu komponen memegang semuanya. Pecah concern-nya.

## Aturan kontrak

Kontrak harus dianggap serius pada:
- handoff antar layer;
- DTO;
- response publik;
- input repository;
- output repository;
- aturan idempotensi;
- identitas korelasi operasional yang penting.

Perubahan kontrak meliputi:
- tambah field;
- hapus field;
- rename field;
- ubah tipe field;
- ubah status wajib atau opsional;
- ubah makna field;
- ubah shape payload.

Perubahan seperti ini wajib ditinjau, bukan dianggap perubahan kecil.

## Aturan tentang array, object, dan model mentah

- Array bebas tidak boleh menjadi payload utama lintas layer bila kontrak resmi sudah bisa dibuat eksplisit.
- Model ORM mentah tidak boleh bocor lintas layer sebagai kontrak umum.
- Query builder tidak boleh dilempar ke service atau response layer.
- Object hidup yang bergantung pada connection, session, atau framework context tidak boleh dijadikan payload lintas layer.

## Aturan tentang exception dan error

- Error teknis, error domain, dan error input harus dibedakan.
- Domain compute tidak boleh memetakan error ke response publik final.
- Repository tidak boleh memetakan error menjadi response publik.
- Application service boleh mengklasifikasikan error aplikasi, tetapi tidak boleh membentuk response publik liar.
- Response layer atau mekanisme error mapping resmi adalah rumah kontrak error publik.

## Aturan tentang performa dan skala

- Desain yang benar pada data kecil belum tentu aman pada data besar.
- Access pattern besar harus berada di persistence layer.
- Full load, N+1, item-per-item write, dan offset tinggi yang rusak tidak boleh dianggap normal.
- Materialization atau read model boleh dipakai bila alasan dan refresh contract jelas.
- Benchmark dan profiling minimum wajib untuk alur penting yang jelas memproses data besar.

## Aturan tentang determinisme, idempotensi, dan operasi berulang

- Operasi penting harus jelas apakah idempotent atau tidak.
- Retry tanpa identitas operasi yang jelas adalah desain berbahaya.
- Rerun batch atau job harus punya kontrak yang jelas.
- Duplicate request dan duplicate event harus dipikirkan sejak desain, bukan hanya saat incident.
- Partial failure tidak boleh disamarkan sebagai sukses penuh.

## Aturan tentang logging operasional

- Logging harus membantu operasi nyata, bukan sekadar menumpuk output.
- Log penting harus terstruktur dan dapat dikorelasikan.
- Data sensitif tidak boleh bocor ke log.
- Logging harus mengikuti boundary layer.
- Log tidak boleh dipakai untuk mengganti kontrak status atau desain state yang seharusnya eksplisit.

## Pengecualian

Pengecualian hanya sah bila:
- ada alasan teknis atau bisnis yang nyata;
- ruang lingkupnya sempit;
- ada kompensasi atau batas pengaman yang jelas;
- terdokumentasi pada dokumen yang relevan;
- tidak diam-diam berubah menjadi praktik umum.

Pengecualian tidak sah bila:
- hanya karena “lebih cepat dibuat”;
- hanya karena “sementara” tanpa batas waktu dan owner;
- hanya karena implementator tidak mau mengikuti kontrak yang sudah ada;
- digunakan berulang-ulang hingga menghapus makna boundary.

## Konflik antar dokumen

- Dokumen spesifik mengalahkan dokumen umum untuk topik yang sama.
- Bila terjadi konflik, jangan pilih aturan secara oportunistik.
- Konflik dokumentasi harus diperbaiki di dokumen, bukan diselesaikan diam-diam di kode.
- README berfungsi sebagai peta, bukan pengganti aturan teknis.

## Anti-pattern global

- Controller query database langsung.
- Service memegang query dan rule domain sekaligus.
- Repository memutuskan recommendation atau eligibility.
- DTO memanggil service atau query.
- Response layer melakukan lazy loading.
- Handoff lintas layer memakai model mentah atau array liar.
- Retry dilakukan tanpa identitas operasi.
- Logging dipakai sebagai tempat dump semua hal.
- Pengecualian “sementara” dibiarkan permanen.
- Komponen dinamai generik agar bisa menampung apa saja.
