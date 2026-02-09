# Watchlist Operasional

Dokumen ini menjelaskan cara kerja **operasional** untuk menyempurnakan data Watchlist: langkah proses, urutan eksekusi, dan contoh command/query yang dipakai.

> **STATUS (LIVING DOCS)**
> Dokumen ini adalah panduan penggunaan Watchlist saat aplikasi sudah jadi. Jika ada perubahan di code/command/parameter/urutan eksekusi yang belum tercatat di sini, maka dokumen ini **wajib** diupdate agar operator/user tidak salah menjalankan.
> Dokumen ini tidak mengubah aturan **LOCKED** di `docs/watchlist/watchlist.md`.

## Daftar aktivitas (ringkas)
1. Pastikan prasyarat data (calendar, tickers, OHLC, indicators, dividend events)
2. Generate PLAN (preopen) via endpoint `/watchlist/preopen`
3. (Opsional) Isi snapshot intraday untuk CONFIRM
4. Jalankan CONFIRM via `watchlist:scorecard:check-live`
5. Hitung scorecard via `watchlist:scorecard:compute`

## Konsep tanggal (WAJIB konsisten)
- `asof_eod_date` = tanggal EOD yang dipakai membentuk kandidat (biasanya **hari bursa sebelumnya**).
- `trade_date` (di preopen contract) = tanggal eksekusi yang dituju (next trading day dari `asof_eod_date`).

> Contoh: jika EOD terakhir adalah Jumat 2026-01-30, maka `asof_eod_date=2026-01-30` dan `trade_date` biasanya Senin 2026-02-02 (jika tidak libur).

---

## 0) Prasyarat data
Watchlist **membaca** data berikut sebelum bisa menghasilkan output yang valid:

1. `market_calendar` terisi lengkap (kolom tanggal: `cal_date`).
2. `tickers` terisi.
3. `ticker_ohlc_daily` terisi untuk `asof_eod_date` (dan beberapa hari sebelumnya untuk DV20 + prev candle).
4. `ticker_indicators_daily` terisi untuk `asof_eod_date` (hasil compute-eod).
5. Khusus policy `DIVIDEND_SWING`: `ticker_dividend_events` terisi (minimal `ticker_id` + `ex_date`).

Khusus `INTRADAY_LIGHT`:
- Butuh histori OHLC yang cukup untuk mengambil **prior trading dates** sebelum `asof_eod_date`:
  - LOOKBACK_10 untuk `hh10` (highest high 10 hari)
  - LOOKBACK_3 untuk `ll3` (lowest low 3 hari)
  - LOOKBACK_5 untuk `close_5ago` → hitung `roc5`
- Karena semua lookback berbasis **trading day**, `market_calendar` wajib lengkap agar mapping tanggalnya benar.

Jika salah satu kosong, watchlist tetap bisa mengembalikan payload, tapi biasanya `canonical_ready=false` dan/atau hasil akan banyak `NO_TRADE`.

Khusus `DIVIDEND_SWING`:
- Policy **wajib** punya event dividen berdasarkan `ex_date` (window T+2..T+12 trading days dari `trade_date` eksekusi).
- Jika event tidak ada, kandidat akan gugur dengan reason `DS_EVENT_MISSING`.

Contoh INSERT minimal (manual) untuk event dividen:
```sql
INSERT INTO ticker_dividend_events
(ticker_id, ex_date, cum_date, cash_dividend, dividend_yield_est, created_at, updated_at)
VALUES
(1, '2026-02-10', '2026-02-07', 120.00, 0.0123, NOW(), NOW());
```

Catatan khusus Dividend Swing:
- Watchlist memilih event berdasarkan **`ex_date`** (bukan `cum_date`).
- Window yang dipakai: **T+2..T+12 trading days** dari `trade_date` eksekusi.

---

## 1) Pipeline data EOD (ringkas)
Watchlist tidak membangun EOD sendiri. Pastikan pipeline market data & compute-eod sudah jalan.

Rujukan detail command ada di `docs/commands.md` (bagian Market Data + Compute EOD).

Minimal (gambaran):
1. Import RAW EOD
2. Rebuild canonical
3. Publish canonical ke `ticker_ohlc_daily`
4. Compute indikator ke `ticker_indicators_daily`

---

## 2) Generate PLAN preopen (endpoint)
Endpoint:
- `GET /watchlist/preopen?policy=WEEKLY_SWING&capital_idr=5000000`

Query params:
- `policy` (opsional; default dari config `trade.watchlist.policy_default`)
- `capital_idr` (opsional; jika kosong → Mode A (no capital))
- `risk_per_trade_pct` (opsional)
- `asof_eod_date` (opsional; override tanggal EOD yang dipakai)
- `now_ts` (opsional; untuk testing deterministik)

Output:
- strict preopen contract sesuai `docs/watchlist/preopen.md`.

Persistence (otomatis, fail-soft):
- `watchlist_daily` menyimpan payload full per (`policy`,`trade_date`,`source`)
- `watchlist_candidates` menyimpan kandidat per group (denormalized + json audit)

### 2.1 Mode penggunaan (praktis)
- **Mode A (no capital):** panggil preopen tanpa `capital_idr` → sistem hanya memberikan kandidat + plan level (tidak ada lots).
- **Mode B (with capital):** panggil preopen dengan `capital_idr` → sistem menghasilkan `recommendations` + `execution_slices` (tranche).

**Catatan penting:**
- PLAN = murni dari EOD (`asof_eod_date`). Tidak boleh dimutasi oleh intraday.
- CONFIRM = layer tambahan, tidak menghapus kandidat.

Catatan tambahan `INTRADAY_LIGHT`:
- PLAN tetap dibangun dari EOD (`asof_eod_date`), tapi policy ini memakai agregasi tambahan dari histori OHLC berbasis trading day:
  - `hh10` dan `ll3` (min/max dari prior trading dates)
  - `roc5` (momentum dari close 5 trading days lalu)
- Semua derivasi tersebut dilakukan di layer query (repository) menggunakan `market_calendar` untuk mengambil tanggal bursa yang tepat.

---

## 3) Isi snapshot intraday (untuk CONFIRM)
CONFIRM membandingkan PLAN dengan kondisi live. Snapshot live disimpan di `watchlist_intraday_snapshots`.

Ada 2 pola pengisian:
1) Manual insert (paling simpel untuk awal)
2) Otomatis (scraper/broker feed) — di luar scope repo ini

Alternatif praktis (tanpa SQL):
3) Ingest JSON manual via command (upsert by trade_date+ticker_id):
```bash
php artisan watchlist:intraday:ingest --trade-date=2026-02-02 --input=snapshot.json
```

Contoh `snapshot.json`:
```json
{
  "checked_at": "09:20:00",
  "tickers": [
    {"ticker": "BBCA", "bid1": 10000, "ask1": 10005, "last": 10000, "open": 9950, "bid_lots1": 20, "ask_lots1": 15}
  ]
}
```

Contoh INSERT minimal (manual):
```sql
INSERT INTO watchlist_intraday_snapshots
(trade_date, ticker_id, ticker_code, checked_at, bid1, ask1, last, open, open_or_last_exec, spread_pct, created_at, updated_at)
VALUES
('2026-02-02', 1, 'BBCA', NOW(), 10000, 10005, 10000, 9950, 10000, 0.0005, NOW(), NOW());
```

Field tambahan (opsional) untuk strict CONFIRM retry-budget (dipakai oleh command `watchlist:scorecard:check-live`):
- `confirm_retry_count` (default 0)
- `confirm_last_checked_at` (nullable)
- `confirm_next_check_at` (nullable)

Jika kamu insert manual tanpa field ini, sistem akan menganggap retry=0 dan tetap jalan; state retry akan diisi/update otomatis saat CONFIRM dijalankan (fail-soft).

Jika punya top-3 orderbook:
- isi `bid2,bid3,ask2,ask3` + `bid_lots1..3` + `ask_lots1..3`.

---

## 4) Jalankan CONFIRM (check-live)
Command:
- `php artisan watchlist:scorecard:check-live --trade-date=<ASOF_EOD_DATE> --exec-date=<TRADE_DATE> --policy=<POLICY> --input=<snapshot.json>`

Keterangan opsi:
- `--trade-date` = **asof_eod_date** (EOD plan date)
- `--exec-date` = **trade_date** (execution date)
- `--policy` = policy code
- `--input` = file JSON snapshot (atau STDIN)

Output:
- Eligibility check result, dan persist ke:
  - `watchlist_strategy_runs` (PLAN, upsert by unique key)
  - `watchlist_strategy_checks` (append)

Selain itu, jika table `watchlist_intraday_snapshots` tersedia, sistem juga akan update state retry per ticker (best-effort):
- `confirm_retry_count`
- `confirm_last_checked_at`
- `confirm_next_check_at`

Tujuan: membatasi loop DELAY dan menegakkan cooldown/window sesuai `docs/watchlist/scorecard.md` (Retry budget & cooldown).

Rujukan format snapshot JSON & result ada di `docs/watchlist/scorecard.md`.

---

## 5) Hitung scorecard (compute)
Command:
- `php artisan watchlist:scorecard:compute --trade-date=<ASOF_EOD_DATE> --exec-date=<TRADE_DATE> --policy=<POLICY>`

Tujuan:
- hitung metrik ringkas (feasible_rate, fill_rate, dll)
- persist ke `watchlist_scorecards` (1 row per run)

Dependency:
- butuh hasil `check-live`
- butuh OHLC untuk `exec_date` tersedia (agar fill-rate akurat)

---

## 6) Query cepat untuk audit

Ambil payload PLAN terbaru:
```sql
SELECT * FROM watchlist_daily
WHERE policy='WEEKLY_SWING'
ORDER BY watchlist_daily_id DESC
LIMIT 1;
```

Ambil Top Picks (ranked):
```sql
SELECT ticker, rank, score_total, plan_entry, plan_stop, plan_tp1
FROM watchlist_candidates
WHERE policy='WEEKLY_SWING' AND trade_date='2026-02-02' AND group_code='TOP_PICKS'
ORDER BY rank ASC;
```

Cek apakah snapshot intraday sudah ada:
```sql
SELECT COUNT(*) as n
FROM watchlist_intraday_snapshots
WHERE trade_date='2026-02-02';
```

---

## 7) Step 14: CLI Backtest (Preopen)

Command ini menjalankan *preopen build* secara batch untuk rentang tanggal (hanya pada hari trading berdasarkan `market_calendars`).

Jalankan:

```bash
php artisan watchlist:backtest --policy=WEEKLY_SWING --from=2026-01-01 --to=2026-02-01
```

Opsi:
- `--persist=1` untuk menyimpan hasil snapshot harian (preopen) menggunakan source yang bisa dibedakan (default `backtest`).
- `--source=...` untuk override source.
- `--limit=N` untuk membatasi jumlah hari trading yang diproses.

Contoh (persist + source khusus):

```bash
php artisan watchlist:backtest --policy=WEEKLY_SWING --from=2026-01-01 --to=2026-02-01 --persist=1 --source=bt_ws
```
