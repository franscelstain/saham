# System Readiness Audit Baseline

## Audit Objective

Audit ini memastikan bahwa paket dokumentasi aktif cukup siap untuk dipakai membangun sistem secara terarah, konsisten, dan tidak menyimpang dari batas domain yang sudah dikunci.

Audit ini tidak menilai branding aplikasi, tidak menilai nama produk, dan tidak bergantung pada identitas aplikasi tertentu.

Audit ini menilai apakah:

- domain `market_data` sudah cukup kuat sebagai upstream producer contract;
- domain `watchlist` sudah cukup kuat sebagai downstream consumer contract;
- `api_architecture` sudah cukup kuat sebagai guardrail implementasi kode;
- hubungan lintas domain sudah cukup jelas untuk membangun sistem tanpa drift besar;
- source of truth, ownership, dan build order sudah cukup tegas untuk dipakai sebagai baseline kerja.

## What this audit means in this repository

Audit ini adalah audit kesiapan sistem berbasis dokumentasi.

Fokus utamanya adalah menilai apakah kombinasi dokumen domain dan dokumen arsitektur sudah cukup:

- jelas;
- konsisten;
- implementable;
- anti-duplikasi kontrak;
- anti-overlap ownership;
- anti-drift;
- cukup kuat untuk dijadikan pedoman build.

Audit ini bukan audit kualitas marketing document, bukan audit naming, dan bukan audit kosmetik wording semata.

## In-Scope Materials

Audit ini boleh menilai area berikut:

- `docs/README.md`
- `docs/market_data/`
- `docs/watchlist/`
- `docs/api_architecture/`

Audit ini juga boleh menilai hubungan authority, dependency, dan cross-reference di antara folder tersebut.

## Out-of-Scope Materials

Yang tidak menjadi objek audit ini:

- portfolio runtime behavior
- execution engine
- broker integration
- order lifecycle
- live trading operations
- UI styling / frontend cosmetics
- production infrastructure detail
- observability implementation detail di luar yang memang sudah menjadi kontrak minimum
- code implementation nyata, kecuali hanya untuk menyatakan bahwa bukti implementasi belum ada atau belum menjadi fokus fase ini

## Audit Position

Audit ini adalah audit tingkat sistem, bukan audit domain tunggal.

Artinya:

- audit `market_data` tetap menjadi owner audit domain market-data;
- audit `watchlist` tetap menjadi owner audit domain watchlist;
- audit ini bertugas menilai kesiapan sistem secara lintas-domain dan lintas-folder.

Audit ini tidak menggantikan audit domain. Audit ini berdiri di atasnya sebagai baseline integrasi dan readiness.

## Source-of-Truth Precedence

Urutan authority default untuk audit ini adalah:

1. owner docs pada domain masing-masing
2. `LOCKED` contracts / invariants pada domain owner
3. non-LOCKED owner contracts yang jelas memegang perilaku inti
4. schema / SQL / persistence contracts yang menurunkan owner contract
5. implementation-guardrail docs pada `docs/api_architecture/`
6. system overview / system map / root README
7. examples / fixtures / references
8. audit docs sebagai framework evaluasi, bukan owner runtime behavior

## Domain Ownership Rule

Audit ini wajib menegakkan aturan berikut:

- `docs/market_data/` adalah owner untuk contract upstream market-data;
- `docs/watchlist/` adalah owner untuk contract perilaku watchlist;
- `docs/api_architecture/` adalah owner untuk cara implementasi kode;
- `docs/db/` hanya boleh dipakai sebagai shared foundation yang benar-benar lintas-domain;
- tidak boleh ada domain mengambil alih owner domain lain secara normatif.

## Core Readiness Questions

Audit ini wajib menjawab pertanyaan berikut:

1. Apakah `market_data` cukup jelas sebagai upstream contract producer?
2. Apakah `watchlist` cukup jelas sebagai downstream consumer contract?
3. Apakah `api_architecture` cukup jelas untuk menerjemahkan kontrak domain ke implementasi kode?
4. Apakah hubungan `market_data -> watchlist` cukup rapat dan tidak ambigu?
5. Apakah build order lintas domain cukup jelas?
6. Apakah source of truth dan ownership cukup tegas?
7. Apakah masih ada gap yang bisa membuat implementasi menghasilkan kontrak paralel, drift, atau overlap ownership?

## Required Audit Dimensions

Setiap audit readiness minimal harus menilai dimensi berikut:

### 1. Domain Boundary Integrity
Menilai apakah tiap folder tetap berada pada domainnya dan tidak mengambil alih domain lain.

### 2. Source-of-Truth Clarity
Menilai apakah pembaca bisa tahu dokumen mana yang authoritative dan mana yang hanya companion / summary / example.

### 3. Cross-Domain Contract Readiness
Menilai apakah hubungan lintas domain cukup jelas untuk build nyata, terutama:
- upstream producer contract
- downstream consumer contract
- input/output boundary
- dependency yang sah

### 4. Implementation Translation Readiness
Menilai apakah `docs/api_architecture/` cukup jelas untuk menerjemahkan kontrak domain menjadi kode tanpa menciptakan policy baru.

### 5. Build Order Readiness
Menilai apakah urutan memahami dan membangun sistem sudah cukup jelas dan tidak mengundang implementasi liar.

### 6. Anti-Drift Strength
Menilai apakah struktur dokumen cukup kuat untuk mencegah:
- duplikasi kontrak;
- overlap ownership;
- reinterpretasi liar;
- ringkasan yang diam-diam mengubah meaning owner docs.

### 7. Evidence and Claim Discipline
Menilai apakah paket dokumen membuat klaim yang sesuai dengan bukti yang tersedia.

Dokumen boleh kuat di level desain tanpa bukti runtime nyata, tetapi tidak boleh mengklaim implementasi-proven bila belum ada bukti yang setara.

## PASS / PARTIAL / FAIL Definition

### PASS
Sebuah area dinilai `PASS` bila:
- owner jelas;
- kontrak cukup tegas;
- tidak butuh asumsi tambahan besar;
- tidak terjadi overlap authority;
- cukup implementable untuk fase aktifnya.

### PARTIAL
Sebuah area dinilai `PARTIAL` bila:
- arah dan struktur dasarnya benar;
- tetapi masih ada gap yang bisa menyebabkan drift, salah tafsir, atau implementasi paralel;
- atau hubungan lintas dokumen belum cukup rapat.

### FAIL
Sebuah area dinilai `FAIL` bila:
- scope kabur;
- ownership bentrok;
- ada kontrak ganda yang saling bersaing;
- ada domain mengambil alih domain lain secara normatif;
- atau materi tidak cukup layak dipakai membangun tanpa risiko salah tafsir besar.

## Claim Control Rule

Audit ini wajib menjaga disiplin berikut:

- desain yang kuat tidak sama dengan implementasi yang sudah terbukti;
- examples tidak sama dengan owner contract;
- summary tidak boleh mengalahkan dokumen owner;
- implementation guidance tidak boleh menciptakan business rule baru;
- audit tidak boleh menaikkan status kesiapan hanya karena wording terlihat rapi.

## Anti-Drift Rule

Audit ini wajib memeriksa keselarasan minimal antara:

- `docs/README.md`
- `docs/market_data/README.md`
- `docs/watchlist/README.md`
- `docs/api_architecture/README.md`
- dokumen owner inti di `market_data`
- dokumen owner inti di `watchlist`
- dokumen implementasi inti di `api_architecture`

Bila ringkasan, README, atau guide menyimpang dari owner docs, maka nilai harus turun.

## Reading Anchor

Sebelum menilai readiness sistem, pembaca wajib mengakui jalur baca minimum berikut:

1. `docs/README.md`
2. `docs/market_data/README.md`
3. `docs/watchlist/README.md`
4. `docs/api_architecture/README.md`

Setelah itu, audit harus turun ke owner docs yang benar-benar memegang kontrak.

Audit summary tidak boleh menggantikan owner docs.

## Expected Output of Every Readiness Audit

Setiap audit minimal harus menghasilkan:

1. scope statement
2. active layer statement
3. PASS / PARTIAL / FAIL per dimensi
4. daftar gap utama
5. daftar file owner yang paling menentukan
6. remediation items yang konkret
7. final verdict

## Active Audit-State Rule

Audit readiness aktif harus mengikuti model **current-state only**.

Aturannya:

- audit aktif menilai state dokumen saat ini;
- audit aktif tidak boleh bergantung pada penamaan ronde seperti `R1`, `R2`, `R3`, dst.;
- audit aktif tidak boleh bersandar pada histori chat untuk dianggap lengkap;
- tracker aktif harus mewakili gap aktif saat ini;
- setelah suatu gap ditutup, status tracker harus diperbarui agar state kembali bersih dan tunggal.

## Final Rule

Audit ini tidak boleh menyatakan sistem “siap penuh dibangun” hanya karena masing-masing domain terlihat rapi sendiri-sendiri.

Kesiapan sistem baru boleh dinilai tinggi bila:
- domain owner kuat;
- integrasi lintas domain cukup jelas;
- build order cukup tegas;
- dan implementation guidance cukup disiplin untuk mencegah drift saat build nyata dimulai.
