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

### 2.1 Konfigurasi `.env` yang dianggap “kontrak” (jangan diubah sembarangan)

Dokumen ini sengaja menyebut **nama env key** supaya perilaku sistem tidak “berubah diam-diam”.
Semua key di bawah **sudah dipakai di aplikasi** (lihat `config/trade.php`) dan sebaiknya diperlakukan sebagai kontrak.

| Key | Default | Makna |
|---|---:|---|
| `TRADE_EOD_TZ` | `Asia/Jakarta` | Timezone tunggal untuk menentukan `trade_date` internal (WIB). |
| `TRADE_EOD_CUTOFF_HOUR` / `TRADE_EOD_CUTOFF_MIN` | `16` / `30` | Cutoff EOD WIB. Menentukan `effective_end_date`. |
| `TRADE_MD_PROVIDER` | `auto` | Mode provider utama (auto / yahoo / stooq / eodhd). |
| `TRADE_MD_LOOKBACK_TRADING_DAYS` | `7` | Default range saat “run tanpa tanggal” (mundur N trading days). |
| `TRADE_MD_COVERAGE_MIN` | `95.00` | Coverage minimal canonical (dalam %). Jika di bawah ini → `CANONICAL_HELD`. |
| `TRADE_MD_TOL` | `0.002` | Toleransi perbandingan harga (misal 0.2%). Dipakai untuk disagreement check. |
| `TRADE_MD_DISAGREE_PCT` | `1.50` | Threshold disagreement major antar sumber (dalam %). |
| `TRADE_MD_GAP_EXTREME_PCT` | `30.00` | Threshold gap ekstrem (indikasi glitch/CA) untuk flag. |
| `TRADE_MD_CHUNK_ROWS` | `5000` | Chunk size saat proses publish canonical (jaga performa). |
| `TRADE_MD_VALIDATOR_ENABLED` | `true` | Aktif/nonaktif validator (quality cross-check). |
| `TRADE_MD_VALIDATOR_PROVIDER` | `eodhd` | Provider validator (misal EODHD) untuk sampling kandidat. |
| `TRADE_MD_VALIDATOR_MAX_TICKERS` | `20` | Maksimal ticker yang divalidasi per hari (hemat kuota). |
| `TRADE_EODHD_DAILY_CALL_LIMIT` | `20` | Batas panggilan API EODHD per hari (ops/kuota). Validator harus tunduk pada ini. |
| `TRADE_MD_VALIDATOR_DISAGREE_PCT` | `1.50` | Threshold mismatch validator (badges/flags). |

Catatan:
- Provider HTTP tuning (`TRADE_HTTP_*`, `TRADE_YAHOO_*`, `TRADE_EODHD_*`) adalah ops/performa; boleh diubah, tapi jangan mengubah definisi `trade_date`/cutoff.
- `TRADE_MD_COVERAGE_MIN` adalah “saklar keselamatan”: lebih baik **hold** daripada publish salah.


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
Tugas: memilih **1 bar resmi** per `(ticker_id, trade_date)` dari kandidat multi-source yang sudah dinormalisasi + divalidasi.

**Aturan deterministik (sesuai implementasi `CanonicalSelector`):**
- Input: `candidatesBySource[source] = { bar, val }`
- Ambil urutan prioritas dari `providers_priority` (contoh default: `['yahoo']`).
- Iterasi sesuai urutan prioritas, pilih kandidat pertama dengan `val.hardValid = true`.
  - Jika sumber yang menang = prioritas pertama → `reason = PRIORITY_WIN`
  - Jika sumber yang menang bukan prioritas pertama → `reason = FALLBACK_USED`
- `flags` dari validator ikut dibawa agar alasan bisa diaudit.
- Jika **tidak ada** kandidat yang `hardValid`, maka canonical untuk ticker+date itu **tidak dibuat** (mengurangi `canonical_points` dan mempengaruhi `coverage_pct`).

> Canonical selector tidak “mengakali” data. Kalau kandidat tidak valid, lebih baik kosong daripada salah.


### 4.6 Audit / Telemetry
- bukti & jejak untuk investigasi
- bukan cuma log teks

---

## 5) RAW vs CANONICAL (Kunci supaya tidak mudah rusak)

### 5.1 RAW = bukti mentah dari semua sumber

### 5.0 Kontrak Storage (tabel & status) — supaya downstream tidak salah baca

Implementasi Market Data menyimpan jejak run, RAW, dan CANONICAL ke tabel berikut:

#### A) `md_runs` — satu baris = satu run (telemetry minimal)
Kolom kunci:
- `run_id` (PK), `job` (default `import_eod`), `run_mode` (`FETCH` / `PUBLISH`), `timezone`, `cutoff`
- `effective_start_date`, `effective_end_date`
- target: `target_tickers`, `target_days`
- status: `RUNNING | SUCCESS | CANONICAL_HELD | FAILED`
- metrik ringkas: `coverage_pct`, `fallback_pct`, `hard_rejects`, `soft_flags`, `disagree_major`, `missing_trading_day`
- `notes` untuk ringkasan masalah dominan

**Kontrak penting untuk downstream (ComputeEOD/Watchlist/Portfolio):**
- Jika status terakhir untuk `effective_end_date` adalah `CANONICAL_HELD` / `FAILED`, downstream **tidak boleh** pakai tanggal itu; harus fallback ke *last good trade_date*.
**Kontrak `last_good_trade_date` (wajib konsisten lintas modul):**
- Definisi: tanggal trading terbaru yang punya run `md_runs.status = SUCCESS` untuk job `import_eod`.
- Downstream (ComputeEOD/Watchlist/Portfolio) **wajib** menggunakan query standar berikut (atau ekuivalen), bukan asumsi:

```sql
-- last good untuk hari ini (global)
SELECT effective_end_date
FROM md_runs
WHERE job = 'import_eod' AND status = 'SUCCESS'
ORDER BY effective_end_date DESC, run_id DESC
LIMIT 1;

-- last good untuk target tanggal D (fallback saat HELD/FAILED)
SELECT effective_end_date
FROM md_runs
WHERE job = 'import_eod' AND status = 'SUCCESS'
  AND effective_end_date <= :D
ORDER BY effective_end_date DESC, run_id DESC
LIMIT 1;
```

**Kontrak Coverage (`coverage_pct`) — sumber utama keputusan `CANONICAL_HELD`:**
- `target_tickers` = jumlah ticker aktif dari tabel `tickers` (`is_deleted = 0`) setelah filter opsi `--ticker` (jika ada).
- `target_days` = jumlah **trading days** dari `market_calendar` dalam range `effective_start_date..effective_end_date` (inclusive).
- `expected_points = target_tickers * target_days`
- `canonical_points = total canonical picks yang berhasil dibuat`
- `coverage_pct = (canonical_points / expected_points) * 100`
- `fallback_pct = (fallback_picks / canonical_points) * 100`

Run dianggap **HELD** jika `coverage_pct < TRADE_MD_COVERAGE_MIN`.



#### B) `md_raw_eod` — bukti mentah multi-source
Kontrak:
- unik: `(run_id, ticker_id, trade_date, source)`
- kolom kualitas: `hard_valid` + `flags` + `error_code/error_msg`
- `source_ts` disimpan untuk audit timezone/shift

RAW boleh “kotor”; itulah tujuannya. RAW adalah log bukti.

#### C) `ticker_ohlc_daily` — CANONICAL OHLC harian (satu kebenaran)
Kontrak:
- unik: `(ticker_id, trade_date)` (index `uq_ohlc_daily_ticker_date`)
- kolom: `open, high, low, close, adj_close, volume`
- `price_basis` = `close | adj_close` (basis yang dipilih saat publish canonical)
- corporate action hints: `ca_hint`, `ca_event` (jika ada indikasi split/discontinuity)
- `ca_hint` / `ca_event` adalah **guardrail** untuk mencegah indikator & watchlist “palsu” saat ada discontinuity.

**Nilai yang diproduksi implementasi (`CorporateActionHintService`):**
- `ca_event`:
  - `SPLIT`
  - `REVERSE_SPLIT`
  - `UNKNOWN` (indikasi CA lain / beda adj_close ekstrem)
- `ca_hint` (bisa gabungan dengan `|`):
  - `SPLIT_2_FOR_1`, `SPLIT_3_FOR_1`, `SPLIT_4_FOR_1`, `SPLIT_5_FOR_1`, `SPLIT_10_FOR_1`
  - `RSPLIT_1_FOR_2`, `RSPLIT_1_FOR_3`, `RSPLIT_1_FOR_4`, `RSPLIT_1_FOR_5`, `RSPLIT_1_FOR_10`
  - `CA_ADJ_DIFF` (jika `abs(adj_close-close)/close >= 15%`)

**Cara terbentuk (ringkas):**
- Bandingkan `close_today / prev_close` dengan rasio split umum (toleransi relatif default ±3%).
- Jika provider memberi `adj_close` dan beda jauh dari `close` → hint `CA_ADJ_DIFF`.

Kontrak downstream:
- Jika pada trade_date ada `ca_hint`/`ca_event` → ComputeEOD **tetap boleh menghitung**, tapi watchlist **wajib** downgrade / STOP rekomendasi agresif (lihat `compute_eod.md` guardrails).


- `source` = sumber canonical yang dipilih (mis. `yahoo`, `stooq`, `eodhd`, dll)
- `run_id` opsional untuk tracing run

Downstream membaca **hanya** `ticker_ohlc_daily` untuk OHLC harian.

> Kenapa kontrak ini perlu ditulis di dokumen?
> Karena bug paling sering bukan di perhitungan indikator, tapi di *salah baca tabel*, *tanggal*, atau *run yang belum valid*.

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

### 5.3 Kontrak Downstream (ComputeEOD / Watchlist)
- Modul downstream membaca hanya **CANONICAL** (bukan RAW).
- Output canonical yang dianggap resmi untuk OHLC harian: `ticker_ohlc_daily` (unik `(ticker_id, trade_date)`).
- `trade_date` yang dipakai downstream mengikuti **effective date** (cutoff) dan harus merupakan **trading day** (market calendar).

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

### 11.1 Run Summary minimal (wajib tercatat setelah setiap run)

Setelah setiap run, sistem **wajib** dapat menghasilkan ringkasan seperti berikut (minimal):

- `run_id`
- `effective_start_date .. effective_end_date`
- `status` (SUCCESS / CANONICAL_HELD / FAILED)
- `coverage_pct`, `fallback_pct`
- `hard_rejects`, `soft_flags`, `disagree_major`, `missing_trading_day`
- `notes` (alasan dominan)

Contoh ringkas (human readable):

```
run_id: 16
status: CANONICAL_HELD
range: 2026-01-19 .. 2026-01-19
target_tickers: 901, target_days: 1
coverage_pct: 88.01, fallback_pct: 0.00
hard_rejects: 0, soft_flags: 0, disagree_major: 0, missing_trading_day: 0
notes: Coverage below threshold: 88.01% < 95.00%
```

**Kontrak ops:**
- `CANONICAL_HELD` berarti: RAW boleh lengkap, tapi CANONICAL **tidak dipublish** untuk tanggal itu.
- Downstream wajib menahan diri: gunakan tanggal terakhir yang `SUCCESS`.


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

## 16) Phase 7 — Validator Subset untuk Kandidat (EODHD)

Tujuan: EODHD dipakai otomatis untuk ticker yang benar-benar mau dieksekusi (recommended picks), bukan manual input.

Konsep:
- Validasi hanya subset (top picks) agar sesuai limit API calls/hari.
- Hasil validasi disimpan agar UI bisa menampilkan badge tanpa refetch.

Cara pakai:
- Jalankan validasi subset (auto ambil dari watchlist top_picks):
  - `php artisan market-data:validate-eod --date=YYYY-MM-DD`
  - atau tanpa `--date` (akan pakai latest eod_date dari watchlist)
- Hasil akan upsert ke tabel `md_candidate_validations` (jika migration sudah dijalankan).
- Endpoint watchlist akan attach field `validator` untuk item di grup `top_picks` (jika data tersedia).

---

## 17) Bootstrap/Backfill Data (Wajib sebelum Watchlist dipakai serius)

---

## 18) Jadwal Operasional (minimal)

Dokumen ini tidak memaksa pakai Laravel Scheduler; yang penting urutannya konsisten.

- **Hari trading (sesudah 16:30 WIB):**
  1) `market-data:import-eod` untuk tanggal efektif (hari ini)
  2) `market-data:publish-eod`
  3) `trade:compute-eod`
  4) (opsional) `market-data:validate-eod` untuk subset top picks

- **Kalau dijalankan sebelum 16:30 WIB:** sistem harus memproses **previous trading day** (lihat `effective_end_date`).



Watchlist bergantung pada `ticker_ohlc_daily` + `ticker_indicators_daily`. Kalau history terlalu pendek, indikator banyak NULL dan sinyal jadi noise.

**Target minimal history (mengacu config default):**
- `TRADE_LOOKBACK_DAYS = 260`
- `TRADE_EOD_WARMUP_EXTRA_TRADING_DAYS = 60`
- Minimal backfill ≈ **320 trading days** (lebih aman 400–500 trading days untuk ticker likuid rendah).

**Urutan eksekusi (chronological, batch per rentang):**
1) Import (RAW + canonical picks + gating):
   - `php artisan market-data:import-eod --from=YYYY-MM-DD --to=YYYY-MM-DD --chunk=200`
2) Jika `status=SUCCESS`, publish ke canonical OHLC:
   - `php artisan market-data:publish-eod --run_id=RUN_ID`
3) Compute indikator untuk range yang sama:
   - `php artisan trade:compute-eod --from=YYYY-MM-DD --to=YYYY-MM-DD --chunk=200`

**Acceptance check (minimal):**
- `md_runs.coverage_pct >= TRADE_MD_COVERAGE_MIN`
- `ticker_ohlc_daily` terisi untuk range trading days (tidak bolong besar)
- `ticker_indicators_daily` terisi dan indikator kunci (MA/RSI/ATR/vol_sma20) tidak didominasi NULL

Jika `status=CANONICAL_HELD`, **jangan publish**. Ikuti playbook S1/S2/S3.

### Phase 6 — Rebuild Canonical (tanpa refetch)

Dipakai saat **S3 (Critical)** atau saat perlu audit ulang canonical **tanpa** mengambil data provider lagi (RAW sudah ada).
Perintah ini membuat `md_canonical_eod` baru (run baru) dari `md_raw_eod` sebagai **audit trail**, lalu kamu bisa publish ulang ke `ticker_ohlc_daily`.

**Command:**
- Range:
  - `php artisan market-data:rebuild-canonical --from=YYYY-MM-DD --to=YYYY-MM-DD`
- Single date:
  - `php artisan market-data:rebuild-canonical --date=YYYY-MM-DD`
- Filter ticker (opsional):
  - `php artisan market-data:rebuild-canonical --date=YYYY-MM-DD --ticker=BBCA`
- Pakai RAW run tertentu (opsional, default: latest SUCCESS import run yang cover end date):
  - `php artisan market-data:rebuild-canonical --source_run=RUN_ID --from=YYYY-MM-DD --to=YYYY-MM-DD`

**Sesudah rebuild:**
1) Pastikan status rebuild **SUCCESS** dan `coverage_pct` aman.
2) Publish canonical baru:
   - `php artisan market-data:publish-eod --run_id=RUN_ID_HASIL_REBUILD`
3) Rerun compute-eod untuk tanggal/range yang sama:
   - `php artisan trade:compute-eod --from=YYYY-MM-DD --to=YYYY-MM-DD`

Catatan: rebuild ini **tidak** memperbaiki masalah di RAW (mis. mapping/provider error). Kalau RAW salah, kamu harus **refetch/import** ulang lebih dulu (lihat playbook S2/S3).

