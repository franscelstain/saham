# Checklist Review Arsitektur

Dokumen ini adalah checklist ringkas untuk review desain, review PR, dan audit arsitektur API/backend. Dokumen ini tidak menggantikan dokumen utama. Dokumen ini membantu reviewer menemukan pelanggaran paling penting dengan cepat.

## Cara pakai

- Pakai checklist ini saat mereview fitur baru, refactor besar, endpoint baru, job baru, integrasi baru, atau perubahan persistence.
- Bila ada jawaban “tidak jelas” atau “tidak”, reviewer harus kembali ke dokumen spesifik yang relevan.
- Checklist ini ringkas. Bila perlu detail, lihat file concern yang sesuai.

## Review global

- Apakah concern utama punya rumah yang jelas?
- Apakah implementasi mengikuti `aturan-global-arsitektur-api.md`?
- Apakah ada shortcut “sementara” yang sebenarnya merusak boundary?

## Transport boundary

- Apakah request mentah berhenti di transport boundary?
- Apakah controller/handler bebas dari query langsung?
- Apakah controller/handler bebas dari rule domain utama?
- Apakah input dipetakan ke kontrak internal yang eksplisit?
- Apakah auth, validasi bentuk, dan error transport ditangani di boundary yang tepat?

Lihat:
- `transport-boundary.md`

## Application service

- Apakah service hanya mengorkestrasi use case?
- Apakah service bebas dari query database langsung?
- Apakah service bebas dari response publik final?
- Apakah transaction boundary jelas?
- Apakah service tidak tumbuh menjadi god service?

Lihat:
- `application-service.md`

## Repository

- Apakah query resmi hanya ada di persistence layer?
- Apakah repository bebas dari rule bisnis utama?
- Apakah input dan output repository eksplisit?
- Apakah tidak ada lazy loading tersembunyi yang bocor?
- Apakah bulk access, pagination, streaming, atau batching ditangani di persistence layer yang benar?

Lihat:
- `repository.md`

## Domain compute

- Apakah rule bisnis murni berada di domain compute?
- Apakah domain compute bebas dari query, request framework, dan response publik?
- Apakah input/output domain fokus dan eksplisit?
- Apakah hasil domain deterministik terhadap input dan policy yang sah?
- Apakah service, repository, atau controller tidak mengambil alih rule domain utama?

Lihat:
- `domain-compute.md`

## DTO dan handoff

- Apakah DTO hanya membawa data?
- Apakah DTO bebas dari query, service call, dan side effect?
- Apakah handoff antar layer eksplisit?
- Apakah request framework, ORM model mentah, atau query builder tidak bocor lintas layer?
- Apakah array liar tidak dipakai sebagai kontrak utama?

Lihat:
- `kontrak-dto.md`
- `handoff-antar-layer.md`

## Response dan error

- Apakah shape sukses konsisten?
- Apakah shape error konsisten?
- Apakah kode error stabil?
- Apakah mapping error ke status transport jelas?
- Apakah detail sensitif tidak bocor ke response publik?

Lihat:
- `response-dan-error.md`

## Performa data besar

- Apakah strategi akses data sesuai dengan volume?
- Apakah full load dihindari bila tidak perlu?
- Apakah N+1 dicegah?
- Apakah chunking, streaming, pagination, cursor, atau batching dipilih secara sadar?
- Apakah profiling atau benchmark minimum sudah dipertimbangkan untuk alur besar?

Lihat:
- `performa-data-besar.md`

## Determinisme dan idempotensi

- Apakah operasi penting jelas status idempotensinya?
- Apakah retry aman?
- Apakah rerun aman?
- Apakah duplicate request atau duplicate event dapat dideteksi?
- Apakah partial failure dapat dijelaskan dan ditangani?

Lihat:
- `determinisme-dan-idempotensi.md`

## Logging operasional

- Apakah log punya tujuan operasional yang jelas?
- Apakah identitas korelasi tersedia?
- Apakah data sensitif tidak bocor ke log?
- Apakah retry, rerun, duplicate, dan partial failure dapat ditelusuri?
- Apakah volume log masih masuk akal?

Lihat:
- `logging-operasional.md`

## Red flags cepat

Bila salah satu ini muncul, review harus diperdalam:
- controller query database langsung;
- service penuh if/else bisnis panjang;
- repository menentukan recommendation atau eligibility;
- DTO memanggil service atau query;
- response layer melakukan lazy loading;
- retry dilakukan tanpa identitas operasi;
- log berisi secret, token, atau payload sensitif mentah;
- full load besar dilakukan tanpa alasan yang jelas;
- ORM model mentah dilempar lintas layer;
- pengecualian “sementara” mulai dipakai berulang.

## Putusan review

Sebuah perubahan layak dianggap sehat bila:
- rumah concern tetap jelas;
- kontrak input/output tetap eksplisit;
- persistence, domain, transport, dan response tidak bercampur;
- performa, retry, rerun, dan logging sudah dipikirkan sesuai konteks;
- tidak ada shortcut yang merusak boundary untuk menyelesaikan kebutuhan jangka pendek.
