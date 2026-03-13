# Contoh Implementasi End-to-End Tambahan

## Contoh 1: Endpoint read-only kompleks
1. Boundary menerima query parameter.
2. Boundary memvalidasi bentuk minimum.
3. Boundary membentuk filter DTO.
4. Boundary memanggil application service.
5. Service memanggil read repository.
6. Repository menangani filter, sort, dan pagination.
7. Repository mengembalikan page result.
8. Response layer membentuk output publik.

## Contoh 2: Integrasi provider eksternal
1. Boundary menerima request.
2. Boundary membentuk request DTO.
3. Service mengambil data lokal dari repository.
4. Service memanggil domain compute bila perlu.
5. Service memanggil adapter provider.
6. Service menyimpan hasil ke repository.
7. Response layer membentuk output publik.

## Contoh 3: Webhook receiver
1. Boundary memverifikasi signature.
2. Boundary memvalidasi shape payload.
3. Boundary membentuk event DTO.
4. Service memeriksa duplicate event.
5. Service memanggil repository dan domain compute.
6. Repository menyimpan perubahan state.

## Contoh 4: Queue consumer
1. Queue boundary menerima message.
2. Boundary mengekstrak message id, attempt, dan payload.
3. Boundary membentuk job DTO.
4. Service memeriksa idempotensi.
5. Repository mengambil data.
6. Domain compute menghitung hasil.
7. Repository menulis hasil.

## Contoh 5: Batch rerun flow
1. Scheduler memulai batch dengan run id.
2. Service menentukan mode rerun.
3. Repository mengambil item berdasarkan checkpoint atau status.
4. Service memproses per chunk stabil.
5. Domain compute menentukan hasil per item.
6. Repository menulis hasil secara batch.

## Contoh 6: Endpoint write dengan file upload
1. Boundary menerima multipart.
2. Boundary memvalidasi field dan file dasar.
3. Boundary membungkus input menjadi DTO dan file adapter yang aman.
4. Service memanggil domain compute bila perlu.
5. Repository menyimpan metadata.
6. Storage adapter menyimpan file.
7. Response layer membentuk output publik.
