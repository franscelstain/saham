# COMPUTE_EOD (TradeAxis) — Contract (LOCKED)

Compute‑EOD menghasilkan indikator harian dari **CANONICAL OHLC** (`ticker_ohlc_daily`). Dokumen ini adalah **kontrak yang mengikat** untuk:
- basis harga (`price_used`)
- definisi window (trading days)
- kolom output yang dipersist ke `ticker_indicators_daily`
- perilaku saat data invalid / corporate action

> Semua hal yang terkait **scoring, grouping, reasons**, dan output watchlist (preopen/scorecard) adalah domain **watchlist/policy** dan **bukan** scope compute‑eod.

## 1) Input & Tanggal Acuan
- Input utama: `ticker_ohlc_daily` untuk `trade_date = target_date`.
- `target_date` harus **trading day**.
- Jika market-data run terbaru `CANONICAL_HELD/FAILED`, gunakan `last_good_trade_date` (lihat `docs/MARKET_DATA.md`).

## 2) Price Basis (LOCKED)
- `price_used` dipakai untuk indikator berbasis harga penutupan (MA/ROC/RSI):
  - jika `price_basis = ADJ_CLOSE` dan `adj_close` tersedia ➜ gunakan `adj_close`
  - selain itu ➜ gunakan `close`
- **ATR14** wajib pakai OHLC real: `high/low/close` (bukan adjusted).

Tujuan: tren stabil saat ada CA tanpa merusak volatilitas ATR.

## 3) Rolling Window = Trading Days (LOCKED)
Semua lookback menggunakan **trading days** dari `market_calendar`.
- Weekend/libur **tidak dihitung**.
- Jangan pakai kalender biasa.

## 4) Output Persist (LOCKED)
Compute‑EOD **wajib** mengisi/menulis 1 row per `(ticker_id, trade_date)` ke `ticker_indicators_daily` untuk setiap ticker yang diproses.

Selain indikator, Compute‑EOD juga boleh menghasilkan **signal global (policy‑agnostic)** dan mempersistnya ke tabel terpisah `ticker_signals_daily`.
Signal global ini adalah **fitur input** (mis. untuk WEEKLY_SWING), bukan output akhir watchlist.

### 4.1 Kolom Wajib Ada
Kolom berikut **wajib ada** di `ticker_indicators_daily` (nama kolom dan makna dikunci):
- Identitas: `trade_date`, `ticker_id`
- Copy raw (dari canonical hari ini): `open`, `high`, `low`, `close`, `volume`, `adj_close` (nullable), `ca_hint` (nullable), `ca_event` (nullable)
- Basis turunan: `price_used`
- Validitas row:
  - `is_valid` (TINYINT/BOOLEAN; 1 valid, 0 invalid)
  - `invalid_reason` (VARCHAR; NULL jika valid)

**LOCKED enum `invalid_reason`:**
- `INVALID_BAR`
- `CA_GUARD`

### 4.2 Kolom Indikator (Minimal)
Kolom indikator minimal yang dipersist untuk kebutuhan watchlist/backtest:
- Moving average: `ma20`, `ma50`, `ma200`
- Oscillator/volatility: `rsi14`, `atr14`, `atr14_pct`
- Liquidity/momentum/levels: `dv20_idr`, `roc20`, `hh20`, `ll5`
- Volume rollups: `vol_sma20`, `vol_ratio`

> Kolom lain boleh ada, tapi tidak boleh mengubah makna kolom LOCKED di atas.

### 4.3 Tabel Signals (Global, LOCKED)
Compute‑EOD **tidak** lagi menulis kolom `decision_code/signal_code/volume_label_code/signal_age_days` ke `ticker_indicators_daily`.
Sebagai gantinya, jika modul signal diaktifkan, Compute‑EOD menulis 1 row per `(ticker_id, trade_date)` ke `ticker_signals_daily`.

Kolom yang dikunci di `ticker_signals_daily`:
- Identitas: `trade_date`, `ticker_id`
- Klasifikasi global: `decision_code`, `signal_code`, `volume_label_code`
- Tracking umur signal: `signal_first_seen_date`, `signal_age_days`
- Mirror validitas (opsional tapi direkomendasikan): `is_valid`, `invalid_reason`

Catatan:
- Signal ini **satu** untuk semua policy (global). Policy boleh mengabaikan.
- Perbedaan "signal per-policy" (jika suatu saat ada) harus dibuat di dokumen policy, bukan di compute‑eod.

## 5) Window & NULL Policy (LOCKED)
Jika data historis tidak cukup untuk indikator tertentu:
- indikator terkait = **NULL**
- tidak boleh "mengarang" nilai

Ini berlaku per-kolom. Row tetap ditulis.

## 6) Definisi Indikator yang Sering Salah (LOCKED)
> Semua definisi window di bawah menggunakan **trading days** (lihat §3).

### 6.1 Include/Exclude Today
- **Exclude today**: window hanya memakai data **sebelum** `trade_date` target.
- **Include today**: window termasuk bar `trade_date` target.

### 6.2 Volume SMA & Ratio
- `vol_sma20` = SMA(volume) 20 trading days **exclude today**.
- `vol_ratio` = `volume_today / vol_sma20`.
  - Jika `vol_sma20` NULL atau 0 ➜ `vol_ratio` = NULL.

> Naming `vol_sma20` tetap dipakai, namun makna **wajib exclude today**. Tidak boleh berubah tanpa bump kontrak.

### 6.3 dv20_idr
- traded value harian = `close * volume` (IDR).
- `dv20_idr` = AVG(traded value) 20 trading days **exclude today**.
- **LOCKED missing volume:** jika `volume` NULL ➜ dianggap 0 (traded value 0) dan tetap ikut AVG.

### 6.4 atr14_pct
- `atr14_pct` = `atr14 / price_used` pada `trade_date` hari ini (include today).
- Jika `price_used` NULL atau 0 ➜ NULL.

### 6.5 hh20 / ll5
- `hh20` = max(`high`) 20 trading days **exclude today**.
- `ll5` = min(`low`) 5 trading days **exclude today**.

### 6.6 roc20
- `roc20` = `(price_used_today / price_used_20_trading_days_ago) - 1`.
- Butuh minimal 21 data `price_used` (hari ini + 20 trading days lalu). Jika tidak cukup ➜ NULL.

## 7) Guardrails Kualitas Data (LOCKED)
Compute‑EOD **tidak pernah skip insert**.
- Row selalu ditulis.
- Invalid ditandai via `is_valid=0` dan `invalid_reason`.

### 7.1 INVALID_BAR
Jika bar hari ini invalid (contoh: OHLC tidak masuk akal, volume negatif, high < low, dll):
- `is_valid=0`
- `invalid_reason='INVALID_BAR'`
- Semua kolom indikator di §4.2 = NULL
- Kolom copy raw (§4.1) tetap diisi apa adanya (untuk audit)

### 7.2 CA_GUARD
Jika pada `trade_date` hari ini terdapat `ca_hint` atau `ca_event` (non-null):
- `is_valid=0`
- `invalid_reason='CA_GUARD'`
- Semua kolom indikator di §4.2 = NULL
- Kolom copy raw (§4.1) tetap diisi

Catatan: CA_GUARD adalah mekanisme **data-quality** agar downstream (watchlist/policy) tidak menghasilkan sinyal palsu. Penanganan rekomendasi adalah domain watchlist.

## 8) Commands
- Single date:
  - `php artisan trade:compute-eod --date=YYYY-MM-DD`
- Range:
  - `php artisan trade:compute-eod --from=YYYY-MM-DD --to=YYYY-MM-DD`
- Single ticker (opsional):
  - `php artisan trade:compute-eod --date=YYYY-MM-DD --ticker=BBCA`

Urutan aman harian:
1) `market-data:import-eod`
2) `market-data:publish-eod`
3) `trade:compute-eod`

## 9) Backfill & Warmup (LOCKED)
Jika menambah kolom indikator baru:
- lakukan **backfill** range penuh agar watchlist/backtest tidak campur NULL dan non-NULL.

Warmup minimal:
- `dv20_idr`, `hh20`, `roc20` butuh 20 trading days sebelum hari ini (roc20 butuh hari ini + 20 trading days lalu).
- `ma200` butuh 200 trading days sebelum hari ini.

Tanggal-tanggal awal dataset memang akan menghasilkan NULL (lihat §5) — ini **bukan bug**.
