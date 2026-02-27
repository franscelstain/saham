# 03 — EOD OHLCV Contract

## Purpose
Kontrak input EOD OHLCV yang dipakai PLAN.

## Required table (logical)
Nama bebas (mis. `ticker_ohlc_daily`).

## Required columns (minimum)
- `asof_eod_date` (DATE): tanggal bar EOD
- `ticker_id` (FK ke tickers)
- `open`, `high`, `low`, `close` (DECIMAL)
- `volume` (BIGINT)

## Required keys
- Unique(asof_eod_date, ticker_id)

## Notes
Jika ada penyesuaian corporate action, kontrak harus menjelaskan apakah harga sudah adjusted atau tidak.
