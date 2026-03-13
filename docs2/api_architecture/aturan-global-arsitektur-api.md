# Aturan Global Arsitektur API

Dokumen ini menetapkan aturan umum yang berlaku lintas layer.
Isinya tidak menggantikan file yang lebih spesifik, tetapi menjadi dasar untuk membaca seluruh dokumen lain.

## Prinsip dasar
- Setiap concern harus punya rumah yang jelas.
- Satu file dokumentasi hanya boleh punya satu fungsi utama.
- Satu layer aplikasi hanya boleh berubah karena satu jenis alasan utama.
- Query database, rule bisnis, formatting response, dan logging operasional tidak boleh bercampur.
- Kontrak data antar layer harus jelas dan terlihat.

## Aturan umum yang wajib dipatuhi
- Query dan akses persistence hanya boleh dilakukan pada persistence layer resmi.
- Rule bisnis dan keputusan domain hanya boleh dilakukan pada domain compute atau komponen domain yang secara eksplisit ditetapkan.
- Request mentah dari transport hanya boleh hidup di transport boundary.
- Response publik API harus dibentuk secara eksplisit dan tidak boleh mengandalkan model ORM mentah.
- Concern operasional seperti logging, retry, progress, batching, dan chunking tidak boleh menyusup ke pure domain logic.

## Larangan global
- Tidak boleh ada satu komponen yang sekaligus menangani request, query, rule bisnis, dan formatting response.
- Tidak boleh ada helper generik yang diam-diam berisi query database atau rule bisnis.
- Tidak boleh ada duplikasi logika inti pada beberapa layer berbeda.
- Tidak boleh ada perubahan kontrak data lintas layer tanpa perubahan dokumentasi dan penyesuaian konsumen.

## Hubungan dengan dokumen lain
- Alur perpindahan data dijelaskan di `handoff-antar-layer.md`.
- Kontrak data dijelaskan di `kontrak-dto.md`.
- Batas tiap layer dijelaskan di file layer masing-masing.
