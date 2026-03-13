# Domain Compute

Domain compute adalah rumah untuk rule bisnis murni dan perhitungan domain.
Fokusnya adalah logika yang menghasilkan keputusan, label, scoring, classifier, kalkulasi, atau derivasi domain lain.

## Tanggung jawab domain compute
- Menghitung hasil domain dari input yang diberikan.
- Menjalankan rule bisnis dan keputusan domain.
- Menghasilkan output domain yang deterministik untuk input yang sama.

## Yang boleh dilakukan
- Kalkulasi murni.
- Evaluasi rule.
- Scoring, classifier, planning, guard, dan keputusan domain lain.

## Yang dilarang
- Akses database.
- Membaca request framework.
- Logging, cache, I/O, network call, atau side effect lain.
- Membaca env atau konfigurasi langsung.

## Aturan keras
- Semua dependency yang diperlukan domain compute harus diberikan dari luar.
- Domain compute harus pure sejauh mungkin. Input sama harus menghasilkan output sama.
- Jika sebuah komponen menghitung keputusan bisnis berdasarkan kondisi, komponen itu bukan DTO dan bukan repository.
