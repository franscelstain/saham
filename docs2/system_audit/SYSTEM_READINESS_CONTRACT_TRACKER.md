# System Readiness Contract Tracker

## Purpose

Dokumen ini adalah tracker aktif untuk gap kesiapan sistem berbasis dokumentasi.

Fungsinya adalah:

- mencatat area readiness yang sudah `PASS / PARTIAL / FAIL`;
- mengunci apa yang sedang dianggap sebagai gap aktif;
- menjaga agar pembahasan revisi tetap fokus;
- mencegah audit berikutnya kembali mengawang atau mengulang dari nol.

Dokumen ini bukan owner rule bisnis.  
Owner rule tetap berada di domain masing-masing.

## Tracker Scope

Tracker ini hanya melacak kesiapan lintas area berikut:

- `docs/README.md`
- `docs/market_data/`
- `docs/watchlist/`
- `docs/api_architecture/`

## Current Audit Position

- audit type: `documentation readiness`
- audit level: `system / cross-domain`
- primary focus: `market_data + watchlist + api_architecture`
- excluded focus: `portfolio`, `execution`, `broker integration`, `live runtime ops`

## Status Legend

- `PASS` = cukup jelas dan cukup rapat untuk fase aktif
- `PARTIAL` = arah benar tetapi masih ada gap signifikan
- `FAIL` = belum layak dipakai sebagai baseline build pada area tersebut
- `N/A` = tidak relevan untuk fase audit aktif

---

## Active Readiness Matrix

| Area | Status | Severity | Owner Area | Main Evidence | Main Gap |
|---|---|---|---|---|---|
| Root documentation ownership | PASS | Medium | `docs/README.md` | root README | ownership umum sudah jelas |
| Market-data upstream contract readiness | PASS | High | `docs/market_data/` | owner contracts market-data | kuat sebagai upstream producer baseline |
| Watchlist downstream contract readiness | PASS | High | `docs/watchlist/` | owner docs watchlist | kuat sebagai consumer baseline |
| API implementation guardrail readiness | PASS | High | `docs/api_architecture/` | architecture docs | cukup kuat sebagai pedoman implementasi |
| Cross-domain contract readiness (`market_data -> watchlist`) | PARTIAL | Critical | lintas domain | market-data + watchlist | kontrak intake lintas domain belum cukup terkunci sebagai satu baseline integrasi |
| System assembly readiness | PARTIAL | High | lintas domain | root + domain docs | belum ada peta assembly sistem yang cukup tegas |
| Build-order readiness | PARTIAL | High | lintas domain | root README + domain README + api architecture | urutan global ada, tapi penerjemahan lintas domain belum cukup operasional |
| Anti-drift readiness across owner docs and implementation guidance | PARTIAL | High | lintas domain | root + domain + architecture docs | masih ada ruang interpretasi ganda pada area integrasi |
| Runtime-proof claim discipline | PASS | Medium | lintas domain | audit reading of docs | belum terlihat klaim runtime yang berlebihan pada area inti |
| Readiness to build full system without major assumptions | PARTIAL | Critical | lintas domain | seluruh dokumen inti | masih butuh bridge docs lintas domain agar build tidak liar |

---

## Active Gaps

### GAP-001 — Cross-Domain Input Contract Still Too Loose
- **Status:** `PARTIAL`
- **Severity:** `Critical`

#### Problem
Hubungan `market_data -> watchlist` sudah benar secara arah, tetapi belum cukup terkunci sebagai kontrak intake lintas domain yang eksplisit dan mudah diterjemahkan ke implementasi.

#### Why this matters
Tanpa kontrak intake yang cukup tegas:
- engineer bisa membuat jalur baca upstream berbeda-beda;
- DTO intake bisa berbeda antar implementasi;
- boundary ownership bisa tetap terlihat benar di dokumen, tapi meleset saat build nyata.

#### Expected target state
Harus ada baseline yang tegas tentang:
- apa input sah yang boleh dipakai watchlist dari upstream;
- dokumen owner mana yang menjadi anchor intake;
- apa yang tidak boleh dibaca langsung oleh watchlist;
- bagaimana posisi publication-aware read model terhadap consumer watchlist.

#### Main evidence
- `docs/market_data/README.md`
- `docs/market_data/book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `docs/market_data/book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `docs/watchlist/README.md`
- dokumen owner watchlist yang menjelaskan PLAN / RECOMMENDATION / CONFIRM

#### Needed remediation
- buat atau rapatkan satu baseline integrasi lintas domain yang eksplisit;
- kunci istilah intake agar tidak muncul kontrak paralel;
- tegaskan jalur baca upstream yang sah untuk watchlist.

---

### GAP-002 — System Assembly Baseline Not Yet Explicit Enough
- **Status:** `PARTIAL`
- **Severity:** `High`

#### Problem
Dokumen domain sudah kuat masing-masing, tetapi belum ada baseline assembly sistem yang cukup jelas untuk menunjukkan bagaimana domain-domain itu dirangkai menjadi satu build path.

#### Why this matters
Tanpa assembly baseline:
- pembaca tahu owner per domain, tetapi belum tentu tahu urutan perakitan sistem;
- implementasi bisa benar per folder, tapi salah sebagai sistem utuh.

#### Expected target state
Harus ada gambaran yang jelas tentang:
- upstream producer position;
- downstream consumer position;
- posisi implementation guardrail;
- dependency direction;
- urutan baca dan urutan build yang tidak ambigu.

#### Main evidence
- `docs/README.md`
- `docs/market_data/system/*`
- `docs/watchlist/system/*`
- `docs/api_architecture/*`

#### Needed remediation
- rapatkan satu dokumen assembly / readiness map tingkat sistem;
- atau perkuat root-level ownership and build-order docs sampai cukup eksplisit.

---

### GAP-003 — Translation from Domain Contracts to Concrete Build Blueprint Is Still Partial
- **Status:** `PARTIAL`
- **Severity:** `High`

#### Problem
`docs/api_architecture/` sudah kuat sebagai guardrail generik, tetapi penerjemahan lintas domain ke blueprint implementasi yang lebih konkret belum sepenuhnya terkunci.

#### Why this matters
Tanpa jembatan yang cukup tegas:
- aturan coding bisa dipahami benar secara prinsip tetapi diterapkan berbeda-beda;
- module boundary lintas domain bisa dibangun tidak konsisten.

#### Expected target state
Harus cukup jelas:
- domain mana yang hanya owner rule;
- layer mana yang hanya orchestration;
- layer mana yang melakukan read model access;
- layer mana yang tidak boleh menciptakan policy baru.

#### Main evidence
- `docs/api_architecture/README.md`
- `docs/api_architecture/application-service.md`
- `docs/api_architecture/repository.md`
- `docs/api_architecture/domain-compute.md`
- `docs/api_architecture/transport-boundary.md`

#### Needed remediation
- buat mapping yang lebih eksplisit antara kontrak domain dan implementasi lintas layer;
- atau tambah satu dokumen bridge yang memperjelas penerapan architecture guide pada domain aktif.

---

### GAP-004 — System Ready for Build, But Not Yet Fully Locked Against Interpretive Drift
- **Status:** `PARTIAL`
- **Severity:** `Critical`

#### Problem
Secara umum dokumen sudah cukup untuk mulai membangun, tetapi belum cukup terkunci untuk menghindari drift besar bila build dilakukan oleh pembaca yang berbeda atau dalam revisi berulang.

#### Why this matters
Ini adalah gap readiness paling penting.  
Sistem bisa mulai dibangun, tetapi belum aman dari:
- asumsi tambahan;
- interpretasi berbeda;
- kontrak integrasi paralel;
- pergeseran owner secara diam-diam.

#### Expected target state
Sebelum dinilai sangat matang, sistem harus punya:
- owner docs yang kuat;
- bridge integrasi yang tegas;
- build order yang eksplisit;
- translation guidance yang cukup rapat.

#### Main evidence
- seluruh root/domain/architecture docs inti

#### Needed remediation
- tutup GAP-001, GAP-002, dan GAP-003;
- lalu audit ulang secara current-state.

---

## Areas Already Strong

### STRENGTH-001 — Root Ownership Is Clear Enough
- `docs/README.md` sudah cukup jelas memisahkan ownership:
  - `market_data`
  - `watchlist`
  - `api_architecture`
  - `db`

### STRENGTH-002 — Market-Data Is Strong as Upstream Baseline
- kontrak market-data sudah matang, terutama pada:
  - publication
  - consumer readability
  - readiness
  - correction / replay / seal
  - tests and proof discipline

### STRENGTH-003 — Watchlist Is Strong as Consumer Behavior Baseline
- boundary `PLAN / RECOMMENDATION / CONFIRM` sudah kuat;
- scope `weekly_swing` sudah terkunci;
- watchlist tidak dibiarkan melebar ke portfolio atau execution.

### STRENGTH-004 — API Architecture Is Useful as Guardrail
- separation antar layer cukup jelas;
- risiko pencampuran query, rule, transport, dan side effect sudah cukup ditekan.

---

## Current Verdict Snapshot

### Overall readiness status
`PARTIAL BUT STRONG`

### Practical meaning
Dokumen saat ini:
- **sudah layak dipakai untuk mulai build**, tetapi
- **belum layak dianggap fully locked untuk build end-to-end tanpa gap interpretasi signifikan**.

### Current practical verdict
- `market_data` = kuat
- `watchlist` = kuat
- `api_architecture` = kuat
- `system-level integration readiness` = belum rapat penuh

---

## Update Rule

Tracker ini harus diperbarui bila:

- ada gap yang ditutup;
- ada gap baru yang ditemukan;
- ada perubahan owner docs yang memengaruhi readiness lintas domain;
- ada pergeseran scope audit aktif.

Tracker ini tidak boleh diubah menjadi histori ronde audit.  
Tracker harus tetap mewakili **status aktif saat ini**.

## Final Rule

Selama masih ada gap `Critical` pada kontrak lintas domain atau readiness sistem tingkat atas, audit tidak boleh menyatakan paket dokumentasi sudah matang penuh untuk membangun sistem utuh tanpa risiko drift besar.
