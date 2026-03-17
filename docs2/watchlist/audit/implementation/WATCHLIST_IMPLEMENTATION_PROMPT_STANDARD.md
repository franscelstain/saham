Saya akan upload ZIP berisi implementation guidance, artefak implementasi, atau hasil review code watchlist.

Tugas Anda adalah mengaudit implementasi watchlist secara ketat berdasarkan baseline berikut:

Aturan aktivasi layer:
- bila ZIP hanya berisi implementation guidance, examples, fixtures, SQL support, schema docs, atau sample payload, audit sebagai **Layer B**;
- bila ZIP berisi code/app/payload runtime nyata/persistence runtime nyata yang cukup, baru aktifkan **Layer C**;
- source of truth bisnis berada di [`../../system/`](../../system/)
- implementasi tetap watchlist only
- fokus hanya `weekly_swing`
- bukan portfolio
- bukan execution
- bukan market-data internals
- flow runtime harus `PLAN -> RECOMMENDATION -> CONFIRM`
- recommendation berasal hanya dari PLAN immutable
- confirm berasal dari candidate PLAN
- non-recommended candidate tetap bisa confirm
- confirm tidak membentuk atau mengubah recommendation
- data input bisa berasal dari provider gratis atau input manual, dan implementasi harus tetap stabil

Yang wajib diaudit:
1. implementation scope and boundary
2. module mapping
3. runtime artifact flow
4. API/consumer guidance atau payload review
5. persistence guidance atau persistence review
6. test implementation guidance atau bukti test coverage
7. delivery checklist atau delivery readiness
8. service / repository / serializer / validator boundaries jika code/app nyata sudah tersedia

Output yang saya mau:
- nilai akhir numerik
- verdict
- tabel audit singkat PASS/PARTIAL/FAIL/N/A
- temuan utama
- patch prioritas berikutnya

Jangan mengaudit sebagai portfolio atau execution system. Audit ini khusus implementasi watchlist.


Jika code/app nyata tersedia, audit harus memprioritaskan bukti nyata tersebut di atas contoh guidance. Jika code/app nyata belum tersedia, nyatakan keterbatasan itu dengan jujur dan audit sebagai implementation guidance baseline Layer B, bukan Layer C.


Aturan scoring penting:
- jika Layer C tidak aktif, seluruh item audit yang khusus code/app/runtime nyata harus diberi `N/A`, bukan `PARTIAL`;
- jangan menurunkan nilai hanya karena service/controller/repository/payload runtime nyata memang belum ada pada ZIP guidance;
- `PARTIAL` untuk real-app evidence hanya sah bila Layer C aktif tetapi buktinya belum lengkap atau belum sinkron.
