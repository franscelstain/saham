# SRP + Performa — Pedoman Global TradeAxis

Pedoman ini berlaku lintas modul (Market Data, compute-eod, intraday, watchlist, portfolio, dan modul lain).
Sasaran sistem: akurat, deterministik, idempotent, dan kencang—tanpa melanggar prinsip SRP.

---

## 0) Aturan Lintas Modul

### 0.1 Logika bersama (shared logic)
Logika yang digunakan oleh lebih dari satu modul wajib memiliki satu sumber kebenaran (single source of truth).

Ketentuan:
- Duplikasi implementasi dilarang.
- Penempatan di area privat milik satu modul dilarang apabila logika tersebut dipakai lintas modul.
- Implementasi dipusatkan pada satu area yang secara eksplisit diperlakukan sebagai lintas modul (shared) dan digunakan bersama oleh seluruh modul terkait.
- Penamaan dan lokasi area shared harus konsisten di seluruh proyek, dan tidak boleh digantikan oleh variasi lain seperti “common”, “utils”, atau penamaan serupa tanpa alasan yang terdokumentasi.

Kriteria selesai:
- Tidak ditemukan dua fungsi/kelas berbeda yang menghitung/menentukan hal yang sama.
- Perubahan pada shared logic hanya dilakukan pada satu lokasi implementasi.

### 0.2 Kebijakan konfigurasi lintas modul
Konfigurasi atau konvensi yang dipakai lintas modul wajib konsisten dan netral (tidak mengikat pada satu modul).

Ketentuan:
- Penamaan konfigurasi harus generik.
- Struktur/urutan konfigurasi harus konsisten.
- Tidak boleh ada dua nilai aturan yang sama tersebar di tempat berbeda (config lain, hardcode, atau env).

Kriteria selesai:
- Satu aturan memiliki satu definisi konfigurasi.
- Seluruh pemakai aturan mengambil nilai dari definisi yang sama.

---

## 1) Prinsip yang tidak dapat ditawar
- SRP dan performa adalah requirement. Pelanggaran wajib diperbaiki walau perlu refactor ulang.
- Akurasi didahulukan, kemudian performa.
- Perubahan rumus/threshold wajib disertai alasan, dampak terhadap output, dan rencana backfill.
- Output yang dipakai UI wajib dapat direproduksi dari data sumber (traceable).

Catatan:
- Perubahan struktur data mengikuti kontrak dan artefak data (schema/DTO), bukan “menyebar” sebagai perubahan ad-hoc di banyak tempat.

---

## 2) Batas Tanggung Jawab (SRP Boundary)

Bagian ini adalah acuan penempatan kode. Penempatan yang salah dianggap bug arsitektur.

### 2.1 Command / Job
Ruang lingkup:
- Parsing argumen, chunking, retry policy, logging, progress summary.

Larangan:
- Tidak boleh ada logika indikator, rule, scoring, classifier, atau keputusan bisnis.

### 2.2 Service (Orchestrator)
Ruang lingkup:
- Orchestration: fetch → compute → classify → persist.
- Pengelolaan buffer batch dan batas transaksi.
- Koordinasi antar komponen.

Larangan:
- Tidak boleh memuat query yang membutuhkan pembacaan dan pemahaman struktur tabel secara mendalam. Query yang menggabungkan banyak sumber data, menggunakan agregasi bertingkat, windowing, atau membentuk dataset turunan adalah tanggung jawab Repository.
- Tidak boleh mendefinisikan rule detail (threshold/guard/scoring) sebagai definisi keputusan.

### 2.3 Repository
Ruang lingkup:
- Seluruh akses DB (query/SQL).
- Query wajib index-friendly (filter + order deterministik + columns minimal).

Larangan:
- Tidak boleh mengandung rule bisnis atau classifier.
- Tidak boleh membuat keputusan berbasis kondisi (kecuali mapping data mentah → row DB secara mekanis).

Penegasan:
- Repository hanya bertanggung jawab menyajikan data.
- Transformasi mekanis untuk kompatibilitas data (misalnya normalisasi nilai null, pemilihan kolom fallback, atau penyesuaian format) diperbolehkan.
- Transformasi yang menghasilkan keputusan, label, status, atau rekomendasi berdasarkan kondisi adalah rule bisnis dan wajib berada di Domain Compute.

### 2.4 Domain Compute (Rolling/Classifier/Calculator)
Ruang lingkup:
- Perhitungan murni (pure logic): rolling, kalkulasi indikator, klasifikasi, scoring.

Larangan:
- Tidak boleh membaca konfigurasi langsung.
- Tidak boleh akses DB.
- Tidak boleh side-effect (logging, I/O, cache global).

Ketentuan:
- Semua threshold/parameter diteruskan melalui injection.

### 2.5 Provider (Composition Root)
Ruang lingkup:
- Satu-satunya tempat yang boleh membaca konfigurasi dan binding dependency (threshold object, classifier, wiring).

Larangan:
- Tidak boleh berisi rule/rumus bisnis yang seharusnya berada di Domain Compute.

---

## 3) Aturan Performa Wajib
Ketentuan berikut dianggap standar minimal untuk data besar.

- Pemrosesan data besar wajib streaming. Pengambilan history dengan cara memuat seluruh hasil ke memori sekaligus dilarang.
- Penulisan DB wajib batch upsert (buffer + flush berkala + flush sisa).
- N+1 dilarang: snapshot/lookup wajib bulk per chunk, bukan per entitas satuan.
- Seleksi kolom minimal; pengambilan seluruh kolom tanpa kebutuhan jelas dilarang.
- Chunking harus deterministik (urutan stabil dan dapat diulang).
- Index adalah bagian fitur:
  - akses OHLC berbasis pasangan ticker dan tanggal harus memiliki dukungan index yang sesuai
  - data indikator berbasis pasangan ticker dan tanggal harus memiliki batasan unik yang sesuai

Kriteria selesai:
- Range besar dapat diproses tanpa OOM.
- Query utama terbukti memakai index yang relevan.

---

## 4) Akurasi, Deterministik, dan Idempotensi
- Definisi indikator/label wajib konsisten antara code, DB schema, dan dataset.
- Output harus deterministik untuk input OHLC yang sama.
- Idempotent: menjalankan job dua kali pada range sama tidak boleh menggandakan data.
- Rolling calculation wajib mendefinisikan warmup window dan fallback saat data kurang.
- Aturan pembulatan ditetapkan satu kali dan dipakai konsisten. Pembulatan untuk tujuan tampilan dipisahkan dari nilai perhitungan agar perubahan format tampilan tidak mengubah hasil kalkulasi.

Kriteria selesai:
- Menjalankan ulang range yang sama menghasilkan output identik tanpa duplikasi row.

---

## 5) Time, Trade Date, dan Market Calendar
- Sumber kebenaran trading day adalah market_calendar.
- trade_date selalu format YYYY-MM-DD, timezone Asia/Jakarta.
- Penentuan trading day wajib merujuk market_calendar; logika kalender manual dilarang.
- Job intraday tidak boleh menulis ke tanggal libur; wajib skip.

Kriteria selesai:
- Tidak ada data intraday tertulis pada tanggal non-trading day.

---

## 6) Data Source dan Integrasi Eksternal
- Fetch layer wajib terpisah dari compute.
- Metadata response minimal untuk debug wajib tersedia (source, fetched_at, http_status, latency_ms) melalui log atau tabel audit.
- Retry policy wajib terbatas dan jelas; retry tanpa batas dilarang.
- Normalisasi angka wajib mengikuti schema: price konsisten terhadap tipe data, volume konsisten terhadap tipe data.

Kriteria selesai:
- Insiden provider dapat ditelusuri melalui metadata minimal.

---

## 7) Error Handling dan Logging (Global)

### 7.1 Tujuan
- Log digunakan untuk operasional dan debug.
- Log bukan database dan tidak menggantikan penyimpanan terstruktur.

### 7.2 Lokasi log per-domain
Gunakan channel per-domain, masing-masing ke file sendiri di storage/logs.

### 7.3 Batas penulisan log
- Command/Job/Scheduler: diperbolehkan (start/end, progress, summary).
- Service: diperbolehkan (milestone, counters, warning, retry exhausted).
- Repository: hanya saat exception DB/query gagal (hindari spam).
- Domain Compute: dilarang (harus pure logic).

### 7.4 Cakupan log yang wajib
- Start job: from/to/date, scope, chunk size, source.
- Progress per chunk: processed/inserted/updated/skipped/failed.
- Warning data: missing candle, partial response, fallback, outlier.
- Error unit kerja: gagal satu unit namun proses dapat lanjut (wajib error + context).
- Fatal: kondisi yang memaksa stop (schema mismatch, config invalid, source down total).

### 7.5 Context minimal
Setiap log penting wajib memuat context minimal:
- job_name
- job_run_id (jika ada)
- identitas entitas (ticker atau ticker_id jika relevan)
- tanggal/range
- source
- informasi chunk
- exception_class dan message (untuk error)

### 7.6 Level standar
- info: start/end, progress, summary
- warning: anomali namun proses lanjut
- error: unit kerja gagal namun proses masih lanjut
- critical: job harus stop

### 7.7 Job-run DB (opsional)
Jika diperlukan resume/retry terarah dan audit progress:
- simpan ringkasan run + failure list per entitas/tanggal di tabel khusus (bukan per baris OHLC).
- file log tetap wajib; DB job-run hanya tambahan.

---

## 8) Config/ENV — Single Source of Truth
- Satu aturan = satu sumber konfigurasi.
- Domain logic tidak boleh membaca konfigurasi langsung; seluruh nilai diteruskan melalui object injected dari Provider.
- Secret tidak boleh disimpan di repo; semuanya melalui env.

Kriteria selesai:
- Tidak ditemukan konfigurasi dobel/hardcode untuk aturan yang sama.

---

## 9) Kondisi siap rilis
Rilis dianggap siap ketika seluruh kondisi berikut terpenuhi:
- Struktur tanggung jawab per layer dipatuhi (Command/Service/Repository/Domain Compute/Provider).
- Data besar diproses streaming dan hasilnya dapat diproduksi ulang secara deterministik serta idempotent.
- Penulisan data menggunakan mekanisme batch yang aman terhadap run ulang.
- Logging per-domain tersedia, dengan context cukup untuk investigasi.
- Perubahan rumus/threshold memiliki catatan alasan, dampak, dan langkah backfill.
