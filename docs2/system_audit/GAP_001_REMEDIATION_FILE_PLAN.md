# GAP-001 Remediation File Plan

## Purpose

Dokumen ini mencatat file konkret yang perlu disentuh untuk menutup GAP-001, yaitu gap pada kontrak intake lintas domain `market_data -> watchlist`.

Fokus dokumen ini bukan memberi saran abstrak, tetapi mengunci:
- file mana yang benar-benar perlu disentuh;
- masalah spesifik pada file tersebut;
- target state yang harus dicapai;
- tindakan perbaikan yang konkret;
- sinkronisasi lintas file yang wajib dijaga.

## Scope

Remediation plan ini hanya untuk menutup GAP-001.

Bukan untuk:
- mengubah strategy logic watchlist;
- mengubah kontrak internal market-data yang sudah matang;
- memperluas api architecture;
- membahas execution, portfolio, atau runtime nyata.

## Summary

Untuk menutup GAP-001, file yang perlu disentuh cukup pada area berikut:

1. root ownership / build-order anchor;
2. market-data upstream handoff docs;
3. watchlist intake and implementation-translation docs.

File di luar daftar ini tidak perlu disentuh lebih dulu untuk GAP-001.

---

## Concrete Files to Touch

| File | Kenapa perlu disentuh | Prioritas |
|---|---|---:|
| `docs/README.md` | root build-order belum tegas menyebut handoff `market_data -> watchlist` sebagai jalur intake resmi | High |
| `docs/market_data/README.md` | belum cukup eksplisit menulis jalur consumer-facing yang sah untuk domain downstream seperti watchlist | Critical |
| `docs/market_data/system/SYSTEM_CONTEXT_AND_DEPENDENCIES.md` | perlu menegaskan dependency direction producer -> consumer dan posisi watchlist sebagai downstream consumer | High |
| `docs/market_data/system/SYSTEM_DATA_PRODUCT_MAP.md` | perlu memisahkan data product yang consumer-facing vs internals yang tidak boleh dibaca langsung | Critical |
| `docs/market_data/system/SYSTEM_READ_ORDER.md` | perlu menambahkan jalur baca lintas domain untuk downstream consumer build path | High |
| `docs/watchlist/README.md` | belum cukup eksplisit menyebut input upstream sah harus datang dari kontrak consumer-facing producer | Critical |
| `docs/watchlist/system/README.md` | read-first watchlist belum mengunci handoff dari upstream producer sebelum masuk owner docs watchlist | High |
| `docs/watchlist/system/implementation/weekly_swing/01_WS_IMPLEMENTATION_SCOPE_AND_BOUNDARY.md` | boundary sudah melarang market-data internals secara umum, tapi belum mengunci jalur intake upstream yang sah | Critical |
| `docs/watchlist/system/implementation/weekly_swing/02_WS_MODULE_MAPPING.md` | istilah “resolved input contract yang sah” masih terlalu longgar dan harus diikat ke producer-facing contract yang spesifik | Critical |
| `docs/watchlist/system/implementation/weekly_swing/03_WS_RUNTIME_ARTIFACT_FLOW.md` | kalimat “Input EOD yang sah” masih terlalu generik dan harus dirapatkan ke publication-aware upstream input | High |

---

## Per-File Remediation Detail

### 1. `docs/README.md`
- **Masalah**
  - build order sudah benar secara umum, tetapi belum menyebut handoff lintas domain sebagai tahap eksplisit;
  - pembaca masih bisa menafsirkan bahwa setelah memahami `watchlist`, mereka boleh menentukan sendiri jalur baca upstream.
- **Target state**
  - root README harus tegas menyatakan bahwa consumer domain tidak bebas memilih source upstream;
  - jalur `market_data -> watchlist -> api_architecture` harus terlihat sebagai urutan sah untuk build path aktif.
- **Tindakan perbaikan konkret**
  - tambahkan subbagian ringkas tentang cross-domain intake rule;
  - tambahkan satu poin build order yang menyatakan bahwa consumer harus mengikat input upstream ke producer-facing contract lebih dulu sebelum menerapkan implementation guide.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `docs/market_data/README.md` dan `docs/watchlist/README.md`.

### 2. `docs/market_data/README.md`
- **Masalah**
  - domain boundary kuat, tapi belum cukup langsung menyebut bagaimana downstream consumer seperti watchlist harus membaca output domain ini;
  - implementation-critical path lebih fokus ke builder market-data sendiri, belum ke handoff consumer.
- **Target state**
  - README market-data harus tegas menyebut output consumer-facing apa yang sah dibaca downstream;
  - harus jelas bahwa downstream consumer tidak boleh membaca internals selain yang memang dipublikasikan untuk consumption.
- **Tindakan perbaikan konkret**
  - tambahkan subbagian `Downstream Consumer Intake Baseline`;
  - referensikan secara eksplisit `Downstream_Consumer_Read_Model_Contract_LOCKED.md` dan `EOD_Eligibility_Snapshot_Contract_LOCKED.md` sebagai anchor minimum untuk downstream consumer;
  - tambahkan larangan singkat bahwa consumer tidak boleh membaca raw internals di luar kontrak tersebut.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `SYSTEM_DATA_PRODUCT_MAP.md`, `SYSTEM_CONTEXT_AND_DEPENDENCIES.md`, dan dokumen watchlist yang menerima input.

### 3. `docs/market_data/system/SYSTEM_CONTEXT_AND_DEPENDENCIES.md`
- **Masalah**
  - konteks dan dependency kemungkinan menjelaskan upstream dependencies, tetapi belum dipakai untuk menegaskan arah downstream consumer.
- **Target state**
  - file ini harus menunjukkan dengan jelas bahwa watchlist adalah consumer downstream yang bergantung pada publication-ready market-data outputs, bukan pada raw internals.
- **Tindakan perbaikan konkret**
  - tambahkan subsection `Downstream Consumer Dependency Direction`;
  - tulis bahwa consumer membaca consumer-facing publication/read-model outputs;
  - tulis bahwa consumer tidak memiliki authority atas meaning producer outputs.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `SYSTEM_DATA_PRODUCT_MAP.md` dan `docs/watchlist/README.md`.

### 4. `docs/market_data/system/SYSTEM_DATA_PRODUCT_MAP.md`
- **Masalah**
  - peta data product belum tentu memisahkan secara tajam mana yang consumer-facing dan mana yang internal-only.
- **Target state**
  - file ini harus menjadi peta paling cepat untuk menjawab: “produk data mana yang boleh dibaca watchlist?”
- **Tindakan perbaikan konkret**
  - tandai setiap data product minimal dengan salah satu status: `consumer-facing`, `internal-only`, atau `implementation-support only`;
  - beri catatan eksplisit bahwa watchlist aktif hanya boleh mengikat input dari produk yang consumer-facing.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `docs/watchlist/system/implementation/weekly_swing/02_WS_MODULE_MAPPING.md` dan `03_WS_RUNTIME_ARTIFACT_FLOW.md`.

### 5. `docs/market_data/system/SYSTEM_READ_ORDER.md`
- **Masalah**
  - urutan baca internal market-data mungkin sudah jelas, tetapi belum menutup kebutuhan downstream reader yang ingin tahu jalur baca minimum untuk integrasi.
- **Target state**
  - ada subsection khusus untuk downstream integrator / downstream consumer.
- **Tindakan perbaikan konkret**
  - tambahkan jalur baca minimum untuk consumer lintas domain;
  - minimal arahkan ke README market-data, consumer read-model contract, eligibility snapshot contract, lalu ke domain consumer.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `docs/watchlist/system/README.md`.

### 6. `docs/watchlist/README.md`
- **Masalah**
  - README watchlist kuat di domain sendiri, tetapi belum cukup keras menyebut dari mana input upstream yang sah harus datang.
- **Target state**
  - README watchlist harus menulis bahwa watchlist hanya boleh menerima input upstream dari producer-facing contract market-data yang eksplisit.
- **Tindakan perbaikan konkret**
  - tambahkan subsection `Allowed Upstream Intake`;
  - sebut bahwa watchlist tidak membaca market-data internals di luar consumer-facing contracts;
  - arahkan pembaca ke file market-data owner yang relevan.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `docs/watchlist/system/README.md` dan `01_WS_IMPLEMENTATION_SCOPE_AND_BOUNDARY.md`.

### 7. `docs/watchlist/system/README.md`
- **Masalah**
  - urutan read-first langsung masuk owner docs weekly_swing, padahal intake dari upstream producer belum dikunci lebih dulu.
- **Target state**
  - pembaca watchlist system docs harus dipaksa mengakui baseline intake upstream sebelum masuk implementation translation.
- **Tindakan perbaikan konkret**
  - tambahkan bagian `Upstream Intake Anchor Before Weekly Swing`;
  - referensikan file market-data minimum yang wajib dibaca sebelum membangun PLAN input binding.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `docs/watchlist/README.md` dan `SYSTEM_READ_ORDER.md` milik market-data.

### 8. `docs/watchlist/system/implementation/weekly_swing/01_WS_IMPLEMENTATION_SCOPE_AND_BOUNDARY.md`
- **Masalah**
  - kalimat “watchlist hanya memakai data yang sudah tersedia dari domain lain atau input manual yang sah” masih terlalu longgar.
- **Target state**
  - boundary harus menulis bahwa data dari domain lain tidak otomatis sah; yang sah hanya producer-facing contract yang eksplisit.
- **Tindakan perbaikan konkret**
  - ganti wording generik dengan wording yang mengikat ke upstream producer-facing contracts;
  - tambahkan larangan eksplisit membaca market-data raw internals, intermediate run state, dan tabel teknis yang tidak dipublikasikan untuk downstream consumer.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `02_WS_MODULE_MAPPING.md` dan `03_WS_RUNTIME_ARTIFACT_FLOW.md`.

### 9. `docs/watchlist/system/implementation/weekly_swing/02_WS_MODULE_MAPPING.md`
- **Masalah**
  - istilah `resolved input contract yang sah untuk PLAN` benar arahnya, tetapi belum cukup operasional.
- **Target state**
  - module mapping harus jelas bahwa `WsPlanInputProvider` hanya boleh membaca upstream lewat jalur intake yang sudah ditetapkan producer-facing contract.
- **Tindakan perbaikan konkret**
  - ubah bagian PLAN Module agar menyebut source intake minimum dengan lebih konkret;
  - perkuat `Forbidden Coupling` agar menyebut raw table / internal publication state / intermediate pipeline artifacts sebagai jalur terlarang bila tidak consumer-facing.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `01_WS_IMPLEMENTATION_SCOPE_AND_BOUNDARY.md`, `03_WS_RUNTIME_ARTIFACT_FLOW.md`, dan `docs/api_architecture/repository.md` bila nanti perlu penguatan translation layer.

### 10. `docs/watchlist/system/implementation/weekly_swing/03_WS_RUNTIME_ARTIFACT_FLOW.md`
- **Masalah**
  - frasa `Input EOD yang sah` masih terlalu umum dan berisiko ditafsirkan sebagai tabel atau feed apa saja yang tersedia.
- **Target state**
  - runtime artifact flow harus menyebut bahwa Step 1 membaca upstream input yang consumer-facing, publication-aware, dan valid untuk trade_date yang sedang dibangun.
- **Tindakan perbaikan konkret**
  - rapatkan Step 1 agar menyebut publication-aware upstream input;
  - tambahkan satu note singkat bahwa PLAN build tidak boleh dimulai dari raw internals yang belum menjadi consumer-facing dataset.
- **Perlu sinkronisasi ke file lain**
  - ya, ke `SYSTEM_DATA_PRODUCT_MAP.md` dan `02_WS_MODULE_MAPPING.md`.

---

## Files Not Required for GAP-001 Right Now

File berikut tidak wajib disentuh pada tahap pertama penutupan GAP-001:

- `docs/api_architecture/application-service.md`
- `docs/api_architecture/domain-compute.md`
- `docs/api_architecture/transport-boundary.md`
- owner docs algorithm watchlist weekly_swing
- kontrak formula indikator market-data
- SQL schema detail market-data

Alasannya: GAP-001 saat ini masih dominan masalah **intake baseline dan read-path authority**, bukan masalah rule algoritma atau implementasi layer secara penuh.

---

## Recommended Execution Order

1. `docs/README.md`
2. `docs/market_data/README.md`
3. `docs/market_data/system/SYSTEM_CONTEXT_AND_DEPENDENCIES.md`
4. `docs/market_data/system/SYSTEM_DATA_PRODUCT_MAP.md`
5. `docs/market_data/system/SYSTEM_READ_ORDER.md`
6. `docs/watchlist/README.md`
7. `docs/watchlist/system/README.md`
8. `docs/watchlist/system/implementation/weekly_swing/01_WS_IMPLEMENTATION_SCOPE_AND_BOUNDARY.md`
9. `docs/watchlist/system/implementation/weekly_swing/02_WS_MODULE_MAPPING.md`
10. `docs/watchlist/system/implementation/weekly_swing/03_WS_RUNTIME_ARTIFACT_FLOW.md`

Urutan ini sengaja dibuat dari:
- root governance,
- producer authority,
- producer handoff,
- consumer acceptance,
- implementation translation.

## Close Condition for GAP-001

GAP-001 baru boleh dianggap tertutup bila setelah file-file di atas diperbaiki:

- watchlist memiliki jalur intake upstream yang tunggal dalam meaning;
- market-data jelas menunjukkan output mana yang consumer-facing;
- root docs dan read-order tidak memberi ruang jalur baca paralel;
- implementation translation watchlist tidak lagi memakai istilah intake yang terlalu longgar;
- pembaca baru bisa menunjuk file owner yang benar tanpa menebak.
