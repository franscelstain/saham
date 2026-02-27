# 04 — EOD Indicators Contract

## Purpose
Kontrak input indikator EOD yang dipakai policy.

## Required table (logical)
Nama bebas (mis. `ticker_indicators_daily`).

## Required columns (minimum, untuk Weekly Swing)
- `asof_eod_date` (DATE)
- `ticker_id` (FK)
- `dv20_idr` (BIGINT/DECIMAL): rata-rata nilai transaksi 20 hari (IDR)
- `atr14_pct` (DECIMAL): ATR14 dalam persen (0..1 atau 0..100 harus konsisten; policy akan mengunci unit)
- `roc20` (DECIMAL): rate of change 20 hari (mis. -0.10 .. 0.10)
- `hh20` (DECIMAL): highest high 20 hari

## Required keys
- Unique(asof_eod_date, ticker_id)

## Notes
Indikator tambahan boleh ada, tetapi policy tidak boleh mengandalkan kolom yang tidak dikontrak.
