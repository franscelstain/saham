# COMPUTE_EOD (TradeAxis) — Contract & Ops

Compute‑EOD menghasilkan indikator harian dari **CANONICAL OHLC** (`ticker_ohlc_daily`). Dokumen ini mengunci: **basis harga**, **rolling window trading days**, **output kolom**, dan **guard kualitas/CA**.

## 1) Input & Tanggal Acuan
- Input utama: `ticker_ohlc_daily` untuk `trade_date = target_date`.
- `target_date` harus **trading day** dan idealnya sama dengan `effective_end_date` market-data.
- Jika market-data run terbaru `CANONICAL_HELD/FAILED`, gunakan `last_good_trade_date` (lihat MARKET_DATA.md §4).

## 2) Price Basis Policy (wajib konsisten)
- `priceUsed` untuk MA/RSI:
  - jika `price_basis = ADJ_CLOSE` dan `adj_close` tersedia ➜ gunakan `adj_close`
  - selain itu ➜ gunakan `close`
- **ATR** wajib pakai OHLC real: `high/low/close` (bukan adjusted).

Tujuan: indikator tren stabil saat ada CA, tanpa merusak volatilitas ATR.

## 3) Rolling Window = Trading Days
Semua lookback menggunakan **trading days** dari `market_calendar`.
- Jangan pakai kalender (hari Minggu/libur tidak dihitung).

## 4) Output (ticker_indicators_daily)
Minimal kolom yang dipakai watchlist:
- `trade_date`, `ticker_id`
- `close`, `adj_close`, `price_used`
- `ma20`, `ma50`, `ma200`
- `rsi14`, `atr14`
- `vol_sma20`, `vol_ratio` (lihat §6)
- `support_20d`, `resistance_20d` (lihat §6)
- `ca_hint`, `ca_event` (copy dari canonical)
- `is_valid` / `invalid_reason` (jika ada)

> Jika tabel/kolom aktual berbeda, tetap jaga makna yang sama agar watchlist/portfolio tidak salah interpretasi.

## 5) NULL Policy (insufficient window)
Jika data historis tidak cukup untuk indikator tertentu:
- set indikator terkait = NULL
- jangan “mengarang” nilai
- catat log/flag (opsional) untuk audit

## 6) Definisi yang sering salah (dikunci)
> Catatan: kolom DB bernama `vol_sma20`, namun maknanya adalah `vol_sma20_prev` (exclude today).

- **vol_sma20**: SMA volume 20 trading days **sebelum hari ini** (exclude today).
- **vol_ratio**: `today_volume / vol_sma20` (jika `vol_sma20` NULL/0 ➜ NULL).
- **support_20d / resistance_20d**: min(low) / max(high) dari 20 trading days **sebelum hari ini** (exclude today).

## 7) Guardrails kualitas data
- Jika bar hari ini invalid (OHLC tidak masuk akal, volume negatif, dll) ➜ skip/flag.
- Jika `ca_hint`/`ca_event` terisi pada `target_date` ➜ **STOP rekomendasi** untuk tanggal itu:
  - set indikator output utama = NULL (bar netral) untuk mencegah sinyal palsu
  - set `decision_code = 2 (Hindari)`
  - action operator: jalankan `market-data:rebuild-canonical` untuk range terdampak, lalu rerun `trade:compute-eod`.

## 7.1) Mapping Code (kontrak UI/downstream)
Nilai berikut harus konsisten di UI dan proses downstream.

- `signal_code`:
  - 0 Unknown
  - 1 Base/Sideways
  - 2 Early Uptrend
  - 3 Accumulation
  - 4 Breakout
  - 5 Strong Breakout
  - 6 Breakout Retest
  - 7 Pullback Healthy
  - 8 Distribution
  - 9 Climax/Euphoria
  - 10 False Breakout

- `volume_label_code`:
  - 1 Dormant
  - 2 Ultra Dry
  - 3 Quiet
  - 4 Normal
  - 5 Early Interest
  - 6 Volume Burst/Accumulation
  - 7 Strong Burst/Breakout
  - 8 Climax/Euphoria

- `decision_code`:
  - 1 False Breakout/Batal
  - 2 Hindari
  - 3 Hati-hati
  - 4 Perlu Konfirmasi
  - 5 Layak Beli

## 7.2) Score Columns (ranking only)
`score_total` dan breakdown (`score_trend`, `score_momentum`, `score_volume`, `score_breakout`, `score_risk`) dipakai **hanya untuk ranking/sorting** kandidat (mis. watchlist).

Rules:
- Tidak boleh mengubah `decision_code`.
- Untuk bar `INVALID_BAR` atau `CA_GUARD` ➜ score harus netral (0).
- Skala kecil dan deterministic; tidak boleh mengandalkan data selain indikator yang sudah dihitung pada hari itu.

## 8) Command
- Single date:
  - `php artisan trade:compute-eod --date=YYYY-MM-DD`
- Range:
  - `php artisan trade:compute-eod --from=YYYY-MM-DD --to=YYYY-MM-DD`
- Single ticker (opsional):
  - `php artisan trade:compute-eod --date=YYYY-MM-DD --ticker=BBCA`

Urutan aman harian:
1) market-data:import-eod
2) market-data:publish-eod
3) trade:compute-eod