# COMMANDS.md — Urutan Command TradeAxis

Dokumen ini merangkum **seluruh Artisan command** yang tersedia di TradeAxis3.3 dan **urutan eksekusi yang disarankan** untuk operasi harian.

> Catatan: ini fokus ke *command yang benar-benar dipakai* untuk pipeline Market Data → Compute EOD → Score Card → Portfolio. Command demo `inspire` tetap dicantumkan terakhir.

## Urutan eksekusi harian yang disarankan

### A. Pipeline Market Data (EOD)

1. `market-data:import-eod` — ambil EOD, buat RAW + CANONICAL (dengan gating)

2. `market-data:validate-eod` — (opsional) validasi subset ticker terhadap provider validator

3. `market-data:rebuild-canonical` — (opsional) rebuild canonical dari RAW tanpa refetch (audit trail run baru)

4. `market-data:publish-eod` — publish canonical SUCCESS ke `ticker_ohlc_daily`


### B. Compute EOD (indikator + signal)

5. `trade:compute-eod` — hitung indikator EOD + decision/volume + signal age


### C. Watchlist Score Card (live check + metrics)

6. `watchlist:scorecard:check-live` — evaluasi eksekusi *real-time* berbasis snapshot bid/ask/last (spread/gap/chase + entry/avoid windows) dan menghasilkan default recommendation.

7. `watchlist:scorecard:compute` — hitung metrik scorecard (feasible_rate + fill_rate) dari hasil check-live + OHLC harian pada `exec_date`, lalu persist ke tabel scorecard.


### D. Portfolio lifecycle

8. `portfolio:expire-plans` — expire plan PLANNED yang sudah lewat entry expiry

9. `portfolio:ingest-trade` — input 1 transaksi (BUY/SELL) untuk membentuk lots FIFO + posisi

10. `portfolio:value-eod` — valuasi posisi pakai canonical close (setelah publish)

11. `portfolio:cancel-plan` — cancel plan secara manual (ad hoc)


---

## Detail setiap command

### `market-data:import-eod`
**Tujuan:** Import Market Data EOD into md_raw_eod and md_canonical_eod (with gating)  
**Lokasi implementasi:** `app/Console/Commands/MarketDataImportEod.php` (`MarketDataImportEod`)

**Argumen/Opsi:**
- `--date= : Single trade date (YYYY-MM-DD)`
- `--from= : Start date (YYYY-MM-DD)`
- `--to= : End date (YYYY-MM-DD)`
- `--ticker= : Optional single ticker code (ex: BBCA)`
- `--chunk=200 : Ticker chunk size`
- **Kapan dipakai:** awal pipeline harian (setelah bursa tutup atau saat kamu mau update EOD).
- **Output utama:** mengisi/menambah data EOD pada tabel RAW dan CANONICAL (gating coverage).
- **Contoh:**
```bash
php artisan market-data:import-eod --date=2026-01-19
php artisan market-data:import-eod --from=2026-01-01 --to=2026-01-19
php artisan market-data:import-eod --date=2026-01-19 --ticker=BBCA
```
- **Catatan:** kalau hasil import status *HELD / coverage di bawah threshold*, kamu **jangan publish**; lanjut ke rebuild canonical (atau perbaiki sumber data).

### `market-data:validate-eod`
**Tujuan:** Validate canonical EOD against validator provider (EODHD) for subset tickers  
**Lokasi implementasi:** `app/Console/Commands/MarketDataValidateEod.php` (`MarketDataValidateEod`)

**Argumen/Opsi:**
- `--date= : Trade date (YYYY-MM-DD)`
- `--tickers= : Comma-separated ticker codes (ex: BBCA,BBRI)`
- `--max= : Max tickers to validate (override cap, still limited by config/provider)`
- `--run_id= : Optional canonical run_id override`
- `--save=1 : Persist results into md_candidate_validations if table exists`
- **Kapan dipakai:** opsional, untuk sampling kualitas canonical (mis. 20–100 ticker) sebelum publish.
- **Output utama:** hasil validasi (dan jika `--save=1` + tabel ada → persist ke `md_candidate_validations`).
- **Contoh:**
```bash
php artisan market-data:validate-eod --date=2026-01-19 --tickers=BBCA,BBRI,TLKM --save=1
php artisan market-data:validate-eod --date=2026-01-19 --max=50
```
- **Catatan:** command ini bukan pengganti gating import; ini layer verifikasi tambahan.

### `market-data:rebuild-canonical`
**Tujuan:** Phase 6: Rebuild md_canonical_eod from md_raw_eod without refetch (new run_id audit trail)  
**Lokasi implementasi:** `app/Console/Commands/MarketDataRebuildCanonical.php` (`MarketDataRebuildCanonical`)

**Argumen/Opsi:**
- `--source_run= : RAW run_id sumber (default: latest SUCCESS import run covering end date)`
- `--date= : Single trade date (YYYY-MM-DD)`
- `--from= : Start date (YYYY-MM-DD)`
- `--to= : End date (YYYY-MM-DD)`
- `--ticker= : Optional single ticker code (ex: BBCA)`
- **Kapan dipakai:** saat canonical *HELD* / coverage kurang, tapi RAW sudah ada dan kamu ingin rebuild tanpa refetch.
- **Output utama:** membuat run canonical baru dari RAW (audit trail run_id baru).
- **Contoh:**
```bash
php artisan market-data:rebuild-canonical --date=2026-01-19
php artisan market-data:rebuild-canonical --from=2026-01-01 --to=2026-01-19
php artisan market-data:rebuild-canonical --date=2026-01-19 --ticker=BBCA
```
- **Catatan:** setelah rebuild, pastikan run-nya SUCCESS sebelum publish.

### `market-data:publish-eod`
**Tujuan:** Publish md_canonical_eod (SUCCESS run) into ticker_ohlc_daily  
**Lokasi implementasi:** `app/Console/Commands/MarketDataPublishEod.php` (`MarketDataPublishEod`)

**Argumen/Opsi:**
- `--run= : Canonical run_id to publish (required)`
- `--batch=2000 : Batch size for chunking canonical rows`
- **Kapan dipakai:** setelah kamu punya canonical run dengan status **SUCCESS**.
- **Input wajib:** `--run=<run_id>`.
- **Output utama:** copy canonical EOD ke tabel konsumsi utama `ticker_ohlc_daily` (untuk compute-eod & modul lain).
- **Contoh:**
```bash
php artisan market-data:publish-eod --run=16
php artisan market-data:publish-eod --run=16 --batch=3000
```
- **Catatan:** publish adalah tahap yang “mengubah data konsumsi”; jadi jangan lakukan kalau canonical masih held/meragukan.

### `trade:compute-eod`
**Tujuan:** Compute indikator EOD (holiday-aware) + decision/volume + signal age  
**Lokasi implementasi:** `app/Console/Commands/ComputeEod.php` (`ComputeEod`)

**Argumen/Opsi:**
- `--date= : Single trade date (YYYY-MM-DD)`
- `--from= : Start date (YYYY-MM-DD)`
- `--to= : End date (YYYY-MM-DD)`
- `--ticker= : Optional single ticker code (ex: BBCA)`
- `--chunk=200 : Ticker chunk size`
- **Kapan dipakai:** setelah EOD published (`ticker_ohlc_daily` sudah terisi untuk tanggal tsb).
- **Output utama:** tabel indikator/signal EOD (mis. `ticker_indicators_daily` dan turunan lain sesuai modul).
- **Contoh:**
```bash
php artisan trade:compute-eod --date=2026-01-19
php artisan trade:compute-eod --from=2026-01-01 --to=2026-01-19 --chunk=300
php artisan trade:compute-eod --date=2026-01-19 --ticker=BBCA
```
- **Catatan:** command ini holiday-aware (mengikuti market calendar) sesuai deskripsi command.

### `watchlist:scorecard:check-live`
**Tujuan:** Jalankan *live execution check* untuk kandidat dari strategy run: hitung `spread_pct`, `gap_pct`, `chase_pct`, cek `entry_windows` & `avoid_windows`, dan hasilkan `default_recommendation`.  
**Lokasi implementasi:** `app/Console/Commands/WatchlistScorecardCheckLive.php` (`WatchlistScorecardCheckLive`)

**Argumen/Opsi:**
- `--trade-date= : Trade date (YYYY-MM-DD)` (tanggal plan/watchlist dibuat)
- `--exec-date= : Exec date (YYYY-MM-DD)` (tanggal eksekusi rencana / hari beli)
- `--policy= : Nama policy (mis. WEEKLY_SWING / DIVIDEND / dll sesuai watchlist)`
- `--input= : Path file JSON snapshot (atau gunakan STDIN bila implementasi mendukung)`
- **Kapan dipakai:** pagi sebelum/selama market berjalan, setelah kamu punya plan kandidat (strategy run).
- **Output utama:** hasil evaluasi per ticker `results[]` + `default_recommendation`, dan tersimpan sebagai `watchlist_strategy_checks`.

**Contoh:**
```bash
php artisan watchlist:scorecard:check-live --trade-date=2026-01-19 --exec-date=2026-01-20 --policy=WEEKLY_SWING --input=storage/app/snapshots/live.json
```

**Catatan:**
- Snapshot minimal idealnya berisi `bid`, `ask`, `last` (untuk spread) + `open`, `prev_close` (untuk gap).
- `avoid_windows` dapat memakai format `HH:MM-HH:MM` dan juga token `close/open` (mis. `15:15-close`) bila sistem scorecard kamu mendukungnya sesuai `scorecard.md`.

### `watchlist:scorecard:compute`
**Tujuan:** Hitung metrik scorecard berbasis hasil `check-live` + OHLC harian pada `exec_date`, lalu simpan ke `watchlist_scorecards`.  
- `feasible_rate` → dari hasil eligibility (berapa kandidat eligible)  
- `fill_rate` → dari apakah slice price “tersentuh” OHLC di `exec_date`  

**Lokasi implementasi:** `app/Console/Commands/WatchlistScorecardCompute.php` (`WatchlistScorecardCompute`)

**Argumen/Opsi:**
- `--trade-date= : Trade date (YYYY-MM-DD)`
- `--exec-date= : Exec date (YYYY-MM-DD)`
- `--policy= : Nama policy`
- **Kapan dipakai:** setelah `watchlist:scorecard:check-live` (supaya ada data check), dan setelah EOD tersedia untuk `exec_date` (agar fill-rate akurat).
- **Output utama:** 1 row scorecard per run pada `watchlist_scorecards`.

**Contoh:**
```bash
php artisan watchlist:scorecard:compute --trade-date=2026-01-19 --exec-date=2026-01-20 --policy=WEEKLY_SWING
```

### `portfolio:expire-plans`
**Tujuan:** Expire PLANNED portfolio plans whose entry expiry has passed  
**Lokasi implementasi:** `app/Console/Commands/PortfolioExpirePlans.php` (`PortfolioExpirePlans`)

**Argumen/Opsi:**
- `--date=`
- `--account_id=`
- **Kapan dipakai:** harian (mis. pagi sebelum market open) untuk membersihkan plan yang sudah tidak valid.
- **Output utama:** plan PLANNED yang lewat expiry → status `EXPIRED` + event `PLAN_EXPIRED`.
- **Contoh:**
```bash
php artisan portfolio:expire-plans --date=2026-01-25
php artisan portfolio:expire-plans --date=2026-01-25 --account_id=1
```

### `portfolio:ingest-trade`
**Tujuan:** Ingest single trade/fill into portfolio (FIFO lots + derived positions)  
**Lokasi implementasi:** `app/Console/Commands/PortfolioIngestTrade.php` (`PortfolioIngestTrade`)

**Argumen/Opsi:**
- `--account=1 : Account ID`
- `--ticker= : Ticker code (e.g. BBCA)`
- `--ticker_id= : Ticker ID`
- `--date= : Trade date (YYYY-MM-DD)`
- `--side= : BUY|SELL`
- `--qty= : Qty (shares)`
- `--price= : Price`
- `--external_ref= : External ref for idempotency`
- `--broker_ref= : Broker ref`
- `--source=manual : Source`
- `--currency=IDR : Currency`
- `--meta= : JSON meta (optional)`
- **Kapan dipakai:** setiap ada transaksi/fill yang mau dicatat ke sistem (manual atau hasil import).
- **Output utama:**
  - menulis `portfolio_trades` (ledger),
  - BUY → membuat/menambah `portfolio_lots`,
  - SELL → membuat `portfolio_lot_matches` (FIFO),
  - update `portfolio_positions` (state/qty/avg/realized),
  - emit event penting (`ENTRY_FILLED`, `ADD_FILLED`, `EXIT_*`, `TP*`, dll).
- **Contoh (BUY):**
```bash
php artisan portfolio:ingest-trade --account=1 --ticker=ITMA --date=2026-01-14 --side=BUY --qty=1100 --price=2000 --external_ref=202601140000635336
```
- **Contoh (SELL):**
```bash
php artisan portfolio:ingest-trade --account=1 --ticker=ITMA --date=2026-01-14 --side=SELL --qty=1100 --price=1900 --external_ref=202601140001802794
```
- **Catatan penting tentang qty:** di IDX, **1 lot = 100 saham** → kalau kamu input dari Ajaib, konversi `qty = lot × 100`.

### `portfolio:value-eod`
**Tujuan:** Update portfolio_positions valuations using canonical EOD close  
**Lokasi implementasi:** `app/Console/Commands/PortfolioValueEod.php` (`PortfolioValueEod`)

**Argumen/Opsi:**
- `--date= : Trade date (YYYY-MM-DD)`
- `--account=1 : Account ID`
- **Kapan dipakai:** setelah publish EOD (dan idealnya setelah compute-eod selesai) untuk update `market_value` dan `unrealized_pnl`.
- **Effective trade date:** kalau tanggal yang kamu kirim bukan hari bursa, sistem akan mundur ke **previous trading day** (deterministik).
- **Contoh:**
```bash
php artisan portfolio:value-eod --date=2026-01-19 --account=1
```

### `portfolio:cancel-plan`
**Tujuan:** Cancel a portfolio plan (status=CANCELLED) and emit PLAN_CANCELLED event.  
**Lokasi implementasi:** `app/Console/Commands/PortfolioCancelPlan.php` (`PortfolioCancelPlan`)

**Argumen/Opsi:**
- `plan_id : Plan ID`
- `--reason=manual_cancel : Reason string`
- **Kapan dipakai:** ad hoc, kalau kamu mau membatalkan plan sebelum entry terjadi.
- **Aturan:** hanya valid untuk plan status `PLANNED`. Kalau sudah `OPENED`, kamu harus exit/close posisi (bukan cancel).
- **Output utama:** status plan → `CANCELLED` + event `PLAN_CANCELLED` (plus reason).
- **Contoh:**
```bash
php artisan portfolio:cancel-plan 123 --reason="manual_cancel"
php artisan portfolio:cancel-plan 123 --reason="news_risk"
```

### `inspire`
**Tujuan:** command contoh bawaan Laravel untuk menampilkan quote.

**Contoh:**
```bash
php artisan inspire
```


---
## Ringkasan dependensi (biar nggak salah urutan)

- `market-data:publish-eod` **bergantung** pada canonical run SUCCESS (hasil import/rebuild).

- `trade:compute-eod` **bergantung** pada `ticker_ohlc_daily` sudah terisi (hasil publish).

- `watchlist:scorecard:check-live` **bergantung** pada strategy run / plan kandidat tersedia untuk `trade_date + exec_date + policy`.

- `watchlist:scorecard:compute` **bergantung** pada hasil check-live (untuk feasible_rate) dan OHLC `exec_date` tersedia (untuk fill_rate).

- `portfolio:value-eod` **bergantung** pada canonical EOD tersedia untuk tanggal effective (hasil publish/canonical repo).

- `portfolio:ingest-trade` bisa jalan kapan saja (ledger), tapi valuasi unrealized butuh market data.
