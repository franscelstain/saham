# API Architecture Guidelines

Folder ini berisi pedoman arsitektur API yang bersifat framework-agnostic.
Dokumen di dalamnya ditujukan untuk menjaga batas tanggung jawab antar layer tetap jelas,
mencegah query dan rule bisnis tersebar sembarangan, serta membuat sistem lebih mudah dirawat,
diuji, dan dikembangkan tanpa salah tafsir.

## Tujuan folder ini
- Menetapkan aturan global arsitektur API.
- Menjelaskan perpindahan data antar layer.
- Menentukan rumah resmi untuk query database, rule bisnis, response, logging, dan concern operasional lain.
- Menjadi rujukan cepat: jika mencari topik tertentu, pembaca langsung tahu file yang harus dibuka.

## Urutan baca yang disarankan
1. `aturan-global-arsitektur-api.md`
2. `handoff-antar-layer.md`
3. `kontrak-dto.md`
4. `transport-boundary.md`
5. `application-service.md`
6. `repository.md`
7. `domain-compute.md`
8. `response-dan-error.md`
9. `performa-data-besar.md`
10. `determinisme-dan-idempotensi.md`
11. `logging-operasional.md`

## Buka file mana jika mencari topik tertentu
- Aturan global dan prinsip umum: `aturan-global-arsitektur-api.md`
- Perpindahan data antar layer: `handoff-antar-layer.md`
- DTO sebagai kontrak data: `kontrak-dto.md`
- Request/handler/controller/transport boundary: `transport-boundary.md`
- Orkestrasi aplikasi dan use case: `application-service.md`
- Query, SQL, ORM, persistence, dan akses database: `repository.md`
- Rule bisnis murni, scoring, classifier, perhitungan domain: `domain-compute.md`
- Bentuk response API dan pemetaan error: `response-dan-error.md`
- Streaming, batching, chunking, dan performa data besar: `performa-data-besar.md`
- Determinisme hasil, run ulang, dan idempotensi: `determinisme-dan-idempotensi.md`
- Logging, context log, severity, dan kebutuhan investigasi: `logging-operasional.md`

## Aturan penggunaan dokumen
- Tiap file punya satu fungsi utama. Jangan pindahkan aturan ke file lain jika rumah aturannya sudah jelas.
- Bila terjadi konflik antar file, aturan yang lebih spesifik mengalahkan aturan yang lebih umum.
- Bila topik belum punya rumah yang jelas, tambahkan file baru; jangan menyisipkan aturan baru ke file yang tidak relevan.
- Istilah seperti wajib, dilarang, hanya boleh, dan tidak boleh diperlakukan sebagai aturan normatif, bukan saran.
