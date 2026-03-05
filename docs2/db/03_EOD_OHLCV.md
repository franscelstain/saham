# 03 — EOD OHLCV Contract

## Purpose
Kontrak input EOD OHLCV yang dipakai PLAN dan backtest supaya perhitungan harga, return, dan event evaluasi tidak ambigu.

## Prerequisites
- [`01_MARKET_CALENDAR.md`](01_MARKET_CALENDAR.md)
- [`02_TICKERS_MASTER.md`](02_TICKERS_MASTER.md)

## Scope (LOCKED)
Kontrak ini berlaku untuk semua bar harian final yang dipakai oleh:
- scoring/feature PLAN
- universe snapshot yang memerlukan harga/likuiditas
- backtest evaluation
- OOS proof
- audit replay

## Required table (logical)
Nama tabel fisik bebas (mis. `ticker_ohlc_daily`).

## Required columns (minimum, LOCKED)
- `asof_eod_date` (DATE): tanggal bar EOD pada zona waktu bursa (`Asia/Jakarta`)
- `ticker_id` (FK ke tickers)
- `open` (DECIMAL)
- `high` (DECIMAL)
- `low` (DECIMAL)
- `close` (DECIMAL)
- `volume_shares` (BIGINT) — unit **shares**, bukan lot
- `turnover_idr` (BIGINT/DECIMAL, recommended)
- `corporate_action_adjustment_flag` (BOOLEAN/VARCHAR, recommended)
- `created_at` / `updated_at` (recommended)

## Required keys (LOCKED)
- Unique(`asof_eod_date`, `ticker_id`)

## Required behaviors (LOCKED)
- Satu ticker hanya boleh punya satu bar final per `asof_eod_date`.
- Unit volume canonical adalah **shares**.
- `high >= max(open, close, low)` dan `low <= min(open, close, high)` wajib benar untuk bar valid.
- Nilai harga dan volume tidak boleh negatif.
- Untuk tanggal yang sudah dianggap final, seluruh komponen yang memakai OHLCV harus membaca snapshot semantik yang sama.

## Timezone and finalization policy (LOCKED)
- `asof_eod_date` adalah tanggal bursa lokal `Asia/Jakarta`.
- Pipeline boleh punya jam finalize sendiri, tetapi setelah bar dinyatakan final untuk `asof_eod_date`, PLAN, backtest, dan evidence audit harus mengacu ke tanggal yang sama.
- Dilarang menghitung `asof_eod_date` dari timezone server secara implisit bila server tidak berada pada `Asia/Jakarta`.

## Adjusted-price rule (LOCKED)
Sistem wajib memilih **satu** mode dan mendeklarasikannya eksplisit:
- `UNADJUSTED`: harga raw bursa; atau
- `ADJUSTED`: harga sudah disesuaikan corporate action.

Mode yang dipilih harus sama untuk:
- scoring/feature PLAN yang bergantung harga historis,
- backtest evaluation,
- bukti OOS,
- indikator turunan yang memakai sumber OHLCV ini.

Campur adjusted dan unadjusted dalam satu window evaluasi **dilarang**.

## Null and missing-data policy (LOCKED)
- `open`, `high`, `low`, `close`, dan `volume_shares` adalah field wajib untuk bar valid.
- Jika salah satu field wajib NULL atau invalid, ticker dianggap data-missing untuk tanggal tersebut.
- Ticker dengan data-missing tidak boleh dipaksa lolos scoring dengan nilai tebakan, carry-forward, atau fallback diam-diam, kecuali policy terkait memang mengizinkan dan terdokumentasi eksplisit.

## Missing/duplicate/correction handling (LOCKED)
- Duplicate row untuk key yang sama wajib dianggap error source dan harus diselesaikan sebelum PLAN/backtest berjalan.
- Koreksi historis setelah bar dipakai produksi/backtest harus tercermin sebagai perubahan data source yang dapat diaudit, bukan silent overwrite tanpa jejak.
- Jika source melakukan restatement historis, evidence yang pernah dibuat dari data lama harus tetap bisa ditelusuri terhadap versi sumber yang dipakai saat itu.

## Data quality rules (LOCKED)
- `open <= 0`, `high <= 0`, `low <= 0`, atau `close <= 0` tidak valid kecuali memang ada kebijakan pasar khusus yang terdokumentasi jelas; default WS menganggap harga non-positif tidak valid.
- `volume_shares < 0` tidak valid.
- `turnover_idr < 0` tidak valid bila kolom tersedia.
- Bar dengan `high < low` tidak valid.
- Bar dengan kombinasi O/H/L/C yang melanggar range harga tidak valid.

## Audit expectations (recommended)
- Simpan jejak `created_at`/`updated_at` atau metadata batch import.
- Jika tersedia, simpan versi sumber atau batch_id ETL agar reproduksi backtest/evidence lebih kuat.

## Notes
- Jika sistem hanya punya `volume`, dokumentasi implementasi wajib memastikan bahwa unitnya benar-benar shares.
- Untuk backtest yang memakai entry/exit `NEXT_OPEN`, kualitas field `open` adalah kontrak kritikal.
