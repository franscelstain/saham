# Watchlist DB Schema

Dokumen ini mencatat **struktur database yang dipakai fitur Watchlist** (table/view/kolom) beserta fungsi tiap table & kolom dalam konteks Watchlist.

> **STATUS (LIVING DOCS)**
> Dokumen ini adalah catatan kondisi sistem saat ini. Jika ada perubahan di code/DB/command/output yang belum tercatat di sini, maka dokumen ini **wajib** diupdate agar kembali sinkron.
> Dokumen ini membantu operasional dan audit; dokumen ini tidak mengubah aturan **LOCKED** di `docs/watchlist/watchlist.md`.

**Batasan penting (LOCKED):**
- Watchlist **hanya boleh merombak / membuat** table dengan prefix `watchlist_*`.
- Table market data berikut **jangan dirombak** (watchlist hanya membaca):
  - `tickers`
  - `ticker_ohlc_daily`
  - `ticker_indicators_daily`
  - `ticker_dividend_events`
  - `md_runs`, `md_raw_eod`, `md_canonical_eod`, `md_candidate_validations`
  - `market_calendar`

---

## 1) Table persistence Watchlist

### 1.1 `watchlist_daily`
**Fungsi:** menyimpan **1 payload preopen contract** per policy + tanggal eksekusi (audit/replay).

**Diisi oleh:**
- **Otomatis (fail-soft)** saat endpoint `GET /watchlist/preopen` dipanggil (service akan upsert 1 row per `(policy, trade_date, source)`).
- **Boleh manual** untuk kebutuhan debugging/replay, tapi normalnya tidak diperlukan.

Kolom:
- `watchlist_daily_id` (PK)
- `policy` (STRING): kode policy, contoh `WEEKLY_SWING`.
- `trade_date` (DATE): **tanggal eksekusi** (contract `meta.trade_date`).
- `asof_eod_date` (DATE): **tanggal EOD** yang dipakai untuk membentuk kandidat (contract `meta.asof_eod_date`).
- `canonical_ready` (BOOL): status kesiapan canonical EOD pada saat generate.
- `source` (STRING): label sumber penyimpanan (default `preopen_contract_<policy>`).
- `generated_at` (DATETIME|null): waktu generate payload (best-effort).
- `payload_json` (JSON): **payload preopen contract full**.
- `created_at`, `updated_at`

Index/constraint:
- UNIQUE: (`policy`,`trade_date`,`source`)
- INDEX: (`trade_date`,`policy`), (`asof_eod_date`,`policy`)

Relasi:
- 1 row `watchlist_daily` → banyak `watchlist_candidates`.

---

### 1.2 `watchlist_candidates`
**Fungsi:** denormalisasi kandidat per ticker (per group) untuk query ringan (UI/audit), tetapi tetap menyimpan JSON full untuk replay.

**Diisi oleh:**
- **Otomatis (fail-soft)** bersamaan dengan penyimpanan `watchlist_daily` saat endpoint `GET /watchlist/preopen` dipanggil.
- **Tidak diisi manual** (kecuali debugging), karena seharusnya selalu turunan dari payload contract.

Kolom:
- `watchlist_candidate_id` (PK)
- `watchlist_daily_id` (FK → `watchlist_daily.watchlist_daily_id`, cascade delete)
- `policy` (STRING)
- `trade_date` (DATE): tanggal eksekusi
- `asof_eod_date` (DATE): tanggal plan (EOD)
- `group_code` (STRING): `TOP_PICKS | SECONDARY | WATCH_ONLY | AVOID | NO_TRADE`
- `ticker` (STRING): ticker code, contoh `BBCA`
- `rank` (INT|null): urutan ranking dalam group (1..n)
- `score_total` (DECIMAL|null): skor akhir (untuk sorting/audit)

Denormalized PLAN:
- `setup_type` (STRING|null): `PULLBACK | BREAKOUT`
- `plan_entry` (INT|null)
- `plan_stop` (INT|null)
- `plan_tp1` (INT|null)
- `rr_est` (DECIMAL|null)

JSON audit:
- `reasons_json` (JSON|null): array reason objects (dari contract `reasons`).
- `eod_bar_json` (JSON|null): object EOD bar (dari contract `eod_bar`).
- `ticker_plan_json` (JSON|null): object ticker plan full (dari contract `ticker_plan`).
- `created_at`, `updated_at`

Index/constraint:
- UNIQUE: (`watchlist_daily_id`,`group_code`,`ticker`)
- INDEX: (`policy`,`trade_date`,`group_code`,`rank`)
- INDEX: (`ticker`,`asof_eod_date`)

---

### 1.3 `watchlist_intraday_snapshots`
**Fungsi:** menyimpan snapshot live (bid/ask/last + optional top-3) untuk kebutuhan CONFIRM (scorecard check-live).

**Diisi oleh:**
- **Manual** (SQL insert) untuk tahap awal / demo.
- **Otomatis** oleh integrasi broker/feed/scraper (di luar scope repo ini).

**Diupdate oleh:**
- Command `watchlist:scorecard:check-live` akan mengisi/menambah state retry (best-effort): `confirm_retry_count`, `confirm_last_checked_at`, `confirm_next_check_at`.

Kolom:
- `snapshot_id` (PK)
- `trade_date` (DATE): tanggal eksekusi (harus sama dengan contract `meta.trade_date`)
- `ticker_id` (BIGINT): refer ke `tickers.ticker_id`
- `ticker_code` (STRING)
- `checked_at` (DATETIME|null): kapan snapshot diambil

Harga utama:
- `bid1`, `ask1`, `last`, `open`, `open_or_last_exec` (DECIMAL|null)
- `spread_pct` (DECIMAL|null): best-effort (0..1)

Orderbook optional (top-3):
- `bid2`, `bid3`, `ask2`, `ask3` (DECIMAL|null)
- `bid_lots1..3`, `ask_lots1..3` (INT|null)

State untuk strict CONFIRM (retry budget, fail-soft):
- `confirm_retry_count` (INT, default 0): jumlah DELAY yang sudah terjadi untuk ticker ini pada `trade_date` (lihat `docs/watchlist/scorecard.md` bagian Retry budget).
- `confirm_last_checked_at` (DATETIME|null): timestamp terakhir CONFIRM memproses snapshot ini.
- `confirm_next_check_at` (DATETIME|null): timestamp earliest re-check berikutnya (cooldown) yang dihitung evaluator.

Index/constraint:
- UNIQUE: (`trade_date`,`ticker_id`)
- INDEX: (`trade_date`,`ticker_code`)

---

### 1.4 `watchlist_strategy_runs`
**Fungsi:** menyimpan PLAN (payload watchlist) untuk scorecard pipeline.

**Diisi oleh:**
- **Otomatis** oleh command `watchlist:scorecard:check-live` (upsert 1 row per `(asof_eod_date, exec_trade_date, policy, source)`).

Kolom:
- `run_id` (PK)
- `trade_date` (DATE): **tanggal EOD plan** (legacy naming; di scorecard pipeline ini = `asof_eod_date`)
- `exec_trade_date` (DATE): tanggal eksekusi
- `policy` (STRING)
- `source` (STRING)
- `generated_at` (DATETIME|null)
- `payload_json` (JSON): payload plan full
- `created_at`, `updated_at`

Index/constraint:
- UNIQUE: (`trade_date`,`exec_trade_date`,`policy`,`source`)
- INDEX: (`exec_trade_date`,`policy`)

---

### 1.5 `watchlist_strategy_checks`
**Fungsi:** menyimpan hasil CONFIRM (check-live) untuk sebuah run.

**Diisi oleh:**
- **Otomatis** oleh command `watchlist:scorecard:check-live` (append per eksekusi/check).

Kolom:
- `check_id` (PK)
- `run_id` (FK → `watchlist_strategy_runs.run_id`, cascade delete)
- `checked_at` (DATETIME)
- `snapshot_json` (JSON): input live snapshot (sesuai docs/watchlist/scorecard.md)
- `result_json` (JSON): output eligibility check
- `created_at`, `updated_at`

Index:
- (`run_id`,`checked_at`)

---

### 1.6 `watchlist_scorecards`
**Fungsi:** menyimpan metrik performa ringkas hasil evaluasi scorecard.

**Diisi oleh:**
- **Otomatis** oleh command `watchlist:scorecard:compute` (1 row per run).

Kolom:
- `scorecard_id` (PK)
- `run_id` (FK → `watchlist_strategy_runs.run_id`, cascade delete, UNIQUE)
- `feasible_rate` (DECIMAL|null)
- `fill_rate` (DECIMAL|null)
- `outcome_rate` (DECIMAL|null)
- `payload_json` (JSON|null): detail metrics tambahan
- `created_at`, `updated_at`

---

## 2) Table market data yang dibaca Watchlist (read-only)

> Catatan: tabel-tabel market data di bawah **bukan** domain Watchlist. Watchlist hanya membaca.
> Pengisiannya berasal dari pipeline Market Data + Compute EOD (lihat dokumen commands terkait).

### `tickers`
Dipakai untuk mapping `ticker_id` ↔ `ticker_code` dan nama perusahaan (join dari `ticker_indicators_daily`).

**Diisi oleh:** pipeline Market Data (import master ticker). Watchlist tidak mengubah.

### `ticker_ohlc_daily`
Dipakai untuk:
- OHLC canonical untuk `asof_eod_date`
- candle sebelumnya untuk `prev_close`
- DV20 (avg close*volume 20 hari trading sebelum `asof_eod_date`)

**Diisi oleh:** pipeline Market Data (publish canonical EOD → `ticker_ohlc_daily`). Watchlist tidak mengubah.

### `ticker_indicators_daily`
Dipakai untuk:
- skor (`score_total`), label (`decision_code`, `signal_code`, `volume_label_code`), dan indikator (MA/RSI/ATR/dll)
- filter valid/invalid (gate awal kandidat)

**Diisi oleh:** pipeline Compute EOD (hasil compute indikator dari data canonical). Watchlist tidak mengubah.

### `market_calendar`
Dipakai untuk:
- menentukan **previous trading day** (prev candle)
- menentukan **next trading day** dari `asof_eod_date` (jadi `cal_date` eksekusi)
- mengambil **prior N trading dates** untuk agregasi teknikal (berbasis tanggal bursa sebelum `asof_eod_date`):
  - `hh50` = highest high 50 hari (LOOKBACK_50)
  - `hh20` = highest high 20 hari (LOOKBACK_20)
  - `hh10` = highest high 10 hari (LOOKBACK_10) — dipakai oleh `INTRADAY_LIGHT` untuk gate breakout
  - `ll10` = lowest low 10 hari (LOOKBACK_10)
  - `ll5`  = lowest low 5 hari (LOOKBACK_5)
  - `ll3`  = lowest low 3 hari (LOOKBACK_3) — dipakai oleh `INTRADAY_LIGHT` untuk anchor stop (tight)
  - `close_5ago` = close pada 5 trading days sebelum `asof_eod_date` (LOOKBACK_5)
  - `roc5` = (close_now - close_5ago) / close_5ago — momentum jangka pendek untuk `INTRADAY_LIGHT`

Kolom minimal yang dibaca:
- `cal_date` (DATE)
- `is_trading_day` (BOOL/INT)

**Diisi oleh:** manual/seed (sekali) atau pipeline kalender bursa. Watchlist tidak mengubah.

### `ticker_dividend_events`
Dipakai khusus untuk policy **DIVIDEND_SWING**.

Fungsi:
- memilih event dividen terdekat berdasarkan **`ex_date`** dalam window T+2..T+12 trading days dari `exec_trade_date`.

Kolom minimal yang dibaca:
- `ticker_id`
- `ex_date` (DATE)

Kolom opsional (untuk derived/scoring):
- `cum_date` (DATE|null)
- `cash_dividend` (DECIMAL|null)
- `dividend_yield_est` (DECIMAL|null)

**Diisi oleh:**
- Otomatis oleh proses corporate action (jika ada integrasi), **atau**
- Manual (SQL insert) untuk tahap awal/POC (lihat `docs/watchlist/strategi.md`).
