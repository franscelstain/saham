# Contoh Implementasi End-to-End

## Status dokumen

Untuk sistem aktif saat ini, **contoh canonical utama** pada dokumen ini adalah:

- `Canonical Example: Translating Market-Data Consumer Output into Weekly-Swing Watchlist Flow`

Contoh itulah yang harus dipakai sebagai pegangan translation blueprint lintas layer untuk build aktif.

Contoh-contoh generik di bagian bawah dokumen ini hanya pelengkap pola. Contoh generik tidak boleh mengalahkan, menggantikan, atau menafsirkan ulang flow canonical sistem aktif.

## Kapan dokumen ini dipakai

Dokumen ini dipakai setelah:

1. owner contract domain sudah dibaca;
2. baseline lintas domain sudah dibaca;
3. assembly baseline sudah dipahami;
4. implementer masuk ke translation phase pada `docs/api_architecture/`.

Dokumen ini bukan titik awal memahami sistem. Dokumen ini menjelaskan bagaimana kontrak domain aktif diterjemahkan ke layer implementasi secara konsisten.

## Canonical Example: Translating Market-Data Consumer Output into Weekly-Swing Watchlist Flow

Bagian ini adalah **rujukan utama** untuk translation blueprint sistem aktif.

### Phase 1 — Producer-facing intake read
- **Layer:** producer-facing read adapter / intake repository
- **Input:** publication-aware consumer-facing output dari `market_data`
- **Output:** upstream intake DTO / normalized result object
- **Forbidden drift:** membaca raw internals producer, langsung membentuk response API, mencampur intake read dengan artifact write

### Phase 2 — PLAN orchestration and compute
- **Layer:** application service + domain compute
- **Input:** intake DTO yang sudah sah
- **Output:** PLAN result object, lalu PLAN artifact payload melalui assembler
- **Forbidden drift:** service menghitung scoring sendiri, domain compute query upstream langsung, presenter ikut membentuk PLAN logic

### Phase 3 — RECOMMENDATION translation
- **Layer:** application service + domain compute + assembler
- **Input:** immutable PLAN-derived compute input
- **Output:** recommendation result object, lalu recommendation artifact payload
- **Forbidden drift:** recommendation membaca CONFIRM, recommendation membaca source di luar PLAN-derived inputs, service mengganti policy owner docs

### Phase 4 — CONFIRM translation
- **Layer:** binder/orchestration + domain compute + assembler
- **Input:** valid PLAN candidate binding + confirm input yang sah
- **Output:** confirm result object, lalu confirm artifact payload
- **Forbidden drift:** confirm memutasi membership/rank recommendation, transport mengambil alih confirm rule

### Phase 5 — Artifact persistence and read-back
- **Layer:** consumer artifact repository / persistence adapter
- **Input:** artifact payload yang sudah selesai diputuskan
- **Output:** persisted artifact / consumer read result
- **Forbidden drift:** repository menjalankan policy/scoring, repository membuat ulang meaning upstream producer-facing contract

### Phase 6 — Response exposure
- **Layer:** presenter / transport DTO shaping
- **Input:** read/composite result object
- **Output:** response DTO
- **Forbidden drift:** presenter menjadi rumah policy baru, mengganti meaning artifact, membangun ulang input producer-facing

## Canonical Layer Interpretation for the Active System

Agar tidak terjadi tafsir paralel, flow canonical di atas harus dibaca dengan aturan berikut:

- **producer-facing read adapter / intake repository** hanya menerjemahkan akses baca upstream yang sudah sah;
- **application service** hanya mengorkestrasi urutan flow aktif;
- **domain compute** hanya menjalankan rule internal consumer dari input yang sudah bersih;
- **assembler** hanya membentuk payload/artifact/result shape yang dibutuhkan tahap berikutnya;
- **consumer artifact repository** hanya menyimpan atau membaca artifact consumer;
- **presenter / transport DTO shaping** hanya mengekspos hasil akhir ke boundary luar.

Kalau ada implementasi aktif yang bertentangan dengan interpretasi ini, maka flow tersebut harus dianggap menyimpang dari blueprint canonical.

## Example: Assembling a Consumer Flow from Market Data to Watchlist

Bagian ini adalah ringkasan pendukung untuk membantu pembaca memahami urutan besar sistem aktif. Ia mengikuti contoh canonical di atas dan tidak menggantikan detail layer mapping pada contoh canonical.

### Step 1 — Producer contract is locked first
`market_data` lebih dulu menghasilkan output consumer-facing yang publication-aware. Consumer tidak boleh membaca raw pipeline state sebagai shortcut.

### Step 2 — Consumer intake follows producer-facing meaning
`watchlist` membaca intake hanya dari kontrak producer-facing yang sudah dikunci, lalu memvalidasi kesiapan input sebelum PLAN dibangun.

### Step 3 — Policy still belongs to the consumer domain
Setelah input upstream sah diterima, owner docs `watchlist` menentukan bagaimana PLAN, RECOMMENDATION, dan CONFIRM dibentuk. Arti perilaku ini tidak datang dari architecture docs.

### Step 4 — Repository and read adapter only translate access
Repository atau read adapter hanya mengambil data sesuai jalur intake yang sudah sah. Layer ini tidak boleh menciptakan sumber upstream alternatif.

### Step 5 — Application service only orchestrates
Application service menyusun urutan kerja, memanggil provider/input adapter yang sah, lalu meneruskan data ke domain compute. Service tidak boleh menjadi tempat policy baru.

### Step 6 — Domain compute only applies watchlist rules
Domain compute menghitung rule internal watchlist di atas input yang sudah valid. Ia tidak boleh mendefinisikan ulang meaning publication atau eligibility dari producer.

### Step 7 — Transport only exposes results
Transport layer hanya mengekspos hasil runtime dan error contract yang sudah ada. Ia tidak boleh mengubah arti input producer atau perilaku consumer.

## Contoh generik tambahan

Contoh di bawah ini hanya pelengkap pola arsitektur umum. Contoh ini **bukan** blueprint utama untuk sistem aktif.

### Contoh 1: Endpoint read-only kompleks
1. Boundary menerima query parameter.
2. Boundary memvalidasi bentuk minimum.
3. Boundary membentuk filter DTO.
4. Boundary memanggil application service.
5. Service memanggil read repository.
6. Repository menangani filter, sort, dan pagination.
7. Repository mengembalikan page result.
8. Response layer membentuk output publik.

### Contoh 2: Integrasi provider eksternal
1. Boundary menerima request.
2. Boundary membentuk request DTO.
3. Service mengambil data lokal dari repository.
4. Service memanggil domain compute bila perlu.
5. Service memanggil adapter provider.
6. Service menyimpan hasil ke repository.
7. Response layer membentuk output publik.

### Contoh 3: Webhook receiver
1. Boundary memverifikasi signature.
2. Boundary memvalidasi shape payload.
3. Boundary membentuk event DTO.
4. Service memeriksa duplicate event.
5. Service memanggil repository dan domain compute.
6. Repository menyimpan perubahan state.

### Contoh 4: Queue consumer
1. Queue boundary menerima message.
2. Boundary mengekstrak message id, attempt, dan payload.
3. Boundary membentuk job DTO.
4. Service memeriksa idempotensi.
5. Repository mengambil data.
6. Domain compute menghitung hasil.
7. Repository menulis hasil.

### Contoh 5: Batch rerun flow
1. Scheduler memulai batch dengan run id.
2. Service menentukan mode rerun.
3. Repository mengambil item berdasarkan checkpoint atau status.
4. Service memproses per chunk stabil.
5. Domain compute menentukan hasil per item.
6. Repository menulis hasil secara batch.

### Contoh 6: Endpoint write dengan file upload
1. Boundary menerima multipart.
2. Boundary memvalidasi field dan file dasar.
3. Boundary membungkus input menjadi DTO dan file adapter yang aman.
4. Service memanggil domain compute bila perlu.
5. Repository menyimpan metadata.
6. Storage adapter menyimpan file.
7. Response layer membentuk output publik.
