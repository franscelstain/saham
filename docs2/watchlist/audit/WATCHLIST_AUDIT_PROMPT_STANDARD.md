# Watchlist Audit Prompt Standard

Gunakan prompt berikut saat audit ZIP baru.

```text
Audit ZIP ini secara ketat sebagai dokumen `watchlist`, bukan portfolio, bukan execution, dan bukan market-data internals.

Kunci arah sejak awal:
- domain aktif hanya `watchlist`
- active policy hanya `weekly_swing`
- watchlist hanya menampilkan saran
- data source realistis tetap dari provider gratis atau input manual

Rule inti yang wajib dicek:
- recommendation berasal dari PLAN only
- recommendation bisa tersedia tanpa confirm
- recommendation bisa kosong walau TOP_PICKS/SECONDARY ada
- confirm eligibility berasal dari candidate PLAN
- non-recommended candidate tetap bisa confirm
- confirm tidak mengubah recommendation
- recommended + confirmed hanya berarti confirm menguatkan

Audit file owner inti lebih dahulu:
- 01, 02, 03, 08, 09, 10, 13, 21, 22, 23, 24, 25

Lalu cek support docs:
- 07, _refs, examples, fixtures, db

Output wajib:
1. nilai akhir yang ketat
2. verdict singkat
3. tabel status PASS/PARTIAL/FAIL/N/A
4. temuan utama
5. file yang masih drift/conflict
6. patch prioritas berikutnya

Turunkan nilai jika:
- scope bocor ke portfolio/execution/market-data internals
- recommendation boundary tidak terkunci
- confirm boundary tidak terkunci
- support docs bertentangan dengan owner docs
```


## Baseline Rule

Gunakan audit baseline ini sebagai baseline tetap. Penguatan ZIP baru hanya boleh memperjelas, memperkaya, atau menutup gap kecil. Penguatan tidak boleh mengubah scope inti watchlist, boundary PLAN/RECOMMENDATION/CONFIRM, atau owner map aktif tanpa perubahan eksplisit pada audit foundation.
