# SRP + Performa — Aturan Inti TradeAxis
1) Prinsip Non-Negotiable
  - SRP + performa adalah requirement. Kalau melanggar, refactor wajib meski kerja ulang.
  - Akurasi dulu, lalu cepat. Semua perubahan rumus/threshold harus bisa diverifikasi dengan dataset.

2) Batas Tanggung Jawab (SRP Boundary)
## Command
  - parsing argumen, chunking, logging.
  - tidak ada logic indikator/rule.
## Service
  - orchestration (fetch → compute → classify → persist).
  - kelola buffer batch.
  - tidak ada SQL kompleks, tidak ada rule detail di sini.
## Repository
  - semua akses DB (query/SQL).
  - query harus index-friendly (filter + order + columns minimal).
  - tidak boleh mengandung rule bisnis/classifier.
## Domain Compute (Rolling/Classifier)
  - pure logic: tidak boleh config(), DB, atau side-effect.
  - semua threshold lewat injection.
## Provider (Composition Root)
  - satu-satunya tempat boleh baca config() dan binding object thresholds/classifier.

3) Aturan Performa Wajib
  - Data besar wajib streaming (cursor()/iterator). Jangan get() untuk history.
  - Tulis DB wajib batch upsert (buffer + flush + flush sisa).
  - Job besar: DB::disableQueryLog().
  - Hindari N+1: snapshot/lookup harus Many() per chunk, bukan per ticker.
  - Index itu bagian fitur: minimal (ticker_id, trade_date) untuk OHLC dan unique (ticker_id, trade_date) untuk indicators.

4) Aturan Akurasi Wajib
  - Definisi indikator/label harus konsisten antara code + DB + dataset.
  - Output harus deterministik untuk input OHLC yang sama.
  - Rule rekomendasi tidak boleh kontradiksi (gunakan override/gate yang tegas).
  - Kalau definisi berubah: wajib backfill range terdampak.

5) Config/ENV (Single Source of Truth)
  - Satu aturan = satu sumber env/config. Tidak boleh dobel.
  - Domain logic tidak boleh baca config langsung. Semua lewat object injected dari Provider.

6) Checklist sebelum merge
  - Domain pure (no config(), no DB) ✅
  - Repo pure DB (no business rule) ✅
  - Cursor untuk data besar ✅
  - Batch upsert + flush sisa ✅
  - Threshold single source ✅
  - Dataset verifikasi kalau rumus/threshold berubah ✅