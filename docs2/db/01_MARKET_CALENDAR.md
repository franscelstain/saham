# 01 — Market Calendar Contract

## Purpose
Kontrak sumber tanggal trading untuk semua proses watchlist, agar `next_trading_day(asof_eod_date)` selalu deterministik, dapat diaudit, dan tidak pernah di-hardcode di code.

## Scope (LOCKED)
Kontrak ini berlaku untuk:
- PLAN produksi
- CONFIRM yang perlu memetakan `asof_eod_date` ke hari trading aktif
- backtest dan OOS proof
- seluruh helper seperti `next_trading_day(d)` dan `prev_trading_day(d)`

Semua komponen di atas **wajib** memakai sumber kalender yang sama secara semantik.

## Required table (logical)
Nama tabel fisik bebas (mis. `market_calendar`), tetapi kontrak kolom dan perilaku berikut **wajib** tersedia.

## Required columns (minimum, LOCKED)
- `cal_date` (DATE, PK): tanggal kalender lokal bursa.
- `is_trading_day` (TINYINT/BOOLEAN): `1` jika bursa buka, `0` jika libur/tidak ada sesi reguler.
- `holiday_name` (VARCHAR/TEXT, nullable): nama hari libur/non-trading day bila relevan.
- `session_code` (VARCHAR, nullable, recommended): metadata sesi bila suatu hari mempunyai perlakuan khusus; **tidak** mengubah definisi `is_trading_day`.
- `source_version` (VARCHAR/TEXT, nullable, recommended): versi/sumber kalender untuk audit.
- `updated_at` (DATETIME/TIMESTAMP, recommended): waktu update record untuk audit koreksi.

## Required behaviors (LOCKED)
- `next_trading_day(d)` berarti tanggal terkecil `> d` dengan `is_trading_day = 1`.
- `prev_trading_day(d)` berarti tanggal terbesar `< d` dengan `is_trading_day = 1`.
- Semua lookup trading day memakai **zona waktu bursa Indonesia** (`Asia/Jakarta`).
- Proses PLAN **dilarang** menebak hari trading dari weekday. Sabtu/Minggu/libur nasional/libur bursa khusus tetap harus lewat tabel ini.
- Rentang kalender wajib mencakup seluruh periode backtest + periode produksi aktif.
- Tidak boleh ada duplikasi `cal_date`.
- `session_code` boleh menyimpan metadata seperti half-day atau sesi khusus, tetapi selama bursa masih dianggap buka reguler untuk keputusan PLAN, nilai `is_trading_day` tetap `1`.

## Timezone and cutoff policy (LOCKED)
- `cal_date` selalu diinterpretasikan sebagai tanggal lokal `Asia/Jakarta`, bukan UTC date hasil konversi mentah.
- Freeze/cutoff harian untuk penentuan `asof_eod_date` pada pipeline implementasi boleh berbeda antar-sistem, tetapi setelah suatu bar EOD dinyatakan final untuk tanggal tertentu, lookup kalender **tetap** harus menganggap tanggal itu sebagai tanggal bursa lokal yang sama.
- Backtest tidak boleh memakai fungsi kalender yang dihitung dari timezone server bila server tidak berada pada `Asia/Jakarta`.

## Correction policy (LOCKED)
- Perubahan historis kalender (mis. libur tambahan, cuti bersama, pembatalan sesi) wajib diperlakukan sebagai **koreksi data sumber** yang dapat diaudit.
- Koreksi kalender historis tidak boleh diakali dengan exception di code atau daftar tanggal tersembunyi di aplikasi.
- Jika ada koreksi kalender yang memengaruhi hasil backtest/OOS yang sudah pernah dipakai sebagai evidence, evidence tersebut harus dapat direproduksi ulang atau ditandai obsolete secara eksplisit.

## Data quality rules (LOCKED)
- Jika `is_trading_day = 0`, `holiday_name` boleh NULL tetapi disarankan terisi.
- Jika `is_trading_day = 1`, `holiday_name` harus NULL.
- `cal_date` tidak boleh NULL.
- Jika lookup `next_trading_day` gagal karena tanggal tidak tercakup, proses yang memerlukan kalender wajib abort dengan fail code terkait kalender.
- Coverage kalender tidak boleh bolong di tengah range aktif. Tidak cukup hanya punya tanggal trading; hari non-trading juga harus tetap terwakili agar fungsi next/prev deterministik.

## Audit expectations (recommended)
- Simpan `source_version` atau metadata sumber kalender lain yang cukup untuk menelusuri asal data.
- Jika ada import ulang, simpan jejak perubahan melalui log ETL, audit table, atau versioned source snapshot.

## Notes
- Calendar contract ini dipakai bersama oleh produksi dan backtest. Backtest tidak boleh memakai kalender lain yang berbeda tanpa deklarasi eksplisit.
- Bila ada hari trading dengan jam lebih pendek, kontrak ini tetap menganggapnya `is_trading_day = 1`; detail sesi intraday ada di sistem lain bila diperlukan.
