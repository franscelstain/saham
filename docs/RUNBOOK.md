# RUNBOOK.md
Operational runbook untuk Market Data → Canonical → Compute-EOD → Watchlist (dan fondasi Portfolio).  
Scope: aturan dan command yang *benar-benar dipakai* oleh TradeAxis.

---

## 0) Definisi yang harus dipahami
- **RAW**: data mentah per sumber (audit trail).
- **CANONICAL**: satu sumber kebenaran untuk downstream (`ticker_ohlc_daily`).
- **effective_end_date**: tanggal trading terakhir yang *boleh* diproses berdasar cutoff.
- **last_good_trade_date**: tanggal canonical terakhir yang valid saat run terbaru **HELD/FAILED**.

---

## 1) Prasyarat wajib (sekali beres, jangan asal)
### 1.1 `market_calendar` wajib lengkap
- Semua tanggal untuk range historis yang akan dipakai harus ada.
- `is_trading_day` harus benar (libur, cuti bersama, dll).
- Downstream memakai **trading days**, bukan kalender.

### 1.2 `tickers` wajib lengkap dan status aktif benar
- Universe expected untuk coverage = tickers aktif.
- Kalau tickers belum lengkap, `coverage_pct` jadi misleading.

> Catatan: kalau pengisian `market_calendar`/`tickers` masih manual, pastikan ada SOP internal siapa yang maintain dan kapan update.

---

## 2) Bootstrap awal (wajib sebelum percaya watchlist)
Tujuan bootstrap: indikator tidak “kosong/noise” karena window kurang.

### 2.1 Target history minimal
- Minimal aman: **≥ 60 trading days** (RSI/ATR/volSMA).
- Ideal: **≥ 200 trading days** (MA200 kalau dipakai filter tren).
- Rekomendasi praktis: **~320 trading days** (lookback + warmup).

### 2.2 Urutan bootstrap (per batch)
**A. Import RAW**
```bash
php artisan market-data:import-eod --from=YYYY-MM-DD --to=YYYY-MM-DD
```

**B. Publish CANONICAL**
```bash
php artisan market-data:publish-eod --from=YYYY-MM-DD --to=YYYY-MM-DD
```

**C. Compute EOD (indikator)**
```bash
php artisan trade:compute-eod --from=YYYY-MM-DD --to=YYYY-MM-DD
```

> Jalankan bertahap: mis. per 1–3 bulan trading days, atau per 20–50 hari kerja, tergantung kapasitas DB.

### 2.3 Acceptance check setelah bootstrap
- `ticker_ohlc_daily` terisi untuk range yang di-bootstrap.
- `ticker_indicators_daily` terisi dan indikator inti tidak dominan NULL.
- Tidak ada lonjakan anomali besar pada tanggal tertentu (indikasi timezone shift / CA / mapping error).

---

## 3) Operasi harian (trading day)
### 3.1 Timing rule (cutoff)
- **Sebelum cutoff (16:30 WIB)**: jangan memproses “today”. Sistem harus pakai **previous trading day**.
- **Sesudah cutoff**: proses “today” *jika* trading day dan data memenuhi kualitas.

### 3.2 Daily pipeline (setelah cutoff)
**A. Import RAW**
```bash
php artisan market-data:import-eod --from=YYYY-MM-DD --to=YYYY-MM-DD
```

**B. Publish CANONICAL**
```bash
php artisan market-data:publish-eod --from=YYYY-MM-DD --to=YYYY-MM-DD
```

**C. Compute EOD**
```bash
php artisan trade:compute-eod --date=YYYY-MM-DD
# atau range bila diperlukan:
# php artisan trade:compute-eod --from=YYYY-MM-DD --to=YYYY-MM-DD
```

**D. Validator (opsional, untuk konfirmasi kualitas top picks / disagreement)**
```bash
php artisan market-data:validate-eod --date=YYYY-MM-DD
```
- Gunakan saat ada gejala disagreement/keunikan data.
- Ingat limit harian validator (mis. EODHD).

### 3.3 Saat hari libur / non-trading day
- Skip compute/watchlist untuk tanggal itu.
- Kalau kamu tetap menjalankan import, hasilnya jangan dipublish sebagai canonical trading day.

---

## 4) Quality gate yang wajib dihormati downstream
### 4.1 Coverage gate
Jika coverage di bawah threshold → **CANONICAL_HELD**.
Konsekuensinya:
- Downstream (compute/watchlist/portfolio) **tidak boleh** pakai trade_date yang HELD.
- Downstream harus fallback ke **last_good_trade_date**.

### 4.2 last_good_trade_date (kontrak operasional)
Kalau run terbaru HELD/FAILED:
- Pakai tanggal canonical terakhir yang **SUCCESS** sebagai acuan compute/watchlist.

Contoh query standar (sesuaikan nama kolom bila beda):
```sql
SELECT effective_end_date
FROM md_runs
WHERE status = 'SUCCESS'
ORDER BY run_id DESC
LIMIT 1;
```

---

## 5) Corporate Actions / anomali (wajib stop, jangan “dibenerin diam-diam”)
### 5.1 Tanda bahaya
- Gap ekstrim / split-like move.
- `ca_hint` / `ca_event` terisi.
- Disagreement major naik drastis.

### 5.2 Aturan tindakan
- Jika `ca_hint/ca_event` muncul pada trade_date:
  - **jangan percaya indikator pada tanggal itu**
  - lakukan **rebuild canonical** pada range terdampak
  - rerun compute-eod untuk range itu

Command:
```bash
php artisan market-data:rebuild-canonical --from=YYYY-MM-DD --to=YYYY-MM-DD
php artisan market-data:publish-eod       --from=YYYY-MM-DD --to=YYYY-MM-DD
php artisan trade:compute-eod             --from=YYYY-MM-DD --to=YYYY-MM-DD
```

---

## 6) Failure Playbook (aksi cepat)
### S1 — masalah minor (stale kecil, beberapa ticker missing)
- Ulang publish untuk tanggal itu.
- Pastikan tickers universe tidak berubah mendadak.
```bash
php artisan market-data:publish-eod --from=YYYY-MM-DD --to=YYYY-MM-DD
php artisan trade:compute-eod       --date=YYYY-MM-DD
```

### S2 — disagreement / kualitas meragukan
- Jalankan validator untuk tanggal itu.
- Bila memang mismatch besar → treat as S3.
```bash
php artisan market-data:validate-eod --date=YYYY-MM-DD
```

### S3 — insiden serius (timezone shift, split/CA, canonical tercemar)
- Freeze: jangan gunakan trade_date terbaru.
- Rebuild canonical range + compute ulang.
```bash
php artisan market-data:rebuild-canonical --from=YYYY-MM-DD --to=YYYY-MM-DD
php artisan market-data:publish-eod       --from=YYYY-MM-DD --to=YYYY-MM-DD
php artisan trade:compute-eod             --from=YYYY-MM-DD --to=YYYY-MM-DD
```

---

## 7) Otomasi (kalau belum ada scheduler internal)
Kalau `Kernel::schedule()` belum dipakai, kamu harus pilih salah satu:
- **Cron eksternal**: jalankan pipeline harian setelah cutoff.
- **Laravel Scheduler**: isi schedule() + cron `schedule:run`.

Runbook ini tidak memaksa metode, tapi pipeline **harus** konsisten urutannya.

---

## 8) Checklist “siap dipakai watchlist” (PASS/FAIL)
PASS kalau:
- canonical untuk trade_date acuan **SUCCESS**, bukan HELD/FAILED
- `ticker_ohlc_daily` ada untuk mayoritas universe (coverage ≥ threshold)
- `ticker_indicators_daily` terisi dan indikator inti tidak dominan NULL
- tidak ada CA/anomali aktif pada top picks
- trade_date acuan = trading day (calendar benar)

FAIL kalau salah satu poin di atas tidak terpenuhi.
