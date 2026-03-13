# Transport Boundary

Dokumen ini mengatur batas tanggung jawab, larangan, dan kontrak implementasi untuk transport boundary sebagai pintu masuk dan keluar komunikasi eksternal sistem, agar concern transport tidak bocor ke layer aplikasi, domain, dan persistence.

## Ruang lingkup

Dokumen ini mengatur:
- apa itu transport boundary;
- apa yang bukan transport boundary;
- tanggung jawab utama transport boundary;
- hubungan transport boundary dengan application service, DTO, response layer, auth, dan validasi;
- penanganan request input seperti path param, query param, header, body, multipart, dan uploaded file;
- aturan pemetaan request ke kontrak input eksplisit;
- error handling pada boundary transport;
- larangan umum pada controller, handler, endpoint, atau adapter transport lain.

Dokumen ini tidak mengatur:
- rule domain murni;
- query database;
- kontrak persistence;
- detail logging operasional secara rinci;
- detail framework tertentu.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- request mentah berhenti di batas transport;
- input eksternal diubah menjadi kontrak internal yang eksplisit;
- controller atau handler tidak menjadi tempat logic bisnis dan query;
- auth, validasi bentuk, dan mapping input memiliki rumah yang jelas;
- variasi transport tidak merusak kontrak aplikasi;
- perubahan protokol, framework, atau cara menerima request tidak memaksa layer bawah ikut berubah.

## Definisi

Transport boundary adalah layer yang menangani komunikasi antara sistem dan dunia luar.

Contoh transport boundary:
- HTTP controller atau handler;
- RPC endpoint;
- CLI command boundary;
- queue consumer boundary;
- webhook receiver;
- gRPC handler;
- scheduled trigger adapter yang memulai use case aplikasi.

Transport boundary bukan:
- application service;
- domain compute;
- repository;
- DTO itu sendiri;
- response mapper penuh untuk seluruh sistem;
- tempat rule bisnis utama.

## Prinsip umum

- Transport boundary menerima input mentah dari dunia luar dan mengubahnya menjadi kontrak aplikasi yang eksplisit.
- Transport boundary memahami protokol komunikasi, tetapi tidak boleh mengambil alih logika aplikasi dan domain.
- Transport boundary harus menjadi tempat terakhir di mana detail framework request hidup sebagai payload mentah.
- Setelah melewati transport boundary, layer di bawah tidak boleh bergantung pada request object framework, header bag, uploaded file wrapper, atau object protokol lain yang mentah.
- Transport boundary harus menjaga agar perubahan teknis protokol tidak bocor ke use case.

## Aturan keras

- Request framework object harus berhenti di transport boundary.
- Controller, handler, atau adapter transport dilarang menulis query database langsung.
- Controller, handler, atau adapter transport dilarang memuat rule domain utama.
- Transport boundary dilarang mengembalikan model persistence mentah sebagai response publik.
- Transport boundary dilarang memanggil repository langsung bila arsitektur resmi menetapkan application service sebagai pengendali use case.
- Transport boundary dilarang menjadi tempat logic orkestrasi panjang.
- Transport boundary dilarang membentuk payload internal yang kabur atau berubah-ubah seenaknya.
- Validasi bentuk input wajib dilakukan sebelum input diperlakukan sebagai kontrak internal yang sah.
- Error transport harus dipetakan secara konsisten dan tidak boleh bocor menjadi detail teknis liar.
- Auth dan authz yang bersifat boundary harus diselesaikan di boundary yang tepat dan tidak bocor secara mentah ke layer bawah.

## Tanggung jawab utama

Transport boundary bertanggung jawab untuk:
- menerima input dari protokol eksternal;
- mengekstrak path param, query param, header, body, cookie, metadata, file upload, atau payload lain yang relevan;
- melakukan parsing awal;
- melakukan validasi bentuk minimum sesuai kontrak transport;
- melakukan autentikasi atau memicu mekanisme autentikasi yang relevan;
- melakukan otorisasi tingkat boundary bila memang demikian desain sistem;
- membentuk DTO atau kontrak input eksplisit untuk application service;
- memanggil application service atau use case yang tepat;
- meneruskan hasil aplikasi ke response layer atau mapper output yang resmi;
- memastikan error input, auth, dan error transport lain dipetakan dengan benar.

## Yang bukan tanggung jawab transport boundary

Transport boundary bukan tempat untuk:
- query database langsung;
- mengatur flow use case panjang;
- menghitung skor, klasifikasi, atau keputusan domain;
- membentuk kontrak persistence;
- memetakan semua detail query repository;
- menjadi lokasi utility serba guna;
- menyimpan state domain;
- menambal kekurangan kontrak aplikasi dengan logika ad hoc di endpoint.

## Input mentah yang ditangani transport boundary

Transport boundary boleh menangani:
- route or path parameter;
- query parameter;
- header;
- request body;
- multipart form;
- uploaded file wrapper;
- auth token atau credential boundary;
- request metadata;
- message payload dari queue atau webhook.

### Aturan umum input mentah

- Input mentah hanya boleh dipakai di boundary transport.
- Input mentah harus diparse dan dipersempit sebelum masuk ke layer aplikasi.
- Field yang dipakai harus eksplisit dan terdokumentasi.
- Input yang tidak diakui kontrak tidak boleh diselundupkan diam-diam ke layer bawah.

## Path parameter

Path parameter adalah bagian dari kontrak route atau endpoint.

Aturan:
- path parameter harus diparse ke tipe yang sesuai;
- format dan keberadaan path parameter wajib divalidasi;
- path parameter tidak boleh diteruskan mentah tanpa shape yang jelas;
- route binding framework yang menghasilkan model persistence mentah tidak boleh diperlakukan sebagai kontrak aplikasi lintas layer.

## Query parameter

Query parameter umum untuk filter, sort, pagination, search, atau flag kontrol baca.

Aturan:
- query parameter harus diparse dan divalidasi bentuknya;
- nama field harus konsisten dengan kontrak input publik;
- query parameter harus dibungkus ke filter DTO atau object setara bila kompleksitasnya sudah lebih dari sangat sederhana;
- query parameter mentah tidak boleh dilempar begitu saja ke repository.

## Header dan metadata

Header dapat dipakai untuk:
- auth token;
- correlation id;
- locale;
- version hint;
- idempotency key;
- content negotiation;
- fitur teknis lain yang memang sah.

Aturan:
- hanya header yang relevan dengan kontrak resmi yang boleh dipakai;
- header mentah tidak boleh dibawa lintas layer sebagai payload utama;
- metadata penting harus diekstrak dan dibungkus secara eksplisit bila memang dibutuhkan layer bawah.

## Request body

Body request adalah sumber utama payload input untuk banyak endpoint.

Aturan:
- body harus diparse sesuai content type yang didukung;
- validasi bentuk minimum wajib dilakukan;
- field wajib, opsional, nested structure, dan tipe data harus jelas;
- body mentah tidak boleh dianggap otomatis valid hanya karena parse berhasil;
- setelah lolos validasi bentuk, body harus dibentuk menjadi DTO atau kontrak input eksplisit.

## Multipart dan uploaded file

File upload dan multipart memiliki kebutuhan boundary khusus.

Aturan:
- uploaded file wrapper framework harus berhenti di boundary transport atau adapter file boundary yang resmi;
- layer bawah tidak boleh bergantung pada object uploaded file mentah kecuali memang ada kontrak eksplisit untuk object adapter yang inert dan aman;
- validasi tipe file, ukuran, dan kelengkapan multipart harus dilakukan di boundary yang tepat;
- payload file tidak boleh dicampur sembarangan dengan kontrak domain tanpa adapter yang jelas;
- path file sementara, handle file, atau metadata framework mentah tidak boleh bocor ke layer bawah tanpa kontrak resmi.

## Validasi pada transport boundary

Transport boundary wajib menangani validasi bentuk input.

### Validasi yang tepat di transport boundary
- field wajib ada atau tidak;
- tipe data;
- format string, tanggal, angka, enum;
- struktur nested data;
- ukuran payload dasar;
- format pagination/filter;
- validitas file upload dasar;
- auth credential presence and format.

### Validasi yang tidak tepat di transport boundary bila sudah ada rumah lain
- keputusan domain apakah operasi boleh dilakukan;
- rule bisnis seperti eligibility, scoring, atau state transition domain;
- keputusan persistence yang bergantung pada query internal.

## Auth dan authz pada transport boundary

Transport boundary sering menjadi tempat pertama untuk auth dan authz yang bersifat request-level.

### Aturan
- autentikasi harus diselesaikan sebelum request diperlakukan sebagai panggilan sah ke use case yang butuh identitas caller;
- informasi identitas caller yang dibawa ke layer bawah harus dipersempit menjadi kontrak eksplisit, bukan request framework mentah;
- otorisasi yang murni berbasis boundary atau route dapat dilakukan di boundary;
- otorisasi yang bergantung pada rule domain dalam harus bekerja sama dengan layer aplikasi atau domain, tanpa membuat boundary memuat seluruh logic domain.

## Pemetaan ke kontrak input internal

Ini adalah salah satu tugas utama transport boundary.

Aturan:
- input mentah harus dipetakan ke DTO, command object, filter object, atau kontrak input eksplisit lain;
- mapping harus konsisten;
- mapping tidak boleh menjadi tempat rule bisnis berat;
- mapping tidak boleh menyisipkan field tersembunyi di luar kontrak resmi;
- perubahan mapping yang mengubah shape input internal adalah perubahan kontrak dan harus ditinjau serius.

## Hubungan dengan application service

Transport boundary memanggil application service atau use case yang tepat.

Aturan:
- transport boundary harus memanggil use case yang eksplisit;
- application service menerima input yang sudah dibentuk, bukan request framework mentah;
- transport boundary tidak boleh mengatur orchestration panjang yang seharusnya berada di application service;
- transport boundary boleh memilih use case berdasarkan route atau operation name, tetapi tidak boleh mengambil alih seluruh flow aplikasi.

## Hubungan dengan repository

Secara default, transport boundary tidak boleh berbicara langsung dengan repository bila arsitektur resmi menetapkan application service sebagai pengendali use case.

Aturan:
- query dan persistence tetap milik persistence layer;
- bila ada pengecualian read-only yang sangat khusus, pengecualian itu harus resmi, sempit, dan terdokumentasi. Secara default, jangan buka jalur ini;
- controller atau handler yang memanggil repository langsung berisiko merusak boundary service, DTO, dan handoff antar layer.

## Hubungan dengan response layer

Transport boundary berperan menyerahkan hasil aplikasi ke response layer atau mekanisme output resmi.

Aturan:
- transport boundary tidak boleh membentuk response publik liar yang berbeda-beda antar endpoint tanpa kontrak;
- response layer harus tetap menjadi tempat kontrak output publik bila itu desain resmi sistem;
- transport boundary boleh memilih status transport atau mekanisme penulisan response bila itu bagian dari adapter, tetapi shape output harus tetap tunduk pada kontrak resmi.

## Error handling di transport boundary

Transport boundary harus menangani error yang khas boundary dan meneruskan error aplikasi ke mekanisme response/error mapping yang resmi.

### Error yang umum di boundary transport
- malformed input;
- missing required parameter;
- invalid content type;
- file terlalu besar;
- auth gagal;
- authz awal gagal;
- unsupported method atau route;
- parse failure.

### Aturan
- error transport harus dibedakan dari domain error;
- error input tidak boleh diperlakukan sama dengan error internal;
- detail teknis sensitif tidak boleh dibocorkan;
- mapping ke response publik harus konsisten;
- exception framework mentah tidak boleh bocor langsung ke client tanpa sanitasi.

## CLI, queue consumer, dan boundary non-HTTP

Transport boundary bukan hanya HTTP. Prinsip yang sama berlaku untuk entry point lain.

### CLI boundary
- parse argumen dan opsi;
- validasi bentuk;
- ubah menjadi input eksplisit untuk use case.

### Queue consumer boundary
- parse message payload;
- validasi schema minimum;
- ekstrak metadata seperti message id atau attempt;
- bentuk payload internal yang eksplisit.

### Webhook boundary
- verifikasi signature atau sumber bila relevan;
- parse payload;
- validasi shape minimum;
- teruskan ke application service melalui kontrak yang eksplisit.

### Scheduler boundary
- bentuk parameter run secara eksplisit;
- jangan menyuntik context scheduler mentah ke layer bawah;
- jaga run id atau parameter eksekusi bila dibutuhkan.

## Logging di transport boundary

Transport boundary boleh membuat log operasional yang relevan.

Boleh log:
- route or action;
- request id;
- trace id;
- auth failure summary yang aman;
- validation failure summary yang aman;
- durasi;
- status akhir.

Tidak boleh:
- log seluruh payload mentah tanpa alasan;
- log secret atau credential;
- log response body sensitif mentah;
- log detail yang seharusnya menjadi concern layer lain tanpa konteks yang tepat.

## Naming controller, handler, dan endpoint adapter

Penamaan harus menjelaskan use case atau operasi, bukan nama teknis generik.

Contoh lebih sehat:
- `CreateOrderController`
- `CancelOrderHandler`
- `UserSearchEndpoint`
- `ImportInvestorWebhookReceiver`

Contoh buruk:
- `GeneralController`
- `CommonHandler`
- `ApiController`
- `ProcessEndpoint`

## Anti-pattern

- Controller menulis query database langsung.
- Handler memanggil repository langsung untuk seluruh flow bisnis tanpa service.
- Controller penuh if/else rule domain.
- Request framework object dilempar ke service.
- Route binding menghasilkan ORM model mentah lalu dilempar lintas layer.
- Transport boundary membentuk response liar per endpoint.
- Validation hanya setengah dilakukan lalu sisanya dipaksakan ke service tanpa kontrak yang jelas.
- Header, query param, dan body dicampur jadi array bebas lalu diteruskan mentah.
- Uploaded file wrapper framework dibawa sampai repository.
- Auth context mentah dari framework disuntik ke semua layer.
