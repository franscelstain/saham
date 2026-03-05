# 21 — Golden Manual Input Template (CONFIRM, Non-Ladder) — Weekly Swing

## Purpose
Template input manual resmi untuk CONFIRM Weekly Swing.

## Scope
Menjelaskan format input manual agar snapshot CONFIRM tidak salah tafsir.

## Inputs
- Operator/manual checker, engineer import, reviewer, dan auditor.

## Outputs
- Pedoman input manual CONFIRM yang sah.

Dokumen 1 halaman ini adalah **template operasional** untuk input manual CONFIRM dari aplikasi (contoh: Ajaib).
Tujuannya: **operator tidak salah input**, data **deterministik**, dan status CONFIRM **tidak bisa diperdebatkan**.

> **LOCKED:** CONFIRM memakai **intraday aggregate snapshot** (last/chg/volume/turnover), **bukan** order book ladder.
> Bid/ask/spread/imbalance/orderbook ladder adalah **non-contract fields** dan wajib diabaikan.

---

## 1) Kontrak Data (LOCKED)

### Header snapshot (`watchlist_confirm_snapshots`)
- `policy_code` = `WS`
- `trade_date` = tanggal PLAN yang sedang dikonfirmasi
- `captured_at` = waktu data **diambil** dari aplikasi (manual)
- `source` = `manual`
- `inserted_at` otomatis oleh DB

### Item per ticker (`watchlist_confirm_snapshot_items`) — WAJIB
- `ticker_code` (contoh `ANTM`)
- `last_price` (IDR, integer)
- `chg_pct` (persen, decimal)
- `volume_shares` (shares, integer; **bukan lot**)
- `turnover_idr` (IDR, integer; **Turnover nominal**, bukan Turnover%)

### Validitas (LOCKED)
- `effective_captured_at = LEAST(captured_at, inserted_at)`
- TTL CONFIRM: snapshot valid jika `snapshot_age_sec <= snapshot_max_age_sec (LOCKED: 900)` (15 menit)

Failure yang wajib (LOCKED):
- tidak ada snapshot → `DELAY` + `WS_SNAPSHOT_MISSING`
- snapshot expired → `DELAY` + `WS_STALE`
- field aggregate wajib tidak lengkap → `DELAY` + `WS_INPUT_INCOMPLETE`

---

## 2) Sumber Field (Mapping Aplikasi → Sistem) (LOCKED)

Ambil dari aplikasi:
- `last_price`: **Close/Last** (bagian aggregate, bukan ladder)
- `chg_pct`: **Change %** (persen intraday)
- `volume_shares`: **Volume** (format `K/M/B/T`)
- `turnover_idr`: **Detail Price → Turnover (NOMINAL)**
  - **DILARANG:** memakai `Turnover %` sebagai `turnover_idr`

---

## 3) Normalisasi Angka (LOCKED)

### A) Harga IDR (titik = ribuan)
- Rule: hapus semua `.` → integer
- Contoh: `4.610` → `4610`

### B) Angka bersuffix `K/M/B/T` (koma = desimal)
Parser (LOCKED):
1) trim spasi
2) ganti `,` → `.` untuk parsing desimal
3) suffix:
   - `K`×1_000, `M`×1_000_000, `B`×1_000_000_000, `T`×1_000_000_000_000
4) hasil akhir integer dengan pembulatan `ROUND_HALF_UP` (LOCKED)

Contoh:
- `39,33 M` → `39.33 × 1_000_000` → `39330000`
- `143,96 B` → `143.96 × 1_000_000_000` → `143960000000`

Reject / INVALID (LOCKED):
- ada karakter selain digit + `.` + `,` + suffix `K/M/B/T`
- format campur tidak valid (mis. `39.33 M`)
- nilai kosong / negatif

---

## 4) Template Input (SQL & CSV)

### A) SQL — buat header snapshot
```sql
INSERT INTO watchlist_confirm_snapshots (policy_code, trade_date, captured_at, source, note)
VALUES ('WS', 'YYYY-MM-DD', 'YYYY-MM-DD HH:MM:SS', 'manual', 'intraday aggregate manual input');
```

### B) SQL — insert item per ticker
```sql
INSERT INTO watchlist_confirm_snapshot_items
(snapshot_id, ticker_code, last_price, chg_pct, volume_shares, turnover_idr)
VALUES
(<<snapshot_id>>, 'TICKER', <<last_price>>, <<chg_pct>>, <<volume_shares>>, <<turnover_idr>>);
```

### C) CSV (jika ada import tool)
Header (LOCKED):
```csv
ticker_code,last_price,chg_pct,volume_shares,turnover_idr
```

Contoh valid:
```csv
ANTM,4610,1.2500,39330000,143960000000
BBCA,8900,0.5600,12500000,215500000000
```

---

## 5) Contoh 1 Ticker (Valid)

Input aplikasi:
- Close/Last: `4.610`
- Change: `+1,25%`
- Volume: `39,33 M`
- Turnover (nominal): `143,96 B`
- `captured_at`: `2026-03-03 10:05:00`

Hasil yang disimpan:
- `last_price = 4610`
- `chg_pct = 1.2500`
- `volume_shares = 39330000`
- `turnover_idr = 143960000000`

---

## 6) Checklist Operator (LOCKED)

Sebelum input:
1) Pastikan `trade_date` sesuai PLAN yang dikonfirmasi.
2) Catat `captured_at` saat angka diambil dari aplikasi.
3) Pastikan `turnover_idr` diambil dari **Turnover nominal**, bukan Turnover%.

Saat input:
4) Normalisasi angka sesuai aturan.
5) Pastikan volume adalah **shares**.
6) Insert header, lalu items.

Sesudah input:
7) Jalankan query verifikasi.
8) Jika expired (`snapshot_age_sec > snapshot_max_age_sec (LOCKED: 900)`), CONFIRM wajib `DELAY + WS_STALE`.

---

## 7) Query Verifikasi (Wajib)

### A) Ambil snapshot terbaru (LOCKED order)
```sql
SELECT snapshot_id, policy_code, trade_date, captured_at, inserted_at
FROM watchlist_confirm_snapshots
WHERE policy_code='WS' AND trade_date='YYYY-MM-DD'
ORDER BY captured_at DESC, snapshot_id DESC
LIMIT 1;
```

### B) Cek kelengkapan item (no NULL)
```sql
SELECT ticker_code
FROM watchlist_confirm_snapshot_items
WHERE snapshot_id = <<snapshot_id>>
  AND (last_price IS NULL OR chg_pct IS NULL OR volume_shares IS NULL OR turnover_idr IS NULL);
```
Harus 0 baris.

### C) Cek TTL (effective_captured_at)
```sql
SELECT
  snapshot_id,
  TIMESTAMPDIFF(
    SECOND,
    LEAST(captured_at, inserted_at),
    NOW()
  ) AS snapshot_age_sec
FROM watchlist_confirm_snapshots
WHERE snapshot_id = <<snapshot_id>>;
```
Valid jika `snapshot_age_sec <= snapshot_max_age_sec (LOCKED: 900)`.