# Performa Data Besar

Dokumen ini mengatur prinsip, batas tanggung jawab, dan aturan implementasi untuk menangani akses, pemrosesan, dan perubahan data besar dalam arsitektur API dan backend agar sistem tetap efisien, stabil, dan dapat dijelaskan saat volume data meningkat.

## Ruang lingkup

Dokumen ini mengatur:
- apa yang dimaksud data besar dalam konteks sistem;
- prinsip desain untuk akses data besar;
- kapan memakai pagination, chunking, streaming, cursor access, batching, dan bulk write;
- kapan precompute, materialization, atau read model dibenarkan;
- aturan untuk menghindari N+1, full scan liar, full load ke memory, dan access pattern yang rapuh;
- profiling dan benchmark minimum;
- fallback saat storage atau index ideal belum tersedia;
- hubungan performa dengan repository, transaction boundary, dan logging operasional;
- anti-pattern umum untuk akses data besar.

Dokumen ini tidak mengatur:
- rule domain murni;
- kontrak response publik secara rinci;
- detail framework tertentu;
- detail konfigurasi database vendor tertentu;
- detail observability dashboard.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- data besar tidak diproses dengan pola naif yang merusak performa;
- penggunaan memory, waktu eksekusi, dan beban storage tetap terkendali;
- desain baca dan tulis untuk volume besar punya rumah dan strategi yang jelas;
- akses data besar tetap konsisten dengan boundary repository dan application service;
- kebutuhan optimisasi dapat dijelaskan, diuji, dan ditinjau;
- perubahan skala tidak memaksa sistem runtuh karena keputusan akses data yang buruk.

## Definisi

### Data besar

Dalam konteks dokumen ini, data besar berarti volume data yang cukup untuk membuat pendekatan sederhana menjadi tidak aman atau tidak efisien.

Data besar tidak harus berarti jutaan row. Data dianggap besar bila salah satu atau beberapa kondisi berikut benar:
- hasil query terlalu besar untuk dimuat sekaligus dengan aman;
- satu operasi berpotensi menyentuh ribuan atau lebih item secara rutin;
- join, agregasi, atau sort menjadi mahal;
- waktu eksekusi mulai mempengaruhi pengalaman pengguna atau window batch;
- memory usage meningkat tajam bila hasil dimaterialisasi sekaligus;
- retry, rerun, atau overlap job mulai berisiko tinggi.

### Full load

Full load adalah memuat seluruh hasil ke memory sekaligus sebelum diproses lebih lanjut.

### Chunking

Chunking adalah memproses data dalam potongan terbatas yang berurutan.

### Streaming

Streaming adalah membaca atau mengalirkan hasil secara bertahap tanpa mematerialisasi seluruh hasil di memory sekaligus.

### Cursor access

Cursor access adalah iterasi hasil secara bertahap dengan mekanisme cursor atau pointer resmi.

### Batching

Batching adalah mengelompokkan operasi baca atau tulis dalam satuan yang lebih efisien daripada item-per-item.

### Materialization

Materialization adalah menyiapkan hasil turunan, snapshot, atau read model yang disimpan untuk mengurangi beban komputasi baca berulang.

## Prinsip umum

- Desain untuk data besar harus eksplisit. Jangan mengasumsikan pola akses kecil akan tetap aman saat volume naik.
- Repository adalah rumah resmi untuk strategi akses data besar.
- Application service boleh mengatur flow batch atau chunk, tetapi tidak boleh mengambil alih query database dari persistence layer.
- Jangan memuat seluruh hasil ke memory bila tidak ada alasan kuat.
- Jangan memakai query item-per-item bila kebutuhan sebenarnya bisa dipenuhi dengan bulk access.
- Jangan mengandalkan optimisasi “nanti saja” untuk alur yang sejak awal jelas akan menyentuh data besar.
- Bila performa bergantung pada urutan, chunk size, snapshot, atau strategi read model, kontraknya harus jelas.
- Keputusan optimisasi harus bisa dijelaskan secara teknis, bukan sekadar “terasa lebih cepat”.

## Aturan keras

- Query resmi tetap hanya boleh berada di persistence layer.
- Full load untuk data besar dilarang kecuali ada alasan yang kuat, terukur, dan terdokumentasi.
- N+1 yang diketahui dan tidak terdokumentasi dianggap pelanggaran desain.
- Item-per-item write untuk volume besar dilarang bila bulk write yang aman dan jelas tersedia.
- Pagination, chunking, streaming, atau cursor access harus dipilih secara sadar, bukan acak.
- Precompute atau materialization boleh dipakai, tetapi harus memiliki alasan yang jelas, sumber data yang jelas, dan aturan refresh yang jelas.
- Optimisasi performa tidak boleh merusak kontrak data, idempotensi, atau correctness hasil.
- Benchmark dan profiling minimum wajib dilakukan untuk alur penting yang memproses data besar.
- Jangan mengklaim sebuah desain aman untuk skala besar bila belum diuji dengan volume representatif.

## Menentukan strategi akses

Strategi akses data besar harus ditentukan berdasarkan:
- tujuan alur: read publik, export, report, batch processing, import, reconciliation, sinkronisasi, atau maintenance;
- ukuran hasil;
- kebutuhan urutan;
- batas waktu;
- batas memory;
- kebutuhan konsistensi snapshot;
- kebutuhan partial progress;
- kebutuhan retry dan rerun.

## Pagination

Pagination cocok untuk:
- endpoint publik atau internal yang dikonsumsi secara berhalaman;
- hasil baca yang tidak perlu diproses semua sekaligus;
- interaksi manusia atau client yang memang mengambil data sebagian.

### Aturan pagination

- Bentuk page result harus jelas.
- Sort order yang dipakai harus resmi dan stabil.
- Pagination tidak boleh menghasilkan duplikasi atau lompatan liar tanpa dijelaskan kontraknya.
- Offset pagination boleh dipakai untuk skala ringan-menengah, tetapi harus ditinjau bila data besar dan offset tinggi mulai mahal.
- Keyset atau cursor-based pagination lebih tepat bila offset besar menjadi masalah.

## Chunking

Chunking cocok untuk:
- batch processing;
- migrasi;
- sinkronisasi;
- update besar;
- export yang diproses bertahap;
- perhitungan besar yang tidak aman bila semua hasil dimuat sekaligus.

### Aturan chunking

- Chunk size harus eksplisit dan dapat diubah secara terkontrol.
- Urutan chunk harus resmi bila urutan memengaruhi hasil.
- Jangan mengandalkan chunking tanpa key order yang stabil untuk proses yang sensitif terhadap perubahan data.
- Partial progress harus dapat diamati bila proses panjang.
- Chunking tidak boleh menyembunyikan biaya query yang tetap buruk di setiap potongan.

## Streaming

Streaming cocok untuk:
- export besar;
- proses baca satu arah;
- hasil yang terlalu besar untuk dimaterialisasi sekaligus.

### Aturan streaming

- Kontrak streaming harus jelas: siapa yang membaca, kapan resource ditutup, dan apakah hasil aman dipakai lintas boundary.
- Streaming tidak boleh mengembalikan object rapuh yang gagal saat koneksi berakhir tanpa penjelasan kontrak.
- Streaming perlu batasan yang jelas pada error handling, cancellation, dan timeout.
- Layer di atas repository harus tahu bahwa hasil bersifat streamed bila itu memengaruhi cara pemakaiannya.

## Cursor access

Cursor access cocok bila:
- hasil besar perlu diiterasi bertahap;
- keyset traversal lebih efisien daripada offset besar;
- job atau process perlu melanjutkan pembacaan secara terukur.

### Aturan cursor access

- Field atau key cursor harus stabil dan jelas.
- Urutan cursor harus resmi.
- Cursor tidak boleh bergantung pada hasil yang tidak terurut jelas.
- Persistence layer harus menentukan kontrak cursor; layer lain tidak boleh merakit cursor query sendiri.

## Batching dan bulk write

Bulk write dan batching lebih tepat untuk volume besar dibanding operasi item-per-item.

### Aturan batching

- Batch size harus eksplisit.
- Caller harus tahu apakah operasi berlaku per item, per batch, atau keduanya.
- Error handling harus jelas: fail-fast, continue-on-error, partial commit, atau strategi lain.
- Bulk write harus mempertimbangkan transaction boundary, lock, dan idempotensi.
- Bulk operation tidak boleh disamarkan sebagai operasi kecil biasa.

### Bulk write lebih tepat bila:
- banyak insert serupa;
- banyak update dengan pola yang bisa digabung;
- sinkronisasi atau upsert besar;
- recalculation atau rebuild yang menyentuh banyak row.

## Read model, precompute, dan materialization

Read model, precompute, atau materialization dibenarkan bila query langsung terlalu mahal, terlalu sering dipakai, atau terlalu kompleks untuk dijalankan berulang secara live.

### Dibenarkan bila:
- query live berulang sangat mahal;
- hasil yang dibutuhkan stabil atau berbasis snapshot;
- kebutuhan baca jauh lebih sering daripada update sumber;
- ada kebutuhan latency rendah untuk endpoint penting;
- perhitungan berat bisa dipindahkan ke proses batch yang resmi.

### Wajib dijelaskan bila dipakai:
- sumber data;
- mekanisme refresh;
- frekuensi refresh;
- toleransi stale data;
- identitas snapshot atau versi;
- recovery strategy bila refresh gagal.

### Larangan
- Membuat read model atau materialization tanpa owner yang jelas.
- Membiarkan stale data tanpa batas kontrak.
- Menyembunyikan perbedaan antara live data dan snapshot data dari consumer internal bila itu memengaruhi makna hasil.

## Menghindari N+1

N+1 adalah pola akses yang membuat satu operasi besar memicu banyak query tambahan yang tidak perlu.

### Aturan

- Repository harus menentukan strategi baca relasi secara eksplisit.
- Layer di atas repository tidak boleh memperbaiki N+1 dengan query liar sendiri.
- Lazy loading yang bocor ke service atau response layer dianggap pelanggaran.
- Bila relasi tidak perlu, jangan dibaca.
- Bila relasi perlu untuk banyak item, siapkan access pattern yang sesuai di persistence layer.

## Full scan, full load, dan sort mahal

### Full scan

Full scan harus diwaspadai untuk alur rutin. Bila full scan memang diperlukan, alasannya harus jelas dan dampaknya harus diukur.

### Full load

Full load dilarang untuk volume besar tanpa justifikasi kuat.

### Sort mahal

Sort terhadap dataset besar harus dipertimbangkan serius, terutama bila tidak didukung oleh strategi akses yang tepat.

### Aturan

- Jangan sort data besar di memory bila storage bisa melakukannya lebih tepat dan aman, kecuali ada alasan yang jelas.
- Jangan memindahkan seluruh data ke layer aplikasi hanya untuk filter atau sort yang bisa diselesaikan di persistence layer.
- Jangan melakukan full scan rutin untuk use case yang sebenarnya layak punya indeks, read model, atau precompute.

## Memory dan materialization di layer aplikasi

Layer aplikasi harus berhati-hati saat mematerialisasi data besar.

Aturan:
- hanya materialize hasil bila benar-benar perlu;
- gunakan iterasi bertahap bila memungkinkan;
- hindari menyimpan list besar di memory lebih lama dari yang diperlukan;
- jangan menggabungkan full read besar dengan mapping objek berat tanpa alasan kuat;
- memory safety harus dipertimbangkan dalam desain batch dan export.

## Konsistensi snapshot dan urutan

Untuk data besar, performa saja tidak cukup. Correctness juga penting.

Aturan:
- bila hasil harus konsisten terhadap snapshot tertentu, snapshot itu harus eksplisit;
- bila chunking atau cursor dipakai, aturan terhadap data yang berubah di tengah proses harus jelas;
- bila urutan memengaruhi hasil, urutan harus resmi;
- jangan mengandalkan urutan natural yang tidak dijanjikan storage.

## Profiling minimum

Sebelum atau saat merilis alur penting yang menyentuh data besar, lakukan profiling minimum.

Yang perlu diamati:
- jumlah row yang dibaca atau ditulis;
- waktu eksekusi end-to-end;
- waktu query utama;
- memory usage puncak;
- jumlah query;
- ukuran batch atau chunk;
- latency dependency lain bila relevan.

### Aturan

- Profiling harus menggunakan data yang cukup representatif.
- Jangan menyimpulkan performa dari dataset mainan.
- Profiling harus diulang saat desain akses diubah secara signifikan.

## Benchmark minimum

Benchmark diperlukan untuk keputusan desain yang berdampak besar.

Benchmark sebaiknya membandingkan:
- naive approach vs optimized approach;
- single write vs bulk write;
- full load vs chunking/streaming;
- live query vs read model;
- offset pagination vs keyset/cursor bila relevan.

### Aturan

- Benchmark harus mencatat asumsi.
- Benchmark harus menggunakan workload representatif.
- Hasil benchmark tidak boleh dipakai di luar konteksnya tanpa kehati-hatian.

## Fallback saat storage atau indeks ideal belum tersedia

Kadang sistem belum punya indeks, schema, atau storage layout yang ideal. Tetap perlu strategi sementara yang aman.

### Fallback yang dibenarkan
- chunking dengan urutan yang stabil;
- batching terbatas;
- windowed processing;
- prefilter kasar yang sah;
- pembatasan scope operasi sementara;
- scheduled processing di luar jam sibuk;
- read model sementara yang terdokumentasi.

### Fallback yang tidak dibenarkan
- memindahkan seluruh dataset ke memory lalu berharap cukup;
- menambah query liar di layer lain;
- membiarkan endpoint lambat tanpa batas;
- menjalankan update besar item-per-item tanpa kontrol;
- menyamarkan performa buruk dengan timeout lebih besar saja.

## Hubungan dengan repository

Repository adalah rumah resmi untuk strategi akses data besar.

Aturan:
- query, join, pagination, streaming, cursor, bulk write, dan strategi baca/tulis storage harus berada di persistence layer;
- application service boleh mengatur flow chunk atau batch, tetapi tidak boleh mengambil alih query;
- response layer tidak boleh menyentuh access pattern data besar secara langsung.

## Hubungan dengan transaction boundary

Transaction boundary harus dipilih hati-hati untuk alur data besar.

Aturan:
- transaksi terlalu besar dapat merusak lock dan throughput;
- batch besar mungkin perlu dibagi ke unit transaksi yang lebih kecil;
- bila correctness mengharuskan atomisitas penuh, biaya transaksi besar harus disadari dan diukur;
- partial failure strategy harus jelas untuk operasi besar yang tidak atomik penuh.

## Hubungan dengan logging operasional

Operasi data besar perlu logging yang cukup.

Log yang dianjurkan:
- start dan end process besar;
- chunk atau batch progress summary;
- row count penting;
- duration;
- retry atau rerun;
- partial failure summary.

Tidak dianjurkan:
- log per item besar-besaran tanpa nilai;
- dump payload besar;
- log query mentah sensitif sembarangan.

## Naming dan kontrak method

Method repository atau proses yang menyentuh data besar harus jelas.

Contoh lebih sehat:
- `streamActiveOrders()`
- `paginateInvestorSnapshots()`
- `processOutstandingInChunks()`
- `bulkUpsertAssetRows()`
- `fetchOrderPageByCursor()`

Contoh buruk:
- `getAll()`
- `processData()`
- `updateEverything()`
- `handleBatch()`

## Anti-pattern

- `getAll()` lalu filter dan sort di application service untuk dataset besar.
- Loop item-per-item yang tiap iterasi memicu query baru.
- Full load ratusan ribu row ke memory hanya untuk update sederhana.
- Bulk write besar disamarkan sebagai single update method.
- Query berat dijalankan di response layer atau controller.
- Tidak ada profiling untuk job yang jelas-jelas besar.
- Offset pagination dipakai tanpa batas untuk dataset sangat besar padahal latency sudah rusak.
- Materialization dibuat tetapi tidak ada aturan refresh atau stale data.
- Chunking tanpa urutan stabil.
- Cursor access tanpa kontrak cursor yang jelas.
