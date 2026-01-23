# MARKET_DATA (TradeAxis) — Contract & Ops

Dokumen ini mengunci **pipeline Market Data**: RAW ➜ CANONICAL ➜ dipakai oleh Compute‑EOD/Watchlist/Portfolio. Fokus: **tanggal efektif (cutoff), kualitas (coverage), fallback (last_good_trade_date), dan perintah operasional**.

## 1) Prinsip
- **RAW**: simpan semua data dari provider untuk audit/debug.
- **CANONICAL**: *satu* sumber kebenaran untuk downstream. Downstream **tidak boleh** membaca RAW langsung.
- Jika kualitas tidak memenuhi syarat: **tahan CANONICAL** (status `CANONICAL_HELD`) dan downstream wajib memakai **last_good_trade_date**.

## 2) Cutoff & Effective End Date (WIB)
- Cutoff default: **16:30 WIB**.
- `effective_end_date` = tanggal trading terakhir yang *dianggap final*.
  - Jika sekarang **< cutoff** ➜ `effective_end_date = previous_trading_day(today)`.
  - Jika sekarang **≥ cutoff** dan **today trading day** ➜ `effective_end_date = today`.
  - Jika **today bukan trading day** ➜ `effective_end_date = previous_trading_day(today)`.

> Semua command (import/publish/compute/watchlist/portfolio) harus konsisten memakai `effective_end_date`.

## 3) Tabel & Kontrak Data
### 3.1 md_runs (telemetry run)
Minimal kolom yang harus ada/terisi per run:
- `run_id`, `status`, `effective_start_date`, `effective_end_date`
- `expected_points`, `canonical_points`, `coverage_pct`, `fallback_pct`
- `hard_rejects`, `soft_flags`, `notes`
- `last_good_trade_date` (lihat §4)

Status:
- `SUCCESS`: canonical untuk `effective_end_date` valid dipakai.
- `CANONICAL_HELD`: canonical **ditahan** (biasanya coverage < threshold).
- `FAILED`: run gagal (error, invalid feed, dll).

### 3.2 md_raw_eod
- Menyimpan hasil fetch per provider (apa adanya) + metadata.


### 3.3 md_canonical_eod (CANONICAL staging)
Tujuan: staging hasil seleksi canonical per run, sebelum dipublish ke `ticker_ohlc_daily`.

Kontrak:
- Unik per `(run_id, ticker_id, trade_date)`.
- Isi sudah dinormalisasi (OHLC, volume, adj_close) + metadata minimal:
  - `chosen_source` (provider yang menang)
  - `reason` (mis. `PRIORITY_WIN` / `FALLBACK_USED` / `ONLY_SOURCE`)
  - `flags` (opsional, ringkas)
  - Catatan: `price_basis` diturunkan saat publish ke `ticker_ohlc_daily` (mis. `ADJ_CLOSE` jika `adj_close` ada, else `CLOSE`).
- Downstream **tidak** memakai tabel ini untuk analisis; tabel ini untuk audit/debug & sumber publish saja.

Alur:
- `market-data:import-eod` ➜ build canonical ke `md_canonical_eod`
- `market-data:publish-eod` ➜ publish dari `md_canonical_eod` ke `ticker_ohlc_daily`


### 3.4 ticker_ohlc_daily (CANONICAL OHLC)
Kontrak:
- **unik** per `(ticker_id, trade_date)`.
- Minimal kolom downstream:
  - `trade_date`, `open`, `high`, `low`, `close`, `volume`
  - `adj_close` (bisa NULL)
  - `price_basis` (mis. `CLOSE` / `ADJ_CLOSE`)
  - `ca_hint`, `ca_event` (lihat §6)
  - `source` (provider yang menang)

Downstream **wajib** pakai tabel ini untuk OHLC harian.

## 4) last_good_trade_date (fallback wajib)
Definisi: trading date terbaru yang **aman dipakai** oleh downstream ketika run terbaru `CANONICAL_HELD/FAILED`.

Kontrak pemilihan (standar):
- Ambil `md_runs` terbaru dengan `status = 'SUCCESS'`
- Ambil `last_good_trade_date = md_runs.effective_end_date` dari run tersebut.

Query contoh:
```sql
SELECT effective_end_date
FROM md_runs
WHERE status = 'SUCCESS'
ORDER BY run_id DESC
LIMIT 1;
```

Aturan downstream:
- Jika run terbaru `SUCCESS` ➜ pakai `effective_end_date` run terbaru.
- Jika `CANONICAL_HELD/FAILED` ➜ pakai `last_good_trade_date` di atas.

## 5) Coverage Gate (mengunci CANONICAL_HELD)
Gunakan universe yang konsisten:
- `total_active_tickers = count(tickers where is_deleted=0)` (atau policy internal kamu yang setara).

Rumus minimal (per run):
- `expected_points = target_tickers * target_days`
- `canonical_points = count(md_canonical_eod where run_id = run_id)`
- `coverage_pct = canonical_points / expected_points * 100`

Jika `coverage_pct < TRADE_MD_COVERAGE_MIN` ➜ status `CANONICAL_HELD`.

Selain gate `coverage_pct`, implementasi juga bisa menahan canonical (`CANONICAL_HELD`) untuk alasan kualitas berikut (semuanya akan tercatat di `md_runs.notes` sebagai `held_reason=...`):
- `disagree_major`: mismatch antar provider terlalu banyak. Gate: `disagree_major_ratio >= hold_disagree_ratio_min` ATAU `disagree_major >= hold_disagree_count_min`.
- `missing_trading_day`: ada tanggal trading (dari `market_calendar`) yang tidak punya data canonical yang cukup.
- `low_coverage_days`: ada >= `hold_low_coverage_days_min` hari dalam range yang coverage hariannya < `min_day_coverage_ratio`.
- `soft_quality`: aturan soft-quality (outlier/gap) memutuskan hold.

Konfigurasi (config `trade.market_data.*` / env):

**Gating & range**
- `TRADE_MD_COVERAGE_MIN` (default 95): minimal coverage run. Jika `coverage_pct < min` ➜ `CANONICAL_HELD`.
- `TRADE_MD_LOOKBACK_TRADING_DAYS` (default 7): dipakai jika `--from` tidak diisi. `fromEff = lookbackStartDate(toEff, lookback)`.

**Additional HOLD gates (selain coverage)**
- `TRADE_MD_HOLD_DISAGREE_RATIO_MIN` (default 0.01)
- `TRADE_MD_HOLD_DISAGREE_COUNT_MIN` (default 20)
- `TRADE_MD_MIN_DAY_COVERAGE_RATIO` (default 0.60)
- `TRADE_MD_MIN_POINTS_PER_DAY` (default 5)
- `TRADE_MD_HOLD_LOW_COVERAGE_DAYS_MIN` (default 2)

**Quality rules (dipakai untuk reject/outlier & disagree scoring)**
- `TRADE_MD_TOL` (default 0.0001): toleransi “price in range” (guard basic sanity).
- `TRADE_MD_DISAGREE_PCT` (default 2.0): threshold mismatch antar provider yang dihitung sebagai `disagree_major`.
- `TRADE_MD_GAP_EXTREME_PCT` (default 20.0): gap ekstrem yang memicu outlier/soft-quality (dan bisa memicu hold via `soft_quality`).

**Validator policy (hanya untuk Phase 7 validate-eod; tidak mempengaruhi import coverage)**
- `TRADE_MD_VALIDATOR_MAX_TICKERS` (default 20): batas ticker yang boleh divalidasi per run.
- `TRADE_MD_VALIDATOR_DISAGREE_PCT` (default 1.5): threshold disagree untuk badge/peringatan (validator).

**Provider config (ops)**
- Yahoo (import): `TRADE_YAHOO_BASE_URL`, `TRADE_YAHOO_SUFFIX`, `TRADE_YAHOO_TIMEOUT`, `TRADE_YAHOO_RETRY`, `TRADE_YAHOO_RETRY_SLEEP_MS`, `TRADE_YAHOO_UA`.
- EODHD (validator): `TRADE_EODHD_BASE_URL`, `TRADE_EODHD_SUFFIX`, `TRADE_EODHD_API_TOKEN`, `TRADE_EODHD_TIMEOUT`, `TRADE_EODHD_RETRY`, `TRADE_EODHD_RETRY_SLEEP_MS`, `TRADE_EODHD_DAILY_CALL_LIMIT`.

> Cutoff time masih dikunci di dokumen ini (16:30 WIB). Override teknis bila dibutuhkan: `TRADE_EOD_CUTOFF_HOUR`, `TRADE_EOD_CUTOFF_MIN`, `TRADE_EOD_TZ`.



Catatan publish:
- Setelah `market-data:publish-eod`, sistem akan menulis `published_ohlc_rows=...` pada `md_runs.notes`.
- Jika `canonical_points` tinggi tapi `ticker_ohlc_daily` kosong/kurang, investigasi publish (`published_ohlc_rows`, error DB, unique constraint).


## 6) Corporate Action Hint/Event (CA guard)
Tujuan: **mencegah indikator palsu** (split/reverse split/adjustment abnormal).
- `ca_hint`: indikasi CA/adjustment anomaly (heuristic).
- `ca_event`: CA terkonfirmasi (atau sudah diputuskan operator).

Nilai disarankan (ringkas):
- `SPLIT`, `RSPLIT`, `CA_ADJ_DIFF` (atau enum internal setara).

Aturan downstream:
- Jika `ca_hint` atau `ca_event` terisi pada `effective_end_date` ➜ watchlist **stop rekomendasi agresif** dan operator pertimbangkan `rebuild-canonical` + rerun compute.

## 6.1) Canonical Selector (deterministik)
Tugas: memilih **1 bar resmi** per `(ticker_id, trade_date)` dari kandidat multi‑source yang sudah dinormalisasi + divalidasi.

Aturan deterministik (wajib):
- Ambil urutan prioritas dari `providers_priority` (contoh default: `['yahoo']`).
- Iterasi sesuai urutan prioritas, pilih kandidat pertama yang **hard valid**.
  - Jika sumber yang menang = prioritas pertama ➜ `reason = PRIORITY_WIN`
  - Jika sumber yang menang bukan prioritas pertama ➜ `reason = FALLBACK_USED`
- Jika **tidak ada** kandidat yang hard valid ➜ canonical **tidak dibuat** untuk ticker+date itu (mengurangi `canonical_points` dan mempengaruhi `coverage_pct`).

> Selector tidak “mengakali” data. Lebih baik kosong daripada salah.

## 6.2) Normalisasi & Quality Guard (wajib, ringkas)
Ini bukan “teori”; ini sumber error paling sering dan harus dijaga konsisten:

- **Timezone shift**: `trade_date` harus WIB. Jika provider memberi timestamp, normalisasi dulu; jangan sampai candle “geser tanggal”.
- **Unit volume**: volume harus satuan **shares/lembar** (bukan lot). Jika ada sumber lot, konversi sebelum scoring/selector.
- **Symbol mapping**: mapping ticker per provider harus deterministik. Jika mapping tidak valid ➜ kandidat di-reject (hard reject).
- **Stale/outlier**: jika data provider stale (tanggal lama) atau outlier ekstrem ➜ hard reject / set `ca_hint` sesuai heuristic.
- **Holiday vs missing**: bedakan “no data expected” (non‑trading day) vs “missing data” (trading day tapi kosong).


## 7) Command Operasional (minimal + convenience)
### 7.1 Import RAW ➜ build CANONICAL staging
Minimal (range):
- `php artisan market-data:import-eod --from=YYYY-MM-DD --to=YYYY-MM-DD`

Convenience flags (ops):
- `--date=YYYY-MM-DD` ➜ shortcut untuk single date (setara `--from=DATE --to=DATE`). **Jika `--date` diisi, `--from/--to` diabaikan.**
- `--ticker=BBCA` ➜ batasi ke 1 ticker (buat debugging cepat).
- `--chunk=200` ➜ ukuran chunk ticker saat fetch (tuning performa / rate‑limit; default 200).

Output penting yang harus dibaca:
- `status`, `effective_start..effective_end`, `expected_points`, `canonical_points`, `coverage_pct`, `hard_rejects`, `notes`.

### 7.2 Publish CANONICAL staging ➜ ticker_ohlc_daily
Minimal:
- `php artisan market-data:publish-eod --run=RUN_ID`

Convenience flags (ops):
- `--batch=2000` ➜ ukuran batch publish dari `md_canonical_eod` (tuning performa; default 2000).

Catatan:
- Publish menulis `published_ohlc_rows=...` ke `md_runs.notes`.
- `canonical_points` itu *jumlah staging* (`md_canonical_eod`). Kalau `published_ohlc_rows` jauh lebih kecil, berarti publish bermasalah (DB error / constraint / filter).

### 7.3 Validator (opsional, subset; untuk badge/cek disagreement)
Minimal:
- `php artisan market-data:validate-eod --date=YYYY-MM-DD`

Optional flags (ops helper):
- `--tickers=BBCA,BBRI` ➜ batasi ticker yang divalidasi.
- `--max=20` ➜ override jumlah maksimal (tetap dibatasi config/provider).
- `--run_id=RUN_ID` ➜ override canonical run yang diverifikasi (default: latest SUCCESS yang cover date).
- `--save=1` ➜ simpan hasil ke `md_candidate_validations` **jika tabel ada** (default 1). Pakai `--save=0` kalau hanya ingin output CLI.

Convenience behaviour (tanpa input manual):
- Jika `--tickers` **tidak** diberikan ➜ sistem otomatis ambil ticker dari **watchlist `top_picks`** (`WatchlistService->preopenRaw()`).
- Jika `--date` kosong dan auto‑tickers dipakai ➜ `--date` akan ikut default ke `watchlist.eod_date`.

Catatan: dibatasi kuota harian `TRADE_EODHD_DAILY_CALL_LIMIT` (kalau validator provider dipakai).

### 7.4 Rebuild CANONICAL (insiden S3/CA; tanpa refetch)
Minimal (range):
- `php artisan market-data:rebuild-canonical --from=YYYY-MM-DD --to=YYYY-MM-DD [--ticker=CODE]`

Convenience flags (ops):
- `--date=YYYY-MM-DD` ➜ shortcut single date.
- `--source_run=RUN_ID` ➜ pakai RAW run_id tertentu sebagai sumber rebuild (default: latest SUCCESS import run yang menutupi end date).

Setelah rebuild: **publish** lalu **compute-eod** untuk range terdampak.

## 8) Bootstrap/Backfill (agar indikator stabil)
Target minimal (praktis):
- Untuk RSI/ATR/vol_sma20/support‑res: **≥ 60 trading days**.
- Jika memakai MA200: **≥ 200 trading days** (lebih aman 260+).

Urutan (per batch range):
1) import-eod (RAW)
2) publish-eod (CANONICAL)
3) compute-eod (lihat compute_eod.md)

Acceptance check cepat:
- coverage hari terakhir ≥ threshold
- indikator tidak dominan NULL (kecuali ticker baru/illiquid)
- tidak ada spike `hard_rejects` / `CANONICAL_HELD` beruntun
