# System Documentation Root

## 1. Purpose

Folder `docs/` adalah **source of truth dokumentasi sistem** untuk sistem aktif.

Dokumen di dalam folder ini dipakai sebagai panduan resmi untuk:
- memahami batas domain sistem,
- menentukan ownership tiap area,
- menetapkan kontrak data dan perilaku sistem,
- membimbing implementasi kode,
- menjaga agar pembangunan fitur tidak menyimpang dari aturan yang sudah dikunci.

Dokumen di sini harus dibaca sebagai **spesifikasi sistem**, bukan catatan sementara, opini, atau ide bebas.

---

## 2. How this documentation must be used

Saat membangun, mengubah, atau meninjau sistem:

1. **Jangan mulai dari asumsi pribadi.**  
   Mulailah dari dokumen domain yang relevan.

2. **Ikuti ownership folder.**  
   Jangan mengambil aturan dari folder yang bukan pemilik domainnya.

3. **Dokumen normatif lebih tinggi daripada contoh, catatan referensi, atau interpretasi implementasi.**

4. **Jangan membuat kontrak paralel.**  
   Jika suatu domain sudah memiliki dokumen authoritative, jangan mendefinisikan ulang kontrak yang sama di tempat lain.

5. **Contoh tidak menggantikan kontrak.**  
   Contoh hanya membantu verifikasi, bukan menjadi sumber aturan utama.

6. **Implementasi kode wajib mengikuti kontrak dokumen, bukan membentuk kontrak baru sendiri.**

7. **Jika ada konflik antar dokumen, ikuti hirarki source of truth yang dijelaskan di README ini dan README domain terkait.**

8. **Consumer domain tidak boleh bebas memilih jalur baca upstream sendiri.**
   Jika `watchlist` membaca `market_data`, maka intake harus mengikuti kontrak producer-facing milik `market_data`, bukan memilih raw internals atau artifact teknis lain hanya karena implementasi teknis memungkinkan.

---

## 3. Folder ownership

### `docs/market_data/`
Folder ini adalah **source of truth untuk domain market-data**.

Gunakan folder ini untuk semua aturan yang berkaitan dengan:
- ingest / import data market,
- canonical EOD bars,
- canonical indicators,
- publication / read model,
- sealing,
- replay,
- correction handling,
- evidence,
- validity / readiness upstream,
- test dan bukti domain market-data.

Jika topiknya menyangkut **bars, indicators, publication, sealing, replay, upstream data validity, atau bukti data market**, maka rujukan utama ada di folder ini.

---

### `docs/watchlist/`
Folder ini adalah **source of truth untuk domain watchlist**.

Gunakan folder ini untuk semua aturan yang berkaitan dengan:
- PLAN,
- CONFIRM,
- scoring,
- grouping,
- ranking,
- runtime output,
- persistence watchlist,
- evaluasi strategy,
- output consumer watchlist,
- kontrak perilaku watchlist.

Jika topiknya menyangkut **plan/confirm, score, semantic group, ranking, output watchlist, atau persistence watchlist**, maka rujukan utama ada di folder ini.

---

### `docs/db/`
Folder ini adalah **shared foundation** yang benar-benar global dan lintas domain.

Folder ini hanya dipakai untuk kontrak dasar yang memang tidak dimiliki penuh oleh satu domain tertentu.  
Contoh yang pantas berada di sini:
- market calendar,
- ticker master,
- foundation data yang benar-benar dipakai lintas sistem tanpa ownership domain tunggal.

Folder ini **bukan** tempat mendefinisikan ulang kontrak domain yang sudah dimiliki penuh oleh `market_data` atau `watchlist`.

Jika suatu artefak sudah menjadi milik domain tertentu, maka kontrak authoritative harus berada di domain tersebut, bukan dibuat paralel di `docs/db/`.

---

### `docs/api_architecture/`
Folder ini adalah **panduan struktur implementasi kode**.

Folder ini menjelaskan cara membangun kode agar:
- tidak mencampur layer,
- tidak mencampur query dengan rule domain,
- tidak menaruh side effect di tempat yang salah,
- tetap deterministik,
- tetap mudah diuji,
- tetap aman untuk data besar.

Gunakan folder ini untuk aturan implementasi seperti:
- boundary / transport,
- application service,
- repository,
- domain compute,
- DTO,
- logging operasional,
- determinisme,
- idempotensi,
- performa data besar.

Folder ini **tidak menggantikan dokumen domain**.  
Fungsinya adalah mengatur **cara implementasi**, bukan mendefinisikan **aturan bisnis domain**.

---

## 4. Reading path

Gunakan jalur baca berikut sesuai kebutuhan.

### Jika ingin memahami sistem market-data
Mulai dari:
- `docs/market_data/README.md`

Lalu ikuti jalur baca yang ditentukan di dalam folder `market_data`.

---

### Jika ingin memahami sistem watchlist
Mulai dari:
- `docs/watchlist/README.md`

Lalu ikuti jalur baca yang ditentukan di dalam folder `watchlist`.

---
### Jika ingin memahami integrasi `market_data -> watchlist`
Mulai dari:
- `docs/market_data/README.md`
- kontrak consumer-facing/downstream-facing yang dirujuk oleh `market_data`
- `docs/watchlist/README.md`
- `docs/watchlist/system/README.md`
- `docs/api_architecture/README.md`

Urutan ini wajib diikuti agar:
- arti input tetap dimiliki producer,
- perilaku watchlist tetap dimiliki consumer,
- dan aturan implementasi tidak diam-diam menciptakan kontrak intake paralel.

---

### Jika ingin memahami shared foundation
Mulai dari:
- `docs/db/README.md`

Gunakan hanya untuk foundation yang memang bersifat global.

---

### Jika ingin mulai mengimplementasikan kode
Jangan mulai langsung dari:
- `docs/api_architecture/`

Ikuti dulu jalur assembly berikut:
1. `docs/README.md`
2. domain owner yang relevan (`market_data` dan/atau `watchlist`)
3. baseline lintas-domain yang relevan di `docs/system_audit/`
4. entry point implementation domain yang relevan
5. baru `docs/api_architecture/` sebagai **translation phase**

`docs/api_architecture/` dipakai untuk menerjemahkan kontrak domain yang sudah terkunci ke struktur kode yang benar. Folder ini bukan titik awal memahami sistem dan bukan pengganti owner contract domain.

---

## 5. Build order that must be followed

Saat membuat sistem, urutannya harus seperti ini:

1. pahami domain yang ingin dibangun,
2. baca source of truth domain tersebut,
3. pahami kontrak input/output dan batas perilaku,
4. jika build melibatkan lintas domain producer -> consumer, kunci dulu jalur intake upstream yang sah,
5. baru terapkan aturan implementasi dari `api_architecture`,
6. baru menulis kode.

Urutan ini penting agar:
- implementasi tidak mendahului kontrak,
- struktur kode tidak membentuk aturan baru,
- domain rule tetap menjadi sumber keputusan,
- kode tetap menjadi pelaksana spesifikasi, bukan penentu spesifikasi.

---

## 6. Source-of-truth rules

Aturan berikut wajib dipatuhi:

### A. Domain owner wins
Jika suatu aturan sudah dimiliki oleh domain tertentu, maka domain itu adalah rujukan utama.

Contoh:
- aturan bars / indicators / publication milik `market_data`,
- aturan PLAN / CONFIRM / scoring / grouping milik `watchlist`,
- aturan struktur coding milik `api_architecture`.

---

### B. Shared layer must stay minimal
`docs/db/` hanya boleh berisi foundation global yang benar-benar shared.  
Jangan menjadikan shared layer sebagai tempat mendefinisikan ulang kontrak domain.

---

### C. No duplicate contracts
Tidak boleh ada dua dokumen berbeda yang sama-sama mengunci kontrak utama untuk artefak yang sama tetapi dengan istilah, field, atau struktur berbeda.

Jika kontrak authoritative sudah ada, dokumen lain hanya boleh:
- merujuk,
- merangkum secara non-authoritative,
- atau menjadi referensi pendukung.

---

### D. Reference documents are not the primary contract
Dokumen referensi, contoh, fixture, worked example, atau `_refs/` tidak boleh diperlakukan sebagai sumber aturan utama jika sudah ada dokumen normatif yang lebih tinggi.

Dokumen referensi hanya membantu:
- verifikasi,
- navigasi,
- contoh bentuk,
- pembuktian,
- atau konteks tambahan.

---

### E. Implementation must not invent policy
Kode tidak boleh:
- menambah aturan domain yang tidak ada di dokumen,
- mengganti arti field seenaknya,
- mengubah perilaku sistem tanpa dasar dokumen,
- membuat fallback diam-diam,
- atau mencampur tanggung jawab antar layer.

Jika dibutuhkan perubahan perilaku, perubahan itu harus dimulai dari dokumen domain yang relevan.

---

## 7. Coding discipline

Semua implementasi harus mengikuti prinsip berikut:

- controller / handler / transport hanya menangani boundary,
- application service hanya mengorkestrasi use case,
- repository menangani query dan persistence,
- domain compute menangani rule bisnis murni,
- DTO menjadi kontrak perpindahan data antar layer,
- logging operasional tidak boleh mencemari domain compute,
- implementasi harus deterministik untuk input yang sama,
- proses data besar harus memakai pendekatan yang aman terhadap performa.

Penjelasan detail untuk aturan ini ada di:
- `docs/api_architecture/`

---

## 8. What must not be done

Hal-hal berikut tidak boleh dilakukan saat memakai dokumen ini sebagai dasar build:

- membaca satu contoh lalu mengabaikan kontrak utama,
- langsung menulis kode tanpa membaca domain owner,
- membuat dokumen baru yang menduplikasi kontrak yang sudah ada,
- memindahkan rule bisnis ke controller, repository, atau response layer,
- menjadikan `_refs/`, fixture, atau example sebagai pengganti kontrak utama,
- mencampur istilah dari dua domain berbeda tanpa dasar eksplisit,
- menganggap semua folder di `docs/` punya bobot aturan yang sama.

---

## 9. Practical guide before starting any implementation

Sebelum membuat fitur baru atau melanjutkan implementasi, lakukan cek berikut:

1. Tentukan domain pemilik fitur:
   - market-data,
   - watchlist,
   - shared foundation,
   - atau aturan implementasi kode.

2. Baca README domain terkait.

3. Identifikasi:
   - input utama,
   - output utama,
   - invariant,
   - forbidden behavior,
   - persistence / publication boundary,
   - test / evidence yang relevan.

4. Pastikan tidak ada kontrak paralel di folder lain.

5. Baru susun implementasi mengikuti `api_architecture`.

Kalau urutan ini diabaikan, risiko drift akan naik dan hasil implementasi mudah menyimpang dari spesifikasi.

---

## 10. Intended outcome

Dokumen di folder `docs/` harus menghasilkan sistem yang:

- punya ownership domain yang jelas,
- tidak memiliki kontrak ganda,
- tidak mencampur rule bisnis dengan detail teknis implementasi,
- tetap deterministik,
- mudah diuji,
- mudah diaudit,
- dan konsisten antara spesifikasi, data, dan kode.

Jika ada keraguan saat membangun sistem, kembali ke:
1. domain owner,
2. kontrak normatif,
3. aturan implementasi dari `api_architecture`.

Jangan mulai dari asumsi, kebiasaan coding, atau pola lama yang belum tentu sesuai dengan spesifikasi sistem ini.
---

### F. Cross-domain input must not fork
Untuk hubungan lintas domain, terutama `market_data -> watchlist`, tidak boleh ada lebih dari satu makna jalur intake yang sama-sama dianggap sah.

`watchlist` wajib membaca input upstream dari kontrak producer-facing yang jelas dari `market_data`. `api_architecture` hanya dipakai setelah jalur intake itu dikunci, bukan untuk membenarkan shortcut membaca internals producer.


## System Assembly Baseline

Dokumen pada repository ini tidak boleh dibaca sebagai kumpulan folder yang berdiri sendiri-sendiri. Paket aktif harus dipahami sebagai satu jalur assembly sistem dengan urutan authority yang jelas:

1. `market_data` mengunci kontrak producer upstream.
2. `watchlist` mengunci perilaku consumer setelah input upstream yang sah tersedia.
3. `system_audit` mengunci readiness lintas-domain dan melarang shortcut interpretasi.
4. `api_architecture` menerjemahkan kontrak domain yang sudah stabil ke implementasi kode.

Artinya, implementer tidak boleh memulai build sistem penuh dari dokumen implementation atau architecture saja tanpa melewati kontrak domain yang menjadi owner meaning.

## Global Build Order

Urutan build minimum untuk sistem aktif adalah:

1. kunci root ownership dan source-of-truth di folder `docs/`;
2. kunci `market_data` sebagai producer contract dan publication-ready output;
3. kunci baseline lintas-domain pada `docs/system_audit/`;
4. kunci `watchlist` sebagai consumer behavior yang bergantung pada upstream producer-facing intake;
5. masuk ke `watchlist/system/implementation/` hanya setelah policy dan intake baseline stabil;
6. gunakan `api_architecture` sebagai translation guardrail untuk controller/service/repository/domain compute;
7. implementasi kode tidak boleh menciptakan kontrak baru yang bersaing dengan owner docs.

## Phase Transition: Domain Contract to Implementation Translation

Perpindahan dari dokumen domain ke dokumen implementasi hanya boleh dilakukan bila syarat berikut sudah terpenuhi:

- owner contract producer sudah jelas;
- owner behavior consumer sudah jelas;
- baseline input lintas-domain sudah jelas;
- system assembly baseline sudah jelas.

Setelah syarat di atas stabil, `docs/api_architecture/` menjadi wajib sebagai guardrail translation. Folder tersebut tidak boleh dipakai untuk mendefinisikan ulang business meaning dari producer contract maupun consumer policy.
