# Repository

Dokumen ini mengatur batas tanggung jawab, larangan, kontrak input dan output, serta aturan implementasi untuk repository sebagai rumah resmi akses persistence dan query dalam arsitektur API dan backend.

## Ruang lingkup

Dokumen ini mengatur:
- apa itu repository;
- apa yang bukan repository;
- tanggung jawab utama repository;
- hubungan repository dengan application service, domain compute, dan persistence framework;
- bentuk input dan output repository;
- aturan query, write, bulk operation, pagination, streaming, dan batching;
- batas repository terhadap transaction boundary;
- perbedaan repository, query object, DAO, read model, dan persistence adapter;
- anti-pattern umum pada repository.

Dokumen ini tidak mengatur:
- rule domain murni;
- kontrak response publik;
- detail DTO secara umum;
- detail logging operasional secara rinci;
- detail framework tertentu.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- akses database dan persistence berada di rumah yang jelas;
- query tidak bocor ke controller, service, response layer, atau DTO;
- repository tidak berubah menjadi tempat keputusan bisnis;
- hasil baca dan tulis ke persistence memiliki kontrak yang eksplisit;
- akses data besar tetap terkontrol;
- boundary antara aplikasi, domain, dan persistence tetap tegas;
- perubahan query dan storage bisa dikelola tanpa merusak layer lain.

## Definisi

Repository adalah komponen persistence layer yang bertugas membaca dan menulis data ke storage resmi sistem sesuai kebutuhan aplikasi, melalui kontrak yang eksplisit dan dapat diuji.

Repository adalah rumah resmi untuk:
- query;
- operasi baca;
- operasi tulis;
- agregasi persistence;
- bulk access yang sah;
- mapping hasil persistence ke bentuk yang sah untuk layer di atas.

Repository bukan:
- application service;
- domain compute;
- controller atau handler;
- response mapper;
- DTO;
- logger umum;
- helper serba guna;
- tempat rule bisnis utama.

## Prinsip umum

- Semua akses persistence resmi harus berada di persistence layer.
- Repository bertugas memahami storage, bukan memutuskan makna bisnis akhir.
- Repository boleh kompleks pada sisi query dan access pattern, tetapi tidak boleh mengambil alih rule domain.
- Repository harus menerima kebutuhan data yang eksplisit dan mengembalikan hasil yang eksplisit.
- Repository tidak boleh memaksa layer di atas memahami detail tabel, join, index, atau strategi akses storage.
- Repository tidak boleh menyelundupkan object persistence hidup yang memicu query tersembunyi di layer lain.
- Bila proyek memakai komponen khusus seperti Query Object, DAO, Read Model, atau Persistence Adapter, komponen tersebut tetap dianggap bagian persistence layer dan harus tunduk pada aturan dokumen ini.

## Aturan keras

- Query database hanya boleh dilakukan di persistence layer resmi.
- Controller, handler, application service, domain compute, DTO, dan response layer dilarang menulis query database langsung.
- Repository dilarang menjadi tempat rule bisnis utama, scoring, klasifikasi, eligibility, atau keputusan domain murni.
- Repository dilarang mengembalikan ORM model mentah dengan lazy loading aktif sebagai payload utama lintas layer.
- Repository dilarang mengembalikan query builder untuk diteruskan ke layer lain.
- Repository dilarang menerima request framework object sebagai kontrak.
- Repository dilarang membentuk response publik final.
- Repository dilarang menyimpan logika presentasi.
- Repository dilarang menyembunyikan side effect teknis yang membuat caller tidak mengerti dampak operasi persistence.
- Repository dilarang memakai array liar yang bentuknya berubah-ubah sebagai kontrak utama bila bentuk resmi sudah bisa dibuat eksplisit.

## Tanggung jawab utama repository

Repository bertanggung jawab untuk:
- membaca data dari persistence sesuai kebutuhan use case;
- menulis perubahan state ke persistence sesuai kontrak aplikasi;
- menyusun query, join, filter, agregasi, windowing, atau strategi baca lain yang memang dibutuhkan;
- melakukan bulk read atau bulk write bila use case membutuhkannya;
- menerapkan pagination, streaming, chunking, batching, atau cursor access sesuai kebutuhan teknis;
- memetakan hasil persistence ke bentuk yang sah untuk layer di atas;
- menghormati transaction boundary yang ditetapkan arsitektur;
- menjaga agar akses data efisien dan tidak menghasilkan pola berbahaya seperti N+1 yang tidak sah.

## Yang bukan tanggung jawab repository

Repository bukan tempat untuk:
- memutuskan apakah aksi bisnis boleh atau tidak boleh dilakukan;
- menghitung scoring, ranking, atau klasifikasi domain utama;
- memutuskan recommendation, eligibility, priority, atau policy bisnis;
- membaca request framework;
- membentuk response publik;
- mengelola flow use case end-to-end;
- menjadi pusat validasi bentuk data transport;
- menjadi penampung utility acak yang kebetulan menyentuh database.

## Hubungan dengan application service

Application service berkomunikasi dengan repository untuk kebutuhan baca dan tulis data.

Aturan:
- Service menyatakan kebutuhan data secara eksplisit.
- Repository mengeksekusi akses persistence sesuai kontrak.
- Service tidak boleh memerintahkan repository lewat object yang kabur.
- Repository tidak boleh memaksa service memahami detail tabel atau strategi query.
- Repository tidak boleh mengambil alih orchestration use case dari service.
- Repository boleh dipanggil oleh beberapa service, tetapi kontraknya harus tetap fokus dan tidak berubah-ubah tanpa alasan resmi.

## Hubungan dengan domain compute

Domain compute adalah rumah rule bisnis murni.

Aturan:
- Repository boleh menyediakan data yang diperlukan oleh domain compute melalui service.
- Repository tidak boleh mengambil alih rule domain yang seharusnya dijalankan di domain compute.
- Repository boleh melakukan transformasi teknis yang diperlukan untuk membaca atau menyimpan data, tetapi tidak boleh mengubah makna bisnis hasil hanya demi kenyamanan query.
- Bila ada derivasi yang murni bersifat persistence-oriented, hal itu boleh ada di repository selama tidak berubah menjadi keputusan domain.

## Hubungan dengan persistence framework

Repository boleh memakai ORM, query builder, raw SQL, stored procedure, driver resmi, atau mekanisme persistence lain yang sesuai arsitektur proyek.

Aturan:
- Layer di atas repository tidak boleh bergantung pada teknologi persistence yang dipakai repository.
- Pergantian teknik query internal repository tidak boleh mengubah kontrak repository tanpa alasan resmi.
- Pemilihan ORM, raw SQL, atau strategi lain adalah keputusan internal persistence layer, selama tetap memenuhi kontrak.

## Input repository

Input repository harus eksplisit dan terstruktur.

Bentuk input yang direkomendasikan:
- query or filter DTO;
- criteria object;
- command or input DTO untuk operasi tulis;
- primitive yang terbatas dan sangat jelas bila kompleksitasnya kecil.

Input repository tidak boleh:
- berupa request framework object;
- berupa response object;
- berupa query builder dari layer lain;
- berupa payload liar yang memaksa repository menebak kebutuhan sebenarnya;
- membawa dependency teknis tersembunyi.

## Output repository

Output repository harus eksplisit, inert, dan aman dipakai layer di atas.

Bentuk output yang direkomendasikan:
- result or read DTO;
- row or result object yang stabil;
- entity atau value object bila memang diizinkan secara resmi oleh arsitektur dan tidak bocor concern persistence;
- primitive yang sangat terbatas untuk kasus sederhana.

Output repository tidak boleh:
- berupa ORM model mentah dengan lazy loading aktif;
- berupa query builder yang harus diteruskan;
- berupa connection-bound object yang hanya valid selama sesi persistence tertentu masih hidup;
- berupa shape liar yang berubah-ubah tanpa kontrak;
- menyembunyikan kebutuhan query tambahan saat field dibaca.

## Return shape repository

Kontrak hasil repository harus dapat dijelaskan secara eksplisit.

Aturan:
- untuk operasi read, repository harus jelas apakah mengembalikan single result, list result, stream, cursor wrapper resmi, page result, atau optional result;
- untuk operasi write, repository harus jelas apakah mengembalikan status, affected count, updated result, generated identifier, atau hasil lain yang sah;
- repository tidak boleh kadang mengembalikan object, kadang array, kadang boolean, untuk operasi yang setara tanpa kontrak resmi;
- hasil kosong harus ditangani secara konsisten;
- not found harus punya kontrak yang jelas, misalnya null, optional result, atau exception layer yang sesuai, tetapi tidak boleh berubah-ubah sembarangan.

## Query di repository

Query adalah tanggung jawab resmi repository.

Yang boleh ada di repository:
- filter;
- sort;
- join;
- aggregation;
- window function;
- pagination query;
- cursor query;
- streaming query;
- chunked read;
- bulk write query;
- stored procedure invocation;
- upsert, merge, dan strategi persistence lain yang resmi.

Yang tidak boleh ada di repository:
- keputusan domain utama;
- penentuan hasil recommendation;
- pengambilan keputusan bisnis yang bukan sekadar filter persistence;
- branching rule bisnis panjang yang seharusnya hidup di service atau domain.

## Read repository dan write repository

Proyek boleh memisahkan read dan write repository bila memang bermanfaat.

### Pemisahan dibenarkan bila:
- read path jauh lebih kompleks dari write path;
- ada read model khusus;
- ada optimisasi khusus untuk endpoint baca berat;
- ada kebutuhan CQRS-like separation secara ringan atau penuh.

### Pemisahan tidak wajib bila:
- kompleksitas masih masuk akal;
- satu repository masih tetap fokus dan jelas.

### Aturan
- bila read dan write dipisah, keduanya tetap bagian dari persistence layer resmi;
- pemisahan tidak boleh membuat kontrak layer di atas jadi kabur;
- read-specific optimization tidak boleh bocor ke service sebagai detail teknis liar.

## Query Object, DAO, Read Model, dan Persistence Adapter

Bila proyek memakai istilah selain repository, aturan boundary tetap berlaku.

### Query Object
Boleh dipakai untuk query baca yang kompleks dan fokus. Tetap bagian persistence layer.

### DAO
Boleh dipakai untuk akses data yang lebih dekat ke tabel. Tetap bagian persistence layer.

### Read Model
Boleh dipakai untuk hasil baca yang dioptimalkan bagi kebutuhan tertentu. Tetap bagian persistence layer bila sumbernya persistence resmi.

### Persistence Adapter
Boleh dipakai untuk mengisolasi detail storage atau provider tertentu. Tetap bagian persistence layer.

### Aturan
- Komponen-komponen ini tidak boleh dipakai sebagai celah untuk menaruh query di service atau controller.
- Letaknya harus tetap di area persistence layer yang jelas.
- Kontraknya harus tetap eksplisit.
- Rule bisnis utama tetap dilarang masuk ke komponen ini.

## Pagination, streaming, chunking, dan cursor access

Repository adalah tempat resmi untuk mengelola access pattern baca besar.

### Pagination
Dipakai untuk hasil baca berhalaman yang dikonsumsi oleh client atau service.

### Streaming
Dipakai untuk hasil yang terlalu besar bila dimuat sekaligus.

### Chunking
Dipakai untuk memproses data besar bertahap di memory yang terkendali.

### Cursor access
Dipakai bila iterasi bertahap lebih tepat daripada materializing seluruh hasil.

### Aturan
- Pilihan access pattern harus sesuai kebutuhan use case dan volume data.
- Layer di atas repository tidak boleh mengimplementasikan ulang strategi ini dengan query sendiri.
- Repository harus jelas apakah hasil yang dikembalikan aman untuk diiterasi lintas boundary atau hanya aman di dalam closure atau scope tertentu.
- Jangan mengembalikan mekanisme iterasi yang rapuh tanpa kontrak yang jelas.

## Bulk operation

Repository boleh melakukan bulk read dan bulk write.

Aturan:
- bulk operation harus eksplisit;
- jumlah item yang diproses harus bisa dijelaskan;
- perilaku saat partial success atau failure harus jelas;
- untuk operasi besar, repository harus bekerja sama dengan aturan idempotensi, transaction boundary, dan logging operasional;
- bulk write tidak boleh dilakukan diam-diam di method yang terlihat seperti single-item update.

## N+1 dan lazy loading tersembunyi

Repository harus menghindari pola akses data yang merusak performa atau melanggar boundary.

Aturan:
- repository tidak boleh dengan sengaja mengembalikan model yang akan memicu lazy loading di layer di atas;
- bila data relasional dibutuhkan, repository harus memutuskan strategi baca yang sesuai di persistence layer;
- N+1 yang diketahui dan tidak terdokumentasi dianggap pelanggaran desain;
- layer di atas repository tidak boleh dipaksa memperbaiki N+1 dengan query tambahan sendiri.

## Transaction boundary

Repository harus menghormati transaction boundary yang ditetapkan arsitektur.

Aturan:
- secara umum, transaction boundary dimiliki application service atau unit of work aplikasi yang resmi;
- repository boleh berpartisipasi dalam transaksi yang sudah dibuka di atasnya;
- repository tidak boleh diam-diam membuka transaksi besar sendiri tanpa kontrak resmi;
- repository boleh memakai transaksi internal sempit hanya bila itu merupakan detail teknis yang aman, terdokumentasi, dan tidak mengganggu kontrak yang lebih besar;
- caller harus memahami efek penting operasi tulis, bukan dibutakan oleh transaksi tersembunyi yang luas.

## Error handling di repository

Repository boleh:
- menangkap error persistence teknis untuk menambah konteks teknis yang berguna;
- mengubah error driver atau framework menjadi error persistence layer yang lebih bermakna;
- mengembalikan hasil kosong sesuai kontrak resmi.

Repository tidak boleh:
- memetakan error menjadi response publik;
- menyamarkan error persistence serius sebagai sukses palsu;
- mencampur error teknis dengan domain rule violation seolah sama;
- menelan error yang membuat caller kehilangan informasi penting.

## Caching dan repository

Bila caching dipakai, posisinya harus tetap jelas.

Aturan:
- cache terkait hasil persistence boleh dikelola melalui persistence layer atau adapter resmi yang terkait, selama kontraknya jelas;
- repository tidak boleh diam-diam mengubah semantics hasil karena cache tanpa aturan yang bisa dijelaskan;
- invalidation, TTL, dan cache key penting harus bisa dijelaskan;
- cache tidak boleh dipakai untuk menutupi query atau kontrak repository yang buruk.

## Logging di repository

Repository boleh membuat log operasional yang relevan untuk persistence.

Boleh log:
- jumlah row penting;
- kegagalan write atau query;
- durasi query tingkat tinggi;
- bulk operation summary.

Tidak boleh:
- log raw SQL dan parameter sensitif sembarangan;
- log setiap operasi kecil tanpa nilai operasional;
- log data sensitif tanpa sanitasi.

## Naming repository

Penamaan repository harus jelas dan fokus.

Contoh yang lebih sehat:
- `OrderRepository`
- `UserReadRepository`
- `SettlementWriteRepository`
- `InvestorSnapshotReadModel`

Contoh yang buruk:
- `GeneralRepository`
- `CommonRepository`
- `MasterRepository`
- `DatabaseService`

## Anti-pattern

- Query di controller atau service lalu “dibungkus” tipis oleh repository palsu.
- Repository memutuskan recommendation atau eligibility.
- Repository mengembalikan ORM model mentah untuk semua kasus.
- Repository melempar query builder ke service agar service bisa “lanjut filter sendiri”.
- Repository membaca request framework object.
- Repository membentuk response JSON.
- Repository method namanya generik dan isinya melakukan terlalu banyak hal.
- Repository write tunggal diam-diam melakukan bulk update besar tanpa kontrak yang jelas.
- Repository mencampur query, domain rule, response shaping, dan logging liar dalam satu method.
- Repository yang sebenarnya hanya helper utilitas tanpa kontrak persistence yang jelas.

## Active-System Repository Roles

Pada sistem aktif saat ini, repository/read-adapter perlu dibedakan minimal menjadi tiga peran:

1. **Producer-facing intake read adapter**
   - membaca output upstream `market_data` yang consumer-facing dan publication-aware;
   - mengembalikan intake DTO / normalized result object;
   - tidak menjalankan policy watchlist.

2. **Consumer artifact repository**
   - menyimpan dan membaca artifact PLAN / RECOMMENDATION / CONFIRM milik watchlist;
   - tidak mengubah meaning intake producer.

3. **Response/read materialization adapter**
   - membantu membentuk read model/composite read untuk consumer exposure;
   - tetap tidak boleh menjadi rumah policy baru.

## Intake Read vs Internal Persistence

Repository untuk intake upstream **tidak sama** dengan repository untuk persistence artifact internal consumer.

Perbedaan minimumnya:
- intake read adapter tunduk pada kontrak producer-facing;
- artifact repository tunduk pada persistence contract consumer;
- keduanya tidak boleh digabung menjadi satu object serba bisa yang mencampur semantik intake dan semantik artifact write.

## What a Consumer Read Adapter May Do

Boleh:
- membaca publication-aware consumer-facing output;
- memetakan hasil baca ke intake DTO/result object yang stabil.

Tidak boleh:
- membaca raw internals producer sebagai shortcut;
- menghitung ranking/scoring watchlist;
- memutuskan validitas policy internal consumer;
- langsung membentuk response transport final.

