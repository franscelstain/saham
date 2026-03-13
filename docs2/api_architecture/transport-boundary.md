# Transport Boundary

Dokumen ini mengatur layer yang berhubungan langsung dengan HTTP, CLI, message bus, atau mekanisme transport lain.

## Tanggung jawab transport boundary
- Menerima input mentah dari luar sistem.
- Melakukan parsing, validasi awal, dan pemetaan ke bentuk input yang eksplisit.
- Memanggil application service.
- Mengembalikan hasil ke format transport yang sesuai.

## Yang boleh dilakukan
- Membaca request mentah.
- Menangani otorisasi dan validasi transport-level.
- Mengubah payload mentah menjadi request DTO atau input object.
- Memilih HTTP status atau format response berdasarkan hasil aplikasi dan error mapping.

## Yang dilarang
- Query database langsung.
- Menulis SQL, ORM query builder, atau lazy-load model untuk kebutuhan bisnis.
- Menentukan rule bisnis, scoring, eligibility, atau keputusan domain.
- Menyusun kalkulasi inti domain.

## Aturan keras
- Objek request milik framework hanya boleh hidup di boundary ini.
- Setelah melewati boundary ini, layer di bawahnya dilarang menerima request mentah dari framework.
