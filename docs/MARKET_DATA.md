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
- `run_id`, `status`, `range_from`, `range_to`, `effective_end_date`
- `expected_points`, `canonical_points`, `coverage_pct`, `fallback_pct`
- `hard_rejects`, `soft_flags`, `notes`
- `last_good_trade_date` (lihat §4)

Status:
- `SUCCESS`: canonical untuk `effective_end_date` valid dipakai.
- `CANONICAL_HELD`: canonical **ditahan** (biasanya coverage < threshold).
- `FAILED`: run gagal (error, invalid feed, dll).

### 3.2 md_raw_eod
- Menyimpan hasil fetch per provider (apa adanya) + metadata.

### 3.3 ticker_ohlc_daily (CANONICAL OHLC)
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

Rumus minimal (per 1 trading date):
- `expected_points = total_active_tickers`
- `canonical_points = count(ticker_ohlc_daily where trade_date = effective_end_date)`
- `coverage_pct = canonical_points / expected_points * 100`

Jika `coverage_pct < TRADE_MD_COVERAGE_MIN` ➜ status `CANONICAL_HELD`.

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


## 7) Command Operasional (minimal)
### 7.1 Import RAW
- `php artisan market-data:import-eod --from=YYYY-MM-DD --to=YYYY-MM-DD`

### 7.2 Publish CANONICAL
- `php artisan market-data:publish-eod --run=RUN_ID`

### 7.3 Validator (opsional, untuk badge/cek disagreement)
- `php artisan market-data:validate-eod --date=YYYY-MM-DD`
Catatan: dibatasi kuota harian `TRADE_EODHD_DAILY_CALL_LIMIT` (kalau dipakai).

### 7.4 Rebuild CANONICAL (insiden S3/CA)
- `php artisan market-data:rebuild-canonical --from=YYYY-MM-DD --to=YYYY-MM-DD [--ticker=CODE]`
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
