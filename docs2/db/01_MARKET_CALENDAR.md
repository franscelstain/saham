# 01 — Market Calendar Contract

## Purpose
Kontrak minimal untuk menentukan `next_trading_day(asof_eod_date)`.

## Required table (logical)
Nama tabel bebas (mis. `market_calendar`), tetapi harus menyediakan kolom dan makna berikut.

## Required columns (minimum)
- `cal_date` (DATE, PK): tanggal kalender.
- `is_trading_day` (TINYINT/BOOLEAN): 1 jika bursa buka, 0 jika libur.
- `holiday_name` (VARCHAR/TEXT, nullable): nama hari libur (opsional).

## Required behaviors
- Fungsi `next_trading_day(d)` berarti tanggal terkecil `> d` dengan `is_trading_day=1`.
- Untuk safety, harus tersedia rentang kalender yang mencakup periode backtest dan produksi.

## Notes
Watchlist tidak boleh hardcode libur; selalu pakai kontrak ini.
