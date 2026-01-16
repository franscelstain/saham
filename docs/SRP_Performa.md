# SRP + Performa - Aturan Global TradeAxis
Dokumen ini adalah aturan lintas modul (Market Data, compute-eod, intraday, watchlist, portfolio, dll).
Target: akurat, deterministik, idempotent, dan kencang - tanpa melanggar SRP.

---

## 0) Aturan Lintas Modul (Shared)

### 0.1 Shared logic
Jika ada logic yang sama dipakai oleh Market Data + compute-eod + watchlist (atau modul lain), maka:
- Jangan duplikasi.
- Jangan taruh di folder privat salah satu modul.
- Buat supaya 1 implementasi dipakai bersama.

### 0.2 Kebijakan config
Kalau suatu config/konvensi dipakai lintas modul:
- Naming harus generik dan tidak mengikat ke satu modul.
- Urutan/struktur harus konsisten.
- Jangan ada aturan cutoff A dan cutoff B di tempat berbeda.

---

## 1) Prinsip Non-Negotiable
- SRP + performa adalah requirement. Kalau melanggar, refactor wajib meski kerja ulang.
- Akurasi dulu, lalu cepat.
- Setiap perubahan rumus/threshold wajib punya alasan, dampak, dan rencana backfill.
- Semua output yang dipakai UI harus bisa direproduksi dari data sumber (traceable).

## 2) Batas Tanggung Jawab (SRP Boundary)

### Command / Job
- Parsing argumen, chunking, retry policy, logging, progress summary.
- Tidak ada logic indikator/rule.

### Service (Orchestrator)
- Orchestration (fetch -> compute -> classify -> persist).
- Kelola buffer batch dan batas transaksi.
- Tidak ada SQL kompleks, tidak ada rule detail di sini.

### Repository
- Semua akses DB (query/SQL).
- Query harus index-friendly (filter + order + columns minimal).
- Tidak boleh mengandung rule bisnis/classifier.

### Domain Compute (Rolling/Classifier/Calculator)
- Pure logic: tidak boleh config(), DB, atau side-effect.
- Semua threshold lewat injection.

### Provider (Composition Root)
- Satu-satunya tempat boleh baca config() dan binding object thresholds/classifier.

## 3) Aturan Performa Wajib
- Data besar wajib streaming (cursor()/iterator). Jangan get() untuk history.
- Tulis DB wajib batch upsert (buffer + flush + flush sisa).
- Job besar: DB::disableQueryLog().
- Hindari N+1: snapshot/lookup harus Many() per chunk, bukan per ticker.
- Select kolom minimal; jangan select *.
- Chunking harus deterministik (order by PK atau unique key).
- Index itu bagian fitur: minimal (ticker_id, trade_date) untuk OHLC dan unique (ticker_id, trade_date) untuk indicators.

## 4) Aturan Akurasi, Deterministik, Idempotent
- Definisi indikator/label harus konsisten antara code + DB + dataset.
- Output harus deterministik untuk input OHLC yang sama.
- Idempotent: menjalankan job dua kali pada range sama tidak boleh menggandakan data.
- Jika ada perhitungan rolling: definisikan warmup window dan fallback saat data kurang.
- Rounding harus eksplisit: tentukan kapan rounding dilakukan (persist vs present).

## 5) Time, Trade Date, dan Market Calendar
- Source of truth trading day: market_calendar.
- trade_date selalu format YYYY-MM-DD, timezone Asia/Jakarta.
- Jangan menghitung hari kerja pakai logika sendiri; selalu join market_calendar.
- Job intraday tidak boleh menulis ke tanggal libur; harus skip.

## 6) Data Source dan Integrasi Eksternal
- Fetch layer terpisah dari compute.
- Simpan metadata response minimal untuk debug (source, fetched_at, http_status, latency_ms) lewat log atau table audit.
- Retry policy harus terbatas dan jelas (misal exponential backoff). Jangan infinite retry.
- Normalisasi angka: price decimal sesuai schema, volume integer.

## 7) Error Handling dan Logging (GLOBAL)

### 7.1 Tujuan
- Log untuk operasional (lihat cepat) dan untuk debug.
- Jangan jadikan log sebagai database.

### 7.2 Lokasi log: per-domain saja
Gunakan channel per domain, masing-masing ke file sendiri di storage/logs.
Contoh:
- storage/logs/market_data.log
- storage/logs/compute_eod.log
- storage/logs/intraday.log
- storage/logs/watchlist.log
- storage/logs/portfolio.log
Tidak perlu file agregat tradeaxis.log.

### 7.3 Siapa yang boleh menulis log
- Command/Job/Scheduler: boleh (start/end, progress per chunk, summary).
- Service: boleh (milestone, counters, warning, retry exhausted).
- Repository: hanya saat exception DB atau query gagal (hindari spam).
- Domain Compute/Classifier/Calculator: dilarang (pure logic).

### 7.4 Apa yang wajib di-log
- Start job: from/to/date, ticker scope, chunk size, source.
- Progress per chunk: processed/inserted/updated/skipped/failed.
- Warning data: missing candle, partial response, fallback, outlier.
- Error per unit aman: gagal 1 ticker/1 date tapi job lanjut (log error + context).
- Fatal: hal yang harus stop (schema mismatch, config invalid, source down total).

### 7.5 Context wajib
Setiap log penting wajib punya context minimal:
- command/job_name
- job_run_id (kalau ada)
- ticker atau ticker_id (kalau relevan)
- trade_date atau from/to
- source (yahoo/idx/dll)
- chunk (offset/limit atau range)
- exception_class dan message (untuk error)

### 7.6 Level standar
- info: start/end, progress chunk, summary
- warning: anomali tapi lanjut
- error: gagal unit kerja (ticker/range) tapi masih bisa lanjut
- critical: job harus stop

### 7.7 DB job-run (opsional)
Jika butuh resume/retry terarah dan audit progress:
- Simpan ringkasan run + failure list per ticker/date di tabel khusus (bukan per row OHLC).
- File log tetap wajib; DB job-run hanya tambahan.

## 8) Config/ENV (Single Source of Truth)
- Satu aturan = satu sumber config. Jangan dobel.
- Domain logic tidak boleh baca config langsung. Semua lewat object injected dari Provider.
- Jangan simpan secret di repo; semua via env.

## 9) Checklist sebelum merge
- Tidak ada duplikasi shared logic lintas modul.
- Command: no indicator/rule logic.
- Service: no SQL kompleks, no rule detail.
- Repo: DB only, index-friendly, kolom minimal.
- Domain compute: pure, no config(), no DB, no side effect.
- Streaming untuk data besar (cursor/iterator) dan chunk deterministik.
- Batch upsert + flush sisa.
- Index/unique sesuai akses pattern.
- Logging per-domain: ada start/end + summary + error context.
- Jika rumus/threshold berubah: ada verifikasi dataset + rencana backfill.
