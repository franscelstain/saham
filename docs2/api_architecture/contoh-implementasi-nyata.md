# Contoh Implementasi Nyata

Dokumen ini berisi contoh implementasi nyata untuk membantu menerjemahkan aturan arsitektur ke struktur kode dan alur kerja yang lebih konkret. Contoh di sini bersifat framework-agnostic. Nama komponen dapat disesuaikan dengan stack yang dipakai, tetapi boundary dan tanggung jawabnya harus tetap sama.

## Tujuan

Contoh di dokumen ini dipakai untuk:
- menunjukkan alur end-to-end yang sehat;
- memperjelas pemisahan transport, service, repository, domain, DTO, dan response;
- memberi pembanding antara pola yang sehat dan pola yang buruk;
- memudahkan tim memahami cara menerapkan dokumen arsitektur ke implementasi nyata.

## Contoh 1: Endpoint create order yang sehat

### Alur sehat

1. Transport boundary menerima request.
2. Transport boundary memvalidasi bentuk minimum.
3. Transport boundary membentuk `CreateOrderRequestDto`.
4. Transport boundary memanggil `CreateOrderService`.
5. Service meminta data yang diperlukan dari repository.
6. Service membentuk input untuk `OrderCreationPolicy` atau rule domain lain yang relevan.
7. Domain compute menentukan apakah order sah, bagaimana status awalnya, atau nilai turunan domain lain.
8. Service menyimpan hasil melalui repository.
9. Service mengembalikan `CreateOrderResultDto`.
10. Response layer membentuk response publik final.

### Struktur komponen yang sehat

- `transport-boundary`
  - `CreateOrderController`
- `application-service`
  - `CreateOrderService`
- `repository`
  - `OrderRepository`
  - `CustomerRepository`
- `domain-compute`
  - `OrderCreationPolicy`
  - `OrderInitialStateCalculator`
- `dto`
  - `CreateOrderRequestDto`
  - `CreateOrderResultDto`
- `response`
  - `CreateOrderResponseMapper`

### Ciri implementasi sehat

- controller tidak query database;
- controller tidak menentukan eligibility order;
- service tidak menulis SQL;
- domain compute tidak baca request framework;
- repository tidak memutuskan order boleh atau tidak;
- response layer tidak mengubah keputusan domain.

## Contoh 2: Endpoint create order yang buruk

### Alur buruk

1. Controller menerima request.
2. Controller langsung query customer.
3. Controller memeriksa rule bisnis dengan if/else panjang.
4. Controller menghitung score atau eligibility.
5. Controller memanggil ORM save.
6. Controller langsung return JSON final.

### Kenapa ini buruk

- query ada di transport boundary;
- rule domain ada di controller;
- service tidak punya rumah resmi;
- repository jadi tidak berguna;
- DTO biasanya tidak dipakai atau hanya formalitas;
- response shape sering berubah antar endpoint.

## Contoh 3: Batch update status besar yang sehat

### Kasus
Sistem perlu memperbarui status ribuan entitas berdasarkan aturan domain dan data persistence.

### Alur sehat

1. Scheduler atau CLI boundary memulai `ProcessStatusBatchService`.
2. Service menentukan parameter run, run id, dan chunk size.
3. Service meminta repository membaca kandidat dalam chunk stabil.
4. Service membentuk input domain yang fokus.
5. Domain compute menentukan status baru.
6. Service meminta repository melakukan bulk update yang sesuai.
7. Logging operasional mencatat ringkasan per chunk.
8. Sistem mencatat partial failure, retry, atau rerun secara eksplisit.

### Ciri implementasi sehat

- query tetap di repository;
- chunking resmi dan berurutan;
- domain compute tidak query database;
- bulk update tidak disamarkan sebagai update tunggal;
- retry dan rerun punya identitas jelas.

## Contoh 4: Batch update status besar yang buruk

### Pola buruk

- service memanggil `getAll()` untuk semua row;
- seluruh dataset dimuat ke memory;
- loop item-per-item memicu query tambahan;
- update dilakukan satu per satu tanpa batching;
- tidak ada run id;
- rerun menciptakan duplikasi atau hasil sulit dijelaskan.

### Kenapa ini buruk

- memory tidak terkontrol;
- performa rusak;
- idempotensi lemah;
- partial failure sulit dijelaskan;
- boundary repository dan performa data besar dilanggar.

## Contoh 5: Response dan error yang sehat

### Sukses

- service mengembalikan result object;
- response layer memetakan ke kontrak publik yang konsisten;
- field output stabil;
- metadata seperti request id konsisten.

### Error

- validation error dibedakan dari domain error;
- repository error tidak dilempar mentah ke client;
- internal error disanitasi;
- mapping error ke status transport konsisten.

## Contoh 6: DTO yang sehat vs DTO yang buruk

### DTO sehat

`CreateOrderRequestDto`
- hanya membawa field yang relevan;
- validasi bentuk minimum;
- tidak query;
- tidak punya method `process()` atau `save()`.

### DTO buruk

`OrderDto`
- punya `save()`;
- memanggil repository;
- menghitung eligibility;
- bentuk field berubah tergantung cabang logic.

## Contoh 7: Repository yang sehat vs repository yang buruk

### Repository sehat
- menerima filter atau input eksplisit;
- mengembalikan result object yang inert;
- tidak menentukan recommendation atau eligibility;
- menangani pagination, chunking, atau bulk write di persistence layer.

### Repository buruk
- menerima request framework object;
- mengembalikan query builder ke service;
- menentukan ranking bisnis;
- mengembalikan ORM model mentah dengan lazy loading aktif.

## Contoh 8: Domain compute yang sehat vs domain compute yang buruk

### Domain compute sehat
- menerima input domain yang terfokus;
- menghitung hasil bisnis murni;
- mengembalikan hasil yang eksplisit;
- tidak query database;
- tidak tahu response HTTP.

### Domain compute buruk
- membaca request object;
- memanggil repository;
- menulis log liar di setiap langkah;
- mengembalikan JSON response;
- memakai `now()` atau random secara tersembunyi tanpa kontrak.

## Contoh alur end-to-end ringkas

### Bentuk sehat

Transport -> Request DTO -> Application Service -> Repository + Domain Compute -> Result DTO -> Response Layer

### Bentuk buruk

Controller -> Query DB -> Rule bisnis -> Save langsung -> Return JSON final

## Cara memakai contoh ini

- Gunakan contoh sehat sebagai acuan implementasi.
- Gunakan contoh buruk sebagai red flag saat review.
- Jangan menyalin nama class secara mentah bila stack Anda berbeda.
- Yang harus dipertahankan adalah boundary, kontrak, dan rumah concern-nya.
