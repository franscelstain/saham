# Response dan Error

Dokumen ini mengatur kontrak response publik API dan pemetaan error aplikasi ke bentuk output yang konsisten, dapat diprediksi, dan aman diekspos ke consumer.

## Ruang lingkup

Dokumen ini mengatur:
- bentuk response sukses;
- bentuk response error;
- klasifikasi error;
- pemetaan error ke HTTP status atau status transport setara;
- batas tanggung jawab response layer dan error mapping;
- informasi apa yang boleh dan tidak boleh diekspos ke consumer.

Dokumen ini tidak mengatur:
- rule bisnis;
- query database;
- keputusan domain;
- format log internal;
- detail implementasi framework tertentu.

## Tujuan

Aturan pada dokumen ini dibuat agar:
- semua endpoint memiliki bentuk output yang konsisten;
- consumer tidak perlu menebak-nebak struktur sukses dan gagal;
- error tidak bocor sembarangan;
- response dapat diuji sebagai kontrak;
- perubahan output diperlakukan sebagai perubahan kontrak, bukan perubahan sepele.

## Prinsip umum

- Output publik harus eksplisit, stabil, dan terdokumentasi.
- Response layer hanya menyajikan hasil aplikasi. Response layer bukan tempat rule bisnis, query, atau koreksi keputusan domain.
- Error harus diklasifikasikan. Error yang berbeda tidak boleh dilempar ke consumer dalam bentuk acak atau disamakan tanpa alasan.
- Satu kelas error harus dipetakan secara konsisten ke satu pola response yang jelas.
- Response publik tidak boleh membocorkan model persistence, query, stack trace, kredensial, host internal, atau detail teknis sensitif lain.

## Tanggung jawab response layer

Response layer bertanggung jawab untuk:
- mengubah output aplikasi ke bentuk response publik;
- menjaga konsistensi nama field, struktur data, dan metadata;
- menambahkan envelope response bila itu adalah kontrak resmi sistem;
- memastikan output final aman dikonsumsi oleh client.

Response layer tidak bertanggung jawab untuk:
- membuat query;
- mengambil data tambahan dari database;
- menghitung ulang hasil domain;
- menambah rule baru;
- memperbaiki data bisnis yang salah dari layer sebelumnya.

## Tanggung jawab error mapping

Error mapping bertanggung jawab untuk:
- menerima error dari layer aplikasi;
- mengklasifikasikan error sesuai kontrak resmi;
- memetakan error ke status transport final;
- membentuk payload error yang konsisten;
- memastikan detail sensitif tetap berada di log internal, bukan di response publik.

Error mapping tidak bertanggung jawab untuk:
- mengubah keputusan domain;
- menyamarkan error berbeda menjadi satu jenis hanya agar implementasi lebih mudah;
- menyisipkan detail teknis internal ke response publik.

## Yang dilarang

- Query database di response layer atau error mapping.
- Lazy loading model saat response dibentuk.
- Menentukan rule bisnis baru pada saat response dibentuk.
- Mengubah hasil domain yang sudah final.
- Mengembalikan model ORM mentah atau object persistence mentah sebagai response publik.
- Menyebarkan pemetaan error di banyak tempat tanpa kontrak yang jelas.
- Mengembalikan format sukses dan gagal yang berubah-ubah antar endpoint tanpa alasan resmi.
- Menggunakan HTTP 200 untuk kasus gagal hanya karena ingin menyederhanakan client.

## Aturan keras

- Bentuk output publik wajib ditetapkan secara eksplisit.
- Bentuk error publik wajib ditetapkan secara eksplisit.
- Semua endpoint dalam satu keluarga API wajib memakai pola response yang konsisten, kecuali ada pengecualian yang terdokumentasi.
- Perubahan field, rename field, penghapusan field, atau perubahan struktur envelope adalah perubahan kontrak.
- Error publik wajib punya `code` yang stabil dan dapat dirujuk.
- Message publik harus jelas bagi consumer, tetapi tidak boleh membocorkan detail internal.
- Detail teknis internal hanya boleh masuk ke log internal atau observability system, bukan ke response publik.

## Bentuk response sukses

Bentuk response sukses harus konsisten. Sistem boleh memakai envelope atau langsung data, tetapi pilihan itu harus tunggal dan resmi.

Contoh pola yang direkomendasikan:

```json
{
  "data": {},
  "meta": {
    "request_id": "req_123"
  }
}
```

Untuk list atau hasil berhalaman:

```json
{
  "data": [],
  "meta": {
    "request_id": "req_123"
  },
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 250,
    "has_next": true
  }
}
```

### Aturan response sukses

- `data` adalah payload utama yang dikonsumsi client.
- `meta` digunakan untuk metadata non-bisnis seperti `request_id`, `trace_id`, atau info versi bila memang resmi.
- `pagination` hanya boleh muncul untuk endpoint yang memang mendukung paginasi.
- Field yang tidak dipakai tidak boleh diisi secara acak hanya demi “seragam”.
- Bentuk sukses harus tetap konsisten walau `data` kosong.

### Response sukses dengan data kosong

Untuk query yang valid tetapi tidak menemukan hasil list, response tetap dianggap sukses bila secara kontrak memang itu perilaku resminya.

Contoh:

```json
{
  "data": [],
  "meta": {
    "request_id": "req_123"
  },
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 0,
    "has_next": false
  }
}
```

Data kosong tidak boleh dicampur dengan error not found tanpa aturan yang jelas. Sistem harus memilih satu perilaku resmi per jenis endpoint.

## Bentuk response error

Bentuk error publik harus konsisten dan stabil.

Contoh pola yang direkomendasikan:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Input tidak valid.",
    "details": null,
    "field_errors": {
      "email": [
        "Format email tidak valid."
      ]
    }
  },
  "meta": {
    "request_id": "req_123"
  }
}
```

### Aturan response error

- `error.code` wajib stabil dan dapat dipakai client untuk logika penanganan.
- `error.message` wajib dapat dibaca consumer dan tidak boleh terlalu teknis.
- `error.details` hanya dipakai bila kontraknya memang mengizinkan detail tambahan yang aman.
- `field_errors` hanya dipakai untuk validation error atau bentuk error lain yang memang mendukung detail per field.
- `meta.request_id` atau identitas sejenis sangat dianjurkan agar error bisa ditelusuri di sistem internal.

## Klasifikasi error

Sistem minimal harus membedakan kategori berikut:

### Validation error
Input tidak lolos validasi bentuk atau kontrak input.

Contoh:
- field wajib tidak ada;
- tipe data salah;
- format tanggal salah;
- nilai enum tidak dikenal.

### Authentication error
Client belum terautentikasi atau token/sesi tidak valid.

### Authorization error
Client terautentikasi tetapi tidak punya izin melakukan aksi.

### Not found
Resource yang diminta tidak ditemukan.

### Conflict
Terjadi benturan state atau constraint yang membuat operasi tidak dapat dilanjutkan.

Contoh:
- duplicate request yang tidak diizinkan;
- versi data tidak cocok;
- resource sudah dalam state yang bertentangan.

### Domain or business rule violation
Input valid secara bentuk, tetapi tidak lolos aturan domain.

Contoh:
- aksi tidak boleh pada state saat ini;
- limit bisnis terlampaui;
- operasi tidak sah menurut rule domain.

### Rate limit or throttling
Consumer melebihi batas pemakaian.

### Dependency or infrastructure failure
Kegagalan pada sistem dependensi atau infrastruktur, seperti service eksternal, queue, cache, atau storage.

### Internal server error
Kesalahan tak terduga yang tidak masuk kategori publik lain.

## Mapping error ke status transport

Status transport final harus ditentukan oleh error mapping terpusat atau mekanisme resmi yang setara. Penentuan ini tidak boleh tersebar liar di controller, service, presenter, dan helper secara acak.

Contoh pemetaan yang direkomendasikan untuk HTTP:

- `VALIDATION_ERROR` -> `400` atau `422`
- `AUTHENTICATION_ERROR` -> `401`
- `AUTHORIZATION_ERROR` -> `403`
- `NOT_FOUND` -> `404`
- `CONFLICT` -> `409`
- `DOMAIN_RULE_VIOLATION` -> `409` atau `422`, tetapi harus konsisten
- `RATE_LIMITED` -> `429`
- `DEPENDENCY_FAILURE` -> `502`, `503`, atau `504` sesuai kontrak
- `INTERNAL_ERROR` -> `500`

### Aturan mapping

- Satu jenis error tidak boleh dipetakan ke banyak status tanpa aturan resmi.
- Dua error berbeda tidak boleh dipetakan ke bentuk yang sama bila itu menghilangkan makna penting bagi consumer.
- Mapping HTTP atau status transport final tidak boleh dilakukan di domain compute.
- Service boleh mengklasifikasikan error aplikasi, tetapi tidak boleh mengubahnya menjadi response publik final secara liar.

## Error yang boleh diekspos

Boleh diekspos:
- kode error publik;
- pesan yang membantu consumer memahami kegagalan;
- detail validasi per field;
- identitas request untuk penelusuran;
- informasi retryable bila memang bagian kontrak resmi.

Tidak boleh diekspos:
- raw SQL;
- stack trace;
- nama tabel internal;
- detail host atau jaringan internal;
- token, secret, credential;
- payload sensitif;
- isi exception internal mentah tanpa sanitasi.

## Field errors untuk validasi input

Bila validation error dipakai, format field error harus konsisten.

Contoh:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Input tidak valid.",
    "field_errors": {
      "email": [
        "Field email wajib diisi.",
        "Format email tidak valid."
      ],
      "profile.birth_date": [
        "Format tanggal harus YYYY-MM-DD."
      ]
    }
  },
  "meta": {
    "request_id": "req_123"
  }
}
```

### Aturan field errors

- Nama field harus konsisten dengan nama field pada kontrak input publik.
- Nested field harus punya format penulisan yang konsisten.
- Validation error tidak boleh dicampur dengan domain error dalam satu `field_errors` tanpa aturan resmi.

## Pagination contract

Untuk endpoint paginasi, bentuk metadata paginasi harus tunggal dan konsisten.

Field yang direkomendasikan:
- `page`
- `per_page`
- `total`
- `has_next`

Sistem boleh memakai variasi lain, tetapi harus resmi dan konsisten di seluruh endpoint yang relevan.

### Larangan pagination

- Mengganti nama field pagination seenaknya per endpoint.
- Kadang memakai `page`, kadang `current_page`, kadang `offset`, tanpa kontrak resmi.
- Menaruh metadata paginasi di tempat yang berubah-ubah.

## Versioning dan kompatibilitas

Perubahan response publik adalah perubahan kontrak.

### Perubahan yang harus ditinjau serius

- menambah field baru;
- menghapus field;
- rename field;
- mengubah tipe field;
- mengubah arti field;
- mengubah envelope sukses atau gagal.

### Aturan kompatibilitas

- Perubahan harus mempertimbangkan backward compatibility.
- Consumer tidak boleh dipaksa menyesuaikan diri tanpa transisi bila kontraknya sebelumnya publik dan stabil.
- Perubahan kontrak wajib diikuti pembaruan dokumentasi dan test kontrak.

## Contoh response standar

### Sukses single object

```json
{
  "data": {
    "id": "ord_123",
    "status": "confirmed"
  },
  "meta": {
    "request_id": "req_123"
  }
}
```

### Sukses list

```json
{
  "data": [
    {
      "id": "ord_123",
      "status": "confirmed"
    }
  ],
  "meta": {
    "request_id": "req_123"
  },
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 1,
    "has_next": false
  }
}
```

### Validation error

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Input tidak valid.",
    "field_errors": {
      "email": [
        "Format email tidak valid."
      ]
    }
  },
  "meta": {
    "request_id": "req_123"
  }
}
```

### Domain error

```json
{
  "error": {
    "code": "ORDER_CANNOT_BE_CANCELLED",
    "message": "Pesanan tidak dapat dibatalkan pada status saat ini."
  },
  "meta": {
    "request_id": "req_123"
  }
}
```

### Not found

```json
{
  "error": {
    "code": "ORDER_NOT_FOUND",
    "message": "Pesanan tidak ditemukan."
  },
  "meta": {
    "request_id": "req_123"
  }
}
```

### Internal error aman

```json
{
  "error": {
    "code": "INTERNAL_ERROR",
    "message": "Terjadi kesalahan internal."
  },
  "meta": {
    "request_id": "req_123"
  }
}
```

## Anti-pattern

- Response sukses dan gagal memiliki shape yang berubah-ubah tanpa aturan resmi.
- Error gagal dikembalikan dengan HTTP 200 dan hanya dibedakan oleh field bebas di body.
- Stack trace diekspos ke consumer.
- Message internal exception dilempar mentah ke publik.
- Response layer melakukan query tambahan agar output “lebih lengkap”.
- Presenter atau resource mengakses lazy-loaded relation tanpa kontrol.
- Error mapping dilakukan berbeda-beda di controller, middleware, service, dan helper tanpa kontrak terpusat.
- Domain error dan validation error diperlakukan sama padahal maknanya berbeda.
