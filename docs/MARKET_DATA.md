# MARKET_DATA.md — Panduan Konseptual untuk Fitur Market Data yang Tahan Produksi
*(Multi-Source • Akurat • Audit-able • SRP • Performa • Tidak Mudah Rusak)*

Dokumen ini adalah **kompas**. Kalau kamu atau AI baca ini di chat lain, harus langsung paham:
- **apa** yang dibangun,
- **kenapa** desainnya begitu,
- **apa saja failure mode** yang biasanya bikin fitur market data “kelihatan jalan” tapi diam-diam merusak sistem,
- dan **guardrail** apa yang wajib ada supaya stabil di production.

Dokumen ini **bukan** spek coding per file. Tapi ini harus cukup tajam untuk jadi panduan implementasi yang benar dan sulit rusak.

---

## 1) Misi Fitur: “Sumber Kebenaran yang Bisa Dipertanggungjawabkan”

Market Data bukan sekadar “ambil harga”. Ini modul yang menentukan kualitas seluruh aplikasi:
- compute-eod (indikator & sinyal),
- watchlist (kandidat & scoring),
- portfolio (valuasi EOD & analisa).

Kalau Market Data salah, modul lain **pasti** ikut salah. Jadi target utamanya:

### 1.1 Akurasi yang konsisten, bukan “kadang benar”
Akurasi di sini artinya:
- candle EOD benar-benar final,
- tanggal tidak bergeser karena timezone,
- volume tidak salah satuan,
- data outlier terdeteksi sebelum menghancurkan indikator,
- sumber data tercatat jelas.

### 1.2 Multi-source itu wajib, tapi canonical harus tunggal
Multi-source tanpa canonical selection = chaos (modul A pakai sumber X, modul B pakai sumber Y → hasil beda).
Jadi prinsip:
- Simpan semua sumber sebagai **RAW (bukti mentah)**.
- Tetapkan **CANONICAL (1 versi resmi)** yang dipakai semua modul.

### 1.3 Bisa diaudit dan bisa direbuild
Wajib bisa menjawab:
- “Harga ini datang dari sumber apa?”
- “Kenapa hari itu canonical berubah?”
- “Kalau vendor baru masuk, bisa rebuild canonical tanpa refetch total?”

---

## 2) Perilaku Default yang Deterministic: Cutoff & “Run Tanpa Tanggal”

Ini penyebab kerusakan paling sering di production: **mengambil hari ini padahal belum final**.

### 2.1 Cutoff EOD (WIB) itu aturan keras
- Gunakan cutoff default: **16:30 WIB**.
- Jika import dijalankan **sebelum cutoff**:
  - end date = **kemarin** (bukan hari ini),
  - jangan ada partial candle masuk canonical.

Catatan:
- Partial candle yang masuk 1 kali saja bisa merusak MA/RSI dan membuat sinyal palsu.
- “Ah nanti malam kita run lagi” tidak cukup, karena indikator sudah terlanjur terhitung dari data salah.

### 2.2 Run tanpa parameter tanggal harus otomatis dan aman
Kalau dijalankan tanpa input:
- tentukan `effective_end_date`:
  - now >= cutoff → today
  - now < cutoff → yesterday
- tentukan `effective_start_date`:
  - gunakan **lookback window kecil** (misal 5–10 trading days) untuk “menambal” keterlambatan update vendor
  - tetap configurable

Kenapa butuh lookback?
- vendor kadang telat 1–2 hari
- weekend/holiday menyebabkan “gap pattern” yang sering bikin logic import salah
- re-run kecil jauh lebih murah daripada backfill besar.

---

## 3) Prinsip SRP yang Wajib (Kalau Dilanggar, Stabilitas Pasti Turun)

Pisahkan peran. Kalau dicampur, debug dan scaling akan hancur.

### 3.1 Provider
Tanggung jawab provider:
- fetch data dari satu sumber,
- parse response,
- keluarkan hasil mentah dalam format internal standar.

Provider **tidak boleh**:
- menentukan canonical,
- menyembunyikan error,
- melakukan “smart fixing” diam-diam (karena jadi sulit audit).

### 3.2 Normalizer
Tanggung jawab:
- menyamakan timezone, format symbol, precision, unit volume.
- output harus konsisten lintas provider.

### 3.3 Validator / Quality Gate
Tanggung jawab:
- menilai bar valid atau tidak,
- flag outlier / suspicious bar,
- menjaga agar canonical tidak tercemar.

### 3.4 Orchestrator / Import Runner
Tanggung jawab:
- menentukan date range (cutoff + lookback),
- batching + throttling,
- retry policy,
- fallback antar provider,
- mencatat telemetry run.

### 3.5 Canonical Selector
Tanggung jawab:
- memilih 1 bar yang dipakai sistem dari banyak RAW.
- tidak fetch data; hanya bekerja dengan data hasil provider + kualitasnya.

### 3.6 Audit / Telemetry
Tanggung jawab:
- membuat bukti dan jejak yang bisa dipakai investigasi.
Log “sekadar teks” tidak cukup kalau kamu mau sistem tahan produksi.

---

## 4) Konsep Data RAW vs CANONICAL (Kunci untuk “Tidak Mudah Rusak”)

### 4.1 RAW = bukti mentah, semua sumber
RAW disimpan walau:
- invalid,
- disagreement,
- ada error partial,
karena RAW itu sumber audit.

Tapi RAW harus punya metadata:
- source,
- waktu import,
- status validasi,
- error/flags.

### 4.2 CANONICAL = satu versi resmi
Canonical hanya boleh dibangun dari RAW yang:
- lolos quality gate,
- memenuhi aturan cutoff,
- memenuhi aturan prioritas sumber.

Canonical wajib menyimpan minimal:
- sumber terpilih,
- alasan pemilihan (misal priority winner, fallback used, etc),
- flags jika data suspicious (biar modul lain bisa aware).

---

## 5) Normalisasi yang Benar: Penyebab Bug yang “Licin” dan Sulit Ditemukan

Multi-source tanpa normalisasi akan menghasilkan bug “licin”: tampak jalan, tapi hasil salah.

### 5.1 Timezone shift (bug paling mematikan)
Banyak provider mengembalikan timestamp UTC. Kalau salah konversi:
- bar tanggal 2026-01-16 WIB bisa terbaca sebagai 2026-01-15, atau sebaliknya.

Dampaknya:
- compute-eod menghitung indikator dengan urutan tanggal salah,
- watchlist menilai breakout palsu,
- portfolio valuasi salah hari.

Guardrail:
- semua `trade_date` harus ditetapkan di **Asia/Jakarta** sebagai definisi tunggal.
- jika provider memberi timestamp, konversi ke WIB dan ambil tanggal WIB.

### 5.2 Unit volume (lot vs shares)
Beberapa sumber memberi volume dalam lembar, ada yang dalam lot.
Dampak:
- volume spike palsu → sinyal “strong burst” palsu.

Guardrail:
- definisikan unit volume internal (misal “shares/lembar”).
- semua provider wajib dikonversi ke unit internal.

### 5.3 Precision & rounding
Perbedaan rounding (2 decimal vs 4 decimal) bisa bikin:
- high < close karena rounding,
- open/close keluar dari range karena truncation.

Guardrail:
- tentukan precision internal (misal 4 decimal),
- normalizer melakukan rounding konsisten,
- validator punya toleransi kecil.

### 5.4 Symbol mapping
Provider punya format symbol sendiri (misal `.JK`).
Bug mapping membuat data ticker A masuk ke ticker B.

Guardrail:
- mapping symbol harus eksplisit dan testable.
- jangan “string concat” di banyak tempat; jadikan satu sumber kebenaran mapping.

---

## 6) Quality Gate: Menolak Data Jelek Sebelum Masuk Canonical

Quality gate adalah “filter sanitasi” agar 1 glitch vendor tidak merusak semua modul.

### 6.1 Aturan validasi minimal (hard rules)
- `high >= low`
- `open` dan `close` berada di dalam `[low, high]` (toleransi kecil)
- harga > 0
- volume >= 0
- tidak future date
- tidak melanggar cutoff (hari ini sebelum cutoff → reject untuk canonical)

### 6.2 Suspicious rules (soft rules, menghasilkan flag)
- gap harga ekstrem vs hari sebelumnya (misal > X%) → flag
- volume 0 di trading day → flag
- candle sama persis berhari-hari → flag (indikasi data stale)
- high==low==open==close pada banyak hari → flag

Prinsip:
- hard rules → boleh simpan RAW, tapi canonical reject.
- soft rules → canonical boleh masuk *dengan flag* (atau tahan jika threshold berat).

### 6.3 Data disagreement antar sumber
Jika banyak sumber tersedia dan beda signifikan:
- simpan semua di RAW,
- canonical pilih sesuai priority,
- tapi jika selisih melewati batas, beri flag `DISAGREE_MAJOR` agar investigasi mudah.

---

## 7) Strategi Multi-Source: Priority, Fallback, dan Anti-Rusak

### 7.1 Priority list harus eksplisit
Contoh urutan (hanya contoh, jangan dianggap mengunci):
1) sumber authoritative (misal file/resmi)
2) vendor A
3) vendor B
4) fallback (misal Yahoo)

Priority ini harus configurable.

### 7.2 Fallback bukan “asal pindah”
Fallback harus punya alasan dan tercatat.
Trigger fallback misalnya:
- HTTP error / timeout,
- rate limit,
- data kosong,
- data tidak lolos quality gate terlalu banyak.

### 7.3 Jangan lakukan fallback “per request” tanpa kontrol
Kalau tiap ticker fallback berbeda-beda tanpa telemetry, kamu akan:
- tidak tahu kualitas run,
- tidak tahu vendor mana yang bermasalah,
- sulit audit kenapa canonical campur.

Minimal harus ada ringkasan run:
- provider utama sukses berapa %
- fallback terjadi berapa kali dan kenapa

---

## 8) Market Calendar Awareness: Menghindari False Missing dan False Data

Importer harus bisa bedakan:
- “tidak ada data karena bukan trading day”
- “tidak ada data padahal trading day (missing)”

Kalau tidak:
- kamu akan mengira vendor error padahal hari libur,
- atau lebih parah: kamu memaksa import dan menyimpan garbage.

Minimal kebutuhan:
- pengetahuan trading day vs non-trading day
- jika trading day dan data tidak ada → masuk daftar “needs backfill / investigate”

---

## 9) Corporate Actions: Split Itu Bom Waktu Kalau Dibiarkan

Split/reverse split membuat harga “lompat” dan menghancurkan indikator historis jika tidak ditangani.

Kebijakan yang wajib jelas sejak awal (meski implementasi bisa bertahap):
- apakah seri EOD yang dipakai compute-eod adalah raw atau adjusted?
- apakah canonical menyimpan info “adjusted available”?
- bagaimana mendeteksi event split jika provider berbeda?

Prinsip aman untuk awal:
- simpan informasi kalau provider menyediakan adjusted/CA hints,
- quality gate memiliki rule “gap ekstrem” yang bisa di-override jika hari itu terdeteksi corporate action,
- jangan diam-diam menormalisasi split tanpa jejak (harus audit-able).

---

## 10) Observability & Operasional: “Biar Tidak Rusak Diam-Diam”

Sistem market data yang paling berbahaya adalah yang “kelihatan sukses”, tapi:
- beberapa ticker missing,
- sebagian data stale,
- canonical tercemar partial,
- dan baru ketahuan saat trading salah.

### 10.1 Sinyal kesehatan harian (minimal)
Wajib ada ringkasan:
- range efektif yang diimport
- jumlah ticker target
- sukses/fail per provider
- jumlah bar valid vs invalid
- jumlah ticker yang missing pada trading day
- daftar “critical issues” (misal provider utama down)

### 10.2 Alerting (minimal konsep)
Bukan berarti harus pakai sistem alert besar. Minimal:
- jika missing trading day di atas threshold → dianggap run bermasalah
- jika fallback melonjak → vendor utama bermasalah
- jika disagreement major melonjak → ada isu data kualitas

---

## 11) Failure Modes yang Paling Sering Terjadi + Mitigasi

Bagian ini sengaja tajam. Ini daftar masalah nyata yang sering bikin market data hancur.

### FM-1: Partial candle “hari ini” masuk canonical
**Gejala**: indikator berubah di malam hari; sinyal muncul lalu hilang besok.
**Mitigasi**:
- cutoff rule keras,
- canonical builder menolak trade_date “today” jika run sebelum cutoff,
- telemetry: selalu tampilkan `effective_end_date`.

### FM-2: Timezone shift menyebabkan tanggal geser
**Gejala**: ada “lubang” atau “double day”, indikator salah urutan.
**Mitigasi**:
- trade_date internal = WIB, wajib.
- test konversi untuk edge case (UTC close vs WIB date).

### FM-3: Volume salah unit (lot vs lembar)
**Gejala**: sinyal volume burst palsu.
**Mitigasi**:
- unit internal didefinisikan,
- provider wajib konversi,
- validator flag volume aneh.

### FM-4: Symbol mapping salah (ticker tertukar)
**Gejala**: data BBCA masuk BBRI, dsb; sulit ketahuan.
**Mitigasi**:
- mapping tunggal,
- sanity check: range harga wajar per ticker (heuristic),
- audit: source symbol disimpan di RAW.

### FM-5: Provider stale (mengulang data lama)
**Gejala**: tanggal berjalan tapi nilainya tidak berubah; missing trading day.
**Mitigasi**:
- lookback window,
- rule: “expected new bar” pada trading day,
- flag “STALE_SERIES”.

### FM-6: Outlier / glitch harga ekstrem
**Gejala**: MA/RSI rusak; watchlist tiba-tiba penuh kandidat palsu.
**Mitigasi**:
- quality gate hard + soft rules,
- disagreement check antar sumber,
- canonical reject jika outlier ekstrem dan tidak ada CA hint.

### FM-7: Rate limit / timeout bikin import setengah jalan
**Gejala**: sebagian ticker update, sebagian tidak.
**Mitigasi**:
- batching + throttling,
- retry policy yang terkontrol,
- run summary menampilkan “coverage %”.

### FM-8: Holiday / non-trading day disalahartikan missing
**Gejala**: false alarm; atau importer memaksa fetch dan simpan nonsense.
**Mitigasi**:
- calendar awareness minimal,
- bedakan missing vs expected-no-data.

### FM-9: Data disagreement antar sumber besar-besaran
**Gejala**: canonical berubah-ubah; hasil backtest tidak stabil.
**Mitigasi**:
- priority list + canonical stabil,
- disagreement flag + threshold,
- rebuild canonical bisa dilakukan dengan aturan baru.

---

## 12) Tahap Implementasi (Roadmap) yang Fokus Stabilitas

### Tahap 0 — Aturan keras & kontrak konsep
Output:
- definisi cutoff + default date behavior,
- definisi normalisasi (timezone, unit, precision),
- definisi quality gate,
- definisi RAW vs CANONICAL (konsep + metadata minimum),
- definisi telemetry minimum.

### Tahap 1 — Jalur EOD yang “benar dulu”, bukan “banyak dulu”
Output:
- satu provider yang stabil,
- RAW tersimpan,
- canonical terbentuk dengan cutoff + quality gate,
- run summary jelas.

Kriteria lulus:
- tidak ada partial day masuk,
- rerun idempotent,
- coverage tinggi dan terukur.

### Tahap 2 — Tambah provider + fallback
Output:
- provider kedua,
- priority + fallback,
- disagreement flag.

Kriteria lulus:
- provider utama down tidak bikin data kosong,
- alasan fallback terbaca jelas.

### Tahap 3 — Hardening: gap, stale, outlier, calendar
Output:
- missing trading day tracking,
- stale detection,
- outlier handling lebih matang,
- calendar awareness minimal.

### Tahap 4 — Rebuild & backfill yang aman
Output:
- rebuild canonical dari RAW,
- backfill lama bertahap,
- aturan baru bisa diterapkan tanpa chaos.

---

## 13) Definisi “Sukses” yang Tidak Bisa Ditawar

Fitur Market Data dianggap sukses jika:
1) Run tanpa tanggal selalu menghasilkan range yang benar berdasarkan cutoff.
2) Canonical tidak pernah tercemar data parsial.
3) Semua data canonical bisa ditelusuri ke RAW + sumbernya.
4) Jika provider down/rate-limited, sistem tetap jalan dengan fallback dan bisa diaudit.
5) Ada indikator kesehatan run (coverage, missing, fallback, disagreement) sehingga kerusakan tidak terjadi diam-diam.
6) Perubahan vendor/aturan tidak membuat data historis jadi tidak konsisten karena rebuild bisa dilakukan.

---

## 14) Prinsip Akhir: “Lebih Baik Tidak Update daripada Update Salah”
Kalau quality gate mendeteksi data mencurigakan parah, lebih baik:
- simpan RAW + flag,
- tahan canonical,
- laporkan di run summary,
daripada memaksa canonical masuk dan merusak seluruh indikator.

Ini kunci supaya sistem tidak “rusak halus” di production.

---
