# 02 — Tickers Master Contract

## Purpose
Kontrak master ticker untuk universe watchlist, backtest, dan audit join antar-tabel.

## Prerequisites
- [`01_MARKET_CALENDAR.md`](01_MARKET_CALENDAR.md)

## Scope (LOCKED)
Kontrak ini menjadi identitas referensi untuk:
- universe PLAN produksi
- mapping hasil CONFIRM
- join ke OHLCV dan indicators
- evaluasi backtest/OOS
- runtime output dan audit trail

## Required table (logical)
Nama tabel fisik bebas (mis. `tickers`), tetapi minimal harus menyediakan kontrak berikut.

## Required columns (minimum, LOCKED)
- `ticker_id` (BIGINT/INT, PK)
- `ticker_code` (VARCHAR, unique, uppercase canonical)
- `is_active` (ENUM('Yes','No') atau BOOLEAN canonical)
- `listed_date` (DATE, nullable, recommended)
- `delisted_date` (DATE, nullable, recommended)
- `board_code` (VARCHAR, nullable, recommended)
- `exchange_code` (VARCHAR, nullable, recommended)
- `updated_at` (DATETIME/TIMESTAMP, recommended untuk audit)

## Required behaviors (LOCKED)
- `ticker_code` adalah identitas tampilan utama untuk runtime output dan audit lintas sistem.
- Universe default produksi: semua ticker dengan status aktif pada tanggal referensi.
- Untuk backtest historis, eligibility universe **tidak boleh** memakai status saat ini saja bila tersedia histori listing/delisting; harus memakai status yang benar pada `asof_eod_date`.
- `ticker_code` harus unik secara case-insensitive; canonical output harus uppercase.
- Mapping `ticker_id -> ticker_code` harus stabil; jika corporate action mengubah kode, histori mapping wajib tetap dapat diaudit.
- Ticker yang sudah delisting tidak boleh tiba-tiba hilang dari histori join; minimal identitas historisnya tetap harus bisa di-resolve.

## Canonical code policy (LOCKED)
- `ticker_code` canonical adalah hasil normalisasi trim + uppercase.
- Variasi spasi, suffix dekoratif, atau alias tampilan tidak boleh menjadi identitas utama.
- Jika ada rename kode emiten, implementasi wajib menyimpan jalur audit yang jelas, mis. tabel histori simbol atau mapping reference yang versioned.

## Listing-status and survivorship policy (LOCKED)
- Jika source memiliki histori `listed_date`/`delisted_date`, maka backtest dan universe snapshot wajib memakai histori tersebut.
- Jika source **tidak** memiliki histori listing/delisting, dokumen policy/backtest wajib menyatakan adanya risiko survivorship bias secara eksplisit.
- Ticker aktif saat ini tetapi belum listed pada `asof_eod_date` historis tidak boleh lolos ke universe backtest historis.

## Data quality rules (LOCKED)
- `ticker_code` kosong/NULL tidak boleh lolos ke PLAN/CONFIRM output.
- Bila `is_active = 'No'` dan `delisted_date` tersedia, tanggal tersebut harus >= `listed_date` bila `listed_date` juga tersedia.
- `listed_date` dan `delisted_date` tidak boleh mengandung timezone; keduanya adalah tanggal bursa lokal.
- Ticker duplikat karena variasi spasi/case harus dinormalisasi di source layer, bukan dibiarkan ke policy.
- Join ke tabel pasar harian tidak boleh menghasilkan dua `ticker_id` berbeda untuk satu `ticker_code` canonical pada tanggal yang sama.

## Change-management policy (LOCKED)
- Penambahan ticker baru adalah perubahan data normal.
- Penggantian `ticker_id` untuk ticker yang sama tanpa jalur migrasi audit **dilarang**.
- Jika ada merger/rename/suspensi permanen, dokumentasi atau source history harus cukup untuk menjelaskan hubungan identitas lama dan baru.

## Audit expectations (recommended)
- Simpan metadata sumber master ticker atau snapshot import.
- Simpan `updated_at`/audit log agar perubahan universe dapat ditelusuri.

## Notes
- Policy boleh punya exclude list manual, tetapi exclude list tidak menggantikan kontrak master ticker.
- Jika source master tidak punya histori listing/delisting, dokumen policy/backtest wajib jujur menyatakan keterbatasan survivorship-bias tersebut.
