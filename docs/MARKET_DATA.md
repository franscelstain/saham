# MARKET_DATA.md — Panduan Konseptual untuk Fitur Market Data yang Tahan Produksi
*(Multi-Source • Akurat • Audit-able • SRP • Performa • Reuse Lintas Modul • Tidak Mudah Rusak)*

Dokumen ini adalah **kompas** dan akan jadi panduan coding. Kalau kamu/AI baca ulang di chat lain, harus langsung paham:
- **apa** yang akan dibangun,
- **kenapa** desainnya seperti itu,
- **failure mode** paling sering yang bikin market data “kelihatan jalan” tapi merusak data diam-diam,
- **guardrail** dan kebijakan operasional supaya stabil di production.

Dokumen ini sengaja tidak mengunci detail implementasi per file, tapi mendefinisikan *kontrak* dan *invariant* yang wajib dipenuhi.

---

## 1) Misi Fitur: “Sumber Kebenaran yang Bisa Dipertanggungjawabkan”

Market Data bukan sekadar “ambil harga”. Ini modul pondasi yang menentukan kualitas seluruh sistem.

Contoh modul downstream yang memakai hasil Market Data (compute-eod, watchlist, portfolio, dll) disebut untuk menjelaskan **dampak**:
- Market Data salah → downstream ikut salah.
- Ini **bukan** aturan bahwa Market Data harus berbeda total atau tidak boleh reuse logic dengan modul lain.

Target utamanya:

### 1.1 Akurasi yang konsisten, bukan “kadang benar”
Akurasi di sini artinya:
- candle EOD benar-benar final,
- tanggal tidak bergeser karena timezone,
- volume tidak salah satuan,
- outlier/glitch terdeteksi sebelum menghancurkan indikator,
- jejak sumber dan keputusan canonical jelas.

### 1.2 Multi-source wajib, tetapi “truth” tetap tunggal
Multi-source tanpa “canonical selection” = chaos (modul A pakai sumber X, modul B pakai sumber Y).
Prinsip:
- Simpan semua hasil sebagai **RAW (bukti mentah)**.
- Tetapkan **CANONICAL (1 versi resmi)** yang dipakai semua modul downstream.

### 1.3 Audit & rebuild adalah fitur inti, bukan bonus
Wajib bisa menjawab:
- “Data tanggal X untuk ticker Y asalnya dari mana?”
- “Kenapa canonical berubah?”
- “Kalau vendor baru masuk / rules berubah, bisa rebuild canonical tanpa refetch besar?”

---

## 2) Reuse & Config Policy (aturan global)

Aturan **reuse lintas modul** dan **kebijakan config lintas modul** adalah aturan global TradeAxis.

- Referensi: `SRP_Performa.md` (bagian *Shared/Public Component* + *Config Policy*).
- Dokumen ini **tidak** mendefinisikan ulang aturan tersebut; di sini hanya memberi konteks Market Data.

Contoh komponen yang *biasanya* layak diekstrak jadi shared/public (jika dipakai lintas modul):
- Cutoff & effective date resolver
- Market calendar helper (trading day awareness)
- Timezone + unit normalizer (WIB trade_date, volume unit)
- Quality gate core rules (hard/soft validation, outlier flags)
- Telemetry/health summary builder (ringkasan run yang konsisten)
- Symbol/ticker mapping resolver

Catatan boundary khusus Market Data:
- Market Data **tidak boleh** memanggil compute-eod/watchlist/portfolio.
- Downstream mengonsumsi **canonical** Market Data.

---

## 3) Perilaku Default Deterministic: Cutoff & “Run Tanpa Tanggal” (Wajib)

Kesalahan paling sering di production: **mengambil data “hari ini” padahal belum final** → besok berubah → indikator kacau.

### 3.1 Cutoff EOD (WIB) adalah aturan keras
- Cutoff default: **16:30 WIB** (bisa configurable).
- Jika run **sebelum cutoff**:
  - end date efektif = **kemarin**
  - canonical **tidak boleh** menerima bar “today”

Ini bukan preferensi. Ini guardrail wajib.

### 3.2 Run tanpa parameter tanggal harus otomatis dan aman
Jika dijalankan tanpa input:
- tentukan `effective_end_date`:
  - now >= cutoff → today
  - now < cutoff → yesterday
- tentukan `effective_start_date`:
  - gunakan **lookback window kecil** (misal 5–10 trading days) untuk menambal data yang telat update
  - configurable

Kenapa butuh lookback walau “harian”?
- vendor sering telat 1–2 hari
- holiday/weekend menciptakan gap pattern
- rerun kecil lebih murah daripada backfill besar

---

## 4) SRP Market Data (turunan dari SRP_Performa.md)

Batasan SRP global (Command/Service/Repository/Domain Compute/Provider) mengikuti `SRP_Performa.md`.
Bagian ini memetakan peran konseptual **khusus Market Data** agar implementasi tidak tercampur.


Pisahkan tanggung jawab. Minimal peran konseptual berikut harus jelas:

### 4.1 Provider
- fetch + parse dari 1 sumber
- output ke format internal standar
- **tidak** memilih canonical
- **tidak** menyembunyikan error
- **tidak** melakukan “smart fixing” diam-diam

### 4.2 Normalizer
- menyamakan timezone, symbol, precision, unit volume
- definisi `trade_date` tunggal: **WIB**

### 4.3 Validator / Quality Gate
- hard rules (reject canonical)
- soft rules (flag suspicious)
- menjaga canonical tidak tercemar

### 4.4 Orchestrator / Import Runner
- menentukan range (cutoff + lookback)
- batching/throttling
- retry policy
- fallback antar provider
- membuat telemetry ringkasan run

### 4.5 Canonical Selector
- memilih 1 bar resmi dari RAW
- bekerja dari data yang sudah dinormalisasi dan divalidasi

### 4.6 Audit / Telemetry
- bukti & jejak untuk investigasi
- bukan cuma log teks

---

## 5) RAW vs CANONICAL (Kunci supaya tidak mudah rusak)

### 5.1 RAW = bukti mentah dari semua sumber
RAW disimpan walau:
- invalid,
- disagreement,
- error,
karena RAW itu bahan audit.

RAW minimal harus membawa:
- source
- waktu import
- status validasi/flags
- error detail (jika ada)
- identitas data provider (symbol eksternal, metadata penting)

### 5.2 CANONICAL = satu versi resmi untuk seluruh downstream
Canonical hanya boleh dibangun dari RAW yang:
- lolos quality gate,
- sesuai cutoff,
- menang priority (atau fallback yang sah).

Canonical wajib menyimpan:
- selected source
- flags penting (fallback used, disagree major, outlier flagged, dsb)
- jejak alasan (cukup ringkas tapi bisa ditelusuri)

---

## 6) Normalisasi: Bug “Licin” yang paling sering merusak tanpa ketahuan

### 6.1 Timezone shift (paling mematikan)
Banyak provider memberi timestamp UTC.
Guardrail:
- `trade_date` internal = **WIB** definisi tunggal
- timestamp provider → konversi WIB → baru ambil tanggal WIB
- tes edge-case: sesi penutupan vs UTC date

### 6.2 Unit volume (lot vs shares)
Guardrail:
- definisikan unit internal (misal “lembar/shares”)
- semua provider wajib konversi ke unit internal
- tambahkan flag kalau volume abnormal

### 6.3 Precision & rounding
Guardrail:
- tetapkan precision internal
- rounding konsisten di normalizer
- validator punya toleransi kecil agar tidak false reject

### 6.4 Symbol mapping
Guardrail:
- mapping tunggal dan testable
- simpan symbol eksternal di RAW untuk audit
- sanity check heuristic untuk mendeteksi “ticker tertukar”

---

## 7) Quality Gate: Menolak data jelek sebelum masuk canonical

### 7.1 Hard rules (reject canonical)
- high >= low
- open/close berada dalam [low, high] (toleransi kecil)
- harga > 0
- volume >= 0
- bukan future date
- tidak melanggar cutoff (today sebelum cutoff → reject)

Hard rule gagal:
- RAW boleh simpan (audit)
- canonical **tidak boleh** ambil

### 7.2 Soft rules (flag)
- gap ekstrem vs hari sebelumnya (threshold)
- volume 0 pada trading day
- series stale (bar tidak update)
- candle flat berkepanjangan

Soft rules:
- canonical boleh masuk dengan flag, atau ditahan jika threshold parah

### 7.3 Disagreement antar sumber
Jika selisih besar:
- simpan semua RAW
- canonical pilih sesuai priority
- flag `DISAGREE_MAJOR` supaya investigasi cepat

---

## 8) Strategi Multi-Source: Priority, Fallback, Anti-Rusak

### 8.1 Priority harus eksplisit dan configurable
Tujuannya bukan “membatasi”, tapi membuat canonical **stabil** dan bisa diprediksi.

### 8.2 Fallback harus punya alasan dan tercatat
Trigger fallback contoh:
- HTTP error / timeout
- rate limit
- data kosong
- quality gate gagal banyak

### 8.3 Jangan fallback liar tanpa kontrol
Tanpa telemetry:
- kamu tidak tahu kualitas run
- canonical jadi campuran random
Minimal ringkasan run harus menunjukkan:
- provider utama sukses berapa %
- fallback berapa kali dan alasan dominan

---

## 9) Calendar Awareness: bedakan “no data expected” vs “missing”

Importer harus sadar trading day.
- non-trading day: tidak ada data itu normal
- trading day: missing adalah problem yang harus dicatat dan ditangani (retry/backfill)

---

## 10) Corporate Actions: split adalah bom waktu kalau diabaikan

Kebijakan minimal harus jelas (implementasi boleh bertahap):
- apakah series untuk compute-eod memakai raw atau adjusted?
- apakah canonical menyimpan info “adjustment/CA hint tersedia”?
- outlier rule harus bisa di-override jika CA terdeteksi

Prinsip:
- jangan “memperbaiki” split diam-diam tanpa jejak audit

---

## 11) Observability: supaya tidak rusak diam-diam

Health summary minimal per run:
- effective date range (cutoff result)
- coverage (% ticker punya bar untuk trading day terbaru)
- fallback rate + alasan dominan
- invalid hard rejects count
- disagreement major count
- missing trading day list
- status canonical: updated / held + alasan

---

## 12) Failure Modes paling sering + Mitigasi (tajam)

### FM-1: Partial candle “today” masuk canonical
Mitigasi:
- cutoff hard rule
- canonical builder reject “today” sebelum cutoff
- telemetry selalu tampilkan effective_end_date

### FM-2: Timezone shift (tanggal geser)
Mitigasi:
- trade_date internal = WIB
- test edge UTC/WIB
- rebuild canonical jika pernah tercemar

### FM-3: Volume salah unit
Mitigasi:
- unit internal didefinisikan
- provider wajib konversi
- flag volume abnormal

### FM-4: Symbol mapping salah (ticker tertukar)
Mitigasi:
- mapping tunggal
- simpan symbol eksternal di RAW
- sanity check range harga/continuity

### FM-5: Provider stale (data lama diulang)
Mitigasi:
- lookback window
- stale detection rule
- turunkan prioritas sementara

### FM-6: Outlier/glitch ekstrem
Mitigasi:
- hard/soft gate
- disagreement check
- CA hint override bila valid

### FM-7: Rate limit / timeout (import setengah jalan)
Mitigasi:
- throttling
- retry terkontrol
- coverage gating (kalau rendah, tahan canonical)

### FM-8: Holiday disalahartikan missing
Mitigasi:
- calendar awareness
- klasifikasi expected-no-data vs missing-trading-day

### FM-9: Disagreement massal
Mitigasi:
- priority stabil
- disagreement flag + threshold
- rebuild canonical dengan rules baru jika perlu

---

## 13) Roadmap Implementasi (fokus stabilitas)

### Tahap 0 — Sepakati aturan & komponen shared/public
Output:
- cutoff resolver (shared)
- calendar helper (shared)
- normalisasi rules (shared)
- quality gate core (shared)
- telemetry summary builder (shared)

### Tahap 1 — Jalur EOD yang benar dulu
- 1 provider
- RAW + canonical
- cutoff + quality gate + health summary

### Tahap 2 — Multi-provider + fallback + disagreement flags
### Tahap 3 — Hardening: gap/stale/outlier/calendar
### Tahap 4 — Rebuild canonical + backfill bertahap

---

## 14) Definisi “Sukses” (tidak bisa ditawar)

1) Run tanpa tanggal selalu benar sesuai cutoff.
2) Canonical tidak pernah tercemar partial day.
3) Canonical bisa ditelusuri ke RAW + sumber.
4) Fallback terjadi dengan alasan jelas dan audit-able.
5) Kerusakan tidak terjadi diam-diam (health summary jelas).
6) Reuse lintas modul terjadi lewat shared/public component, bukan duplikasi.
7) Rules shared tidak drift (tidak ada versi cutoff/validator berbeda per modul).

---

## 15) Prinsip akhir
**Lebih baik canonical ditahan daripada canonical salah.**
RAW + flag boleh. Canonical harus bersih.

---
