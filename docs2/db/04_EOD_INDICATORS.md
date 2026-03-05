# 04 — EOD Indicators Contract

## Purpose
Kontrak input indikator EOD yang dipakai policy watchlist, terutama Weekly Swing, agar unit, sumber, dan perilaku data konsisten.

## Prerequisites
- [`02_TICKERS_MASTER.md`](02_TICKERS_MASTER.md)
- [`03_EOD_OHLCV.md`](03_EOD_OHLCV.md)

## Scope (LOCKED)
Kontrak ini berlaku untuk indikator harian final yang dipakai oleh:
- PLAN Weekly Swing
- validator kesiapan data
- backtest/OOS yang mengevaluasi parameter Weekly Swing
- audit parity terhadap OHLCV source

## Required table (logical)
Nama tabel fisik bebas (mis. `ticker_indicators_daily`).

## Required columns (minimum untuk Weekly Swing, LOCKED)
- `asof_eod_date` (DATE)
- `ticker_id` (FK)
- `dv20_idr` (BIGINT/DECIMAL): rata-rata nilai transaksi 20 hari dalam IDR
- `atr14_pct` (DECIMAL): ATR14 dalam **decimal-percent / ratio 0..1**
- `roc20` (DECIMAL): return 20 hari dalam **decimal-percent / ratio**, mis. `0.125` berarti `12.5%`
- `hh20` (DECIMAL): highest high 20 hari
- `bars_count` (INT, recommended): jumlah bar valid yang dipakai menghitung indikator
- `source_ohlcv_mode` (VARCHAR, recommended): `ADJUSTED` / `UNADJUSTED` untuk parity audit
- `created_at` / `updated_at` (recommended)

## Required keys (LOCKED)
- Unique(`asof_eod_date`, `ticker_id`)

## Unit rules (LOCKED)
- `dv20_idr` selalu dalam IDR nominal.
- `atr14_pct` dan `roc20` untuk WS dikunci sebagai **decimal-percent / ratio**. Contoh: `0.0325` berarti `3.25%`.
- Jika source layer menghasilkan angka persen 0..100, normalisasi ke ratio 0..1 wajib dilakukan sebelum dipakai policy WS.
- `hh20` wajib berada pada satuan harga yang konsisten dengan mode OHLCV sumber (`ADJUSTED` atau `UNADJUSTED`).

## Source-parity rule (LOCKED)
- Semua indikator pada satu row harus berasal dari snapshot OHLCV yang sama (`asof_eod_date`, `ticker_id`).
- Formula indikator boleh dihitung di pipeline mana pun, tetapi output unitnya harus mengikuti kontrak ini.
- Jika source indikator memakai `ADJUSTED`, maka komponen PLAN/backtest yang membandingkan indikator terhadap harga atau terhadap indikator lain harus memakai basis sumber yang sama.

## Timezone and readiness policy (LOCKED)
- `asof_eod_date` selalu tanggal bursa lokal `Asia/Jakarta`.
- Row indikator untuk tanggal tertentu tidak boleh dianggap siap bila OHLCV sumber tanggal yang sama belum final.
- `bars_count`, bila tersedia, harus merepresentasikan jumlah bar valid yang benar-benar dipakai menghitung indikator, bukan jumlah kalender kasar.

## Null and missing-data policy (LOCKED)
- Jika indikator wajib tidak tersedia/invalid, ticker harus diperlakukan sebagai data-missing atau guard-fail sesuai policy; tidak boleh diisi tebakan.
- `dv20_idr`, `atr14_pct`, `roc20`, dan `hh20` yang NULL untuk row final dianggap tidak memenuhi kontrak minimal WS.
- Carry-forward nilai indikator dari tanggal sebelumnya tanpa deklarasi eksplisit **dilarang**.

## Data quality rules (LOCKED)
- `dv20_idr < 0` tidak valid.
- `atr14_pct < 0` tidak valid.
- `hh20 <= 0` tidak valid.
- Jika `bars_count` tersedia dan kurang dari minimum history policy, ticker wajib dianggap belum siap secara data.
- Jika `roc20` bernilai di luar range yang secara jelas mustahil akibat kesalahan unit (mis. `12.5` saat yang dimaksud `12.5%`), row harus diperlakukan sebagai salah unit sampai dinormalisasi.

## Correction policy (LOCKED)
- Koreksi historis indikator harus dapat ditelusuri ke koreksi OHLCV sumber atau perubahan formula pipeline yang terdokumentasi.
- Perubahan formula indikator yang bersifat breaking harus diikuti update policy/backtest yang relevan; tidak boleh disisipkan diam-diam ke dalam row historis tanpa jejak.

## Audit expectations (recommended)
- Simpan jejak `source_ohlcv_mode`, batch import, atau metadata yang cukup untuk membuktikan parity dengan OHLCV.
- Simpan `updated_at` atau audit log untuk replay/debug.

## Notes
- Indikator tambahan boleh ada, tetapi policy tidak boleh bergantung pada kolom yang tidak dikontrak tanpa update dokumen policy.
- Bila source indikator memakai adjusted-price mode, backtest dan PLAN harus konsisten dengan mode yang sama.
