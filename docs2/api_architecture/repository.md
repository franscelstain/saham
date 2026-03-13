# Repository

Repository adalah rumah resmi untuk query dan akses persistence.
Semua interaksi database harus berada di persistence layer resmi seperti repository, query object, DAO, read model, atau adapter yang secara eksplisit ditetapkan setara dengannya.

## Tanggung jawab repository
- Membaca dan menulis data ke database.
- Menyusun query SQL, ORM query, filter, join, agregasi, windowing, dan operasi persistence lain.
- Menyediakan data yang dibutuhkan application service atau domain dengan bentuk yang stabil.
- Melakukan transformasi mekanis untuk kompatibilitas data jika diperlukan.

## Yang boleh dilakukan
- Query dan persistence.
- Mapping hasil query menjadi bentuk hasil baca yang konsisten.
- Fallback mekanis untuk kolom atau format data.
- Optimasi query dan penggunaan index sesuai kebutuhan akses data.

## Yang dilarang
- Menentukan rule bisnis, classifier, label, recommendation, eligibility, atau keputusan domain.
- Mengandung alur use case tingkat aplikasi.
- Menentukan format response publik API.

## Aturan keras
- Controller, transport boundary, application service, DTO, domain compute, presenter, dan helper umum dilarang melakukan query database langsung.
- Bila proyek menggunakan query object, read model, DAO, atau adapter persistence lain, komponen itu tetap dianggap bagian dari persistence layer dan tidak boleh tersebar di layer lain.
- Lazy loading tersembunyi yang membuat query terjadi di luar persistence layer dianggap pelanggaran arsitektur.
