# 11 — Tabel Input Manual Intraday Snapshot (CONFIRM) — Weekly Swing

Dokumen ini mengunci **struktur tabel**, **kolom wajib**, dan **contoh data** untuk input manual intraday snapshot yang dipakai oleh CONFIRM (Weekly Swing).

## Prinsip (LOCKED)

1) **CONFIRM bukan real-time.** CONFIRM selalu menjawab berdasarkan **snapshot** yang terakhir diinput.

2) Snapshot yang diinput manual adalah **sumber kebenaran CONFIRM**. Jika data diambil di masa lalu namun baru diinput sekarang, isi datanya **tidak diperdebatkan**. Yang dinilai hanya **validitas usia snapshot**.

3) Snapshot wajib punya **dua timestamp**:
- `captured_at` = waktu data intraday aggregate diambil (diisi manual, dari jam perangkat saat mengambil data).
- `inserted_at` = waktu record masuk database (otomatis).

**LOCKED (anti-manipulasi):**
- `effective_captured_at = LEAST(captured_at, inserted_at)`
- TTL dihitung dari `effective_captured_at`.

4) **TTL CONFIRM (LOCKED): 15 menit**
- `snapshot_max_age_sec = 900`
- Jika `NOW() - effective_captured_at > 900 detik` → snapshot **EXPIRED** → CONFIRM wajib menghasilkan `label = DELAY`, dan wajib mengeluarkan reason `WS_STALE`.

5) Data snapshot bersifat **append-only**:
- **UPDATE/DELETE dilarang** (wajib diblok dengan trigger).
- Jika ingin input ulang, buat snapshot baru (record baru).

---

## Prerequisites
### Weekly Swing
10_WS_CONFIRM_OVERLAY.md

## A. Struktur Tabel (FINAL, tanpa opsi)

### A1) Header: `watchlist_confirm_snapshots` (WAJIB)

Fungsi: menyimpan metadata snapshot untuk 1 policy + 1 trade_date.

Kolom wajib:
- `policy_code` (contoh: `WS`)
- `trade_date` (tanggal PLAN yang dikonfirmasi)
- `captured_at` (waktu data diambil)
- `inserted_at` (otomatis)
- `source` (default `manual`)

DDL (MariaDB/MySQL):
```sql
CREATE TABLE IF NOT EXISTS watchlist_confirm_snapshots (
  snapshot_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_code       VARCHAR(16) NOT NULL,
  trade_date        DATE        NOT NULL,
  captured_at       DATETIME    NOT NULL,
  inserted_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source            VARCHAR(32) NOT NULL DEFAULT 'manual',
  note              TEXT        NULL,
  snapshot_hash     CHAR(64)    NULL,
  created_at        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (snapshot_id),
  KEY idx_snap_policy_trade_captured (policy_code, trade_date, captured_at),
  KEY idx_snap_trade_inserted (trade_date, inserted_at)
) ENGINE=InnoDB;
```

Trigger append-only:
```sql
DELIMITER //

CREATE TRIGGER trg_wcs_no_update
BEFORE UPDATE ON watchlist_confirm_snapshots
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'watchlist_confirm_snapshots is append-only (UPDATE blocked)';
END//

CREATE TRIGGER trg_wcs_no_delete
BEFORE DELETE ON watchlist_confirm_snapshots
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'watchlist_confirm_snapshots is append-only (DELETE blocked)';
END//

DELIMITER ;
```

---

### A2) Items: `watchlist_confirm_snapshot_items` (WAJIB)

Fungsi: menyimpan snapshot per ticker.

**LOCKED required fields per ticker (tanpa opsi):**
- `ticker_code`
- `last_price` (IDR, integer)
- `chg_pct` (persen, decimal) — wajib tersedia (tanpa opsi)
- `volume_shares` (integer, unit **shares**, bukan lot) (LOCKED)
  - Jika sumber hanya menyediakan lot, wajib konversi: `shares = lot × 100` sebelum disimpan.
- `turnover_idr` (integer)

DDL:
```sql
CREATE TABLE IF NOT EXISTS watchlist_confirm_snapshot_items (
  snapshot_item_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_id       BIGINT UNSIGNED NOT NULL,

  ticker_code       VARCHAR(16) NOT NULL,
  ticker_id         BIGINT UNSIGNED NULL,

  last_price        INT UNSIGNED NOT NULL,          -- 4.610 -> 4610
  chg_pct           DECIMAL(8,4) NOT NULL,          -- persen (contoh: 1.2500)
  volume_shares     BIGINT UNSIGNED NOT NULL,       -- 39,33 M -> 39330000
  turnover_idr      BIGINT UNSIGNED NOT NULL,       -- 143,96 B -> 143960000000
  item_hash         CHAR(64) NULL,

  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (snapshot_item_id),
  UNIQUE KEY uq_snap_ticker (snapshot_id, ticker_code),
  KEY idx_item_snap (snapshot_id),
  KEY idx_item_ticker (ticker_code),

  CONSTRAINT fk_wcs_items_snapshot
    FOREIGN KEY (snapshot_id)
    REFERENCES watchlist_confirm_snapshots(snapshot_id)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB;
```

Trigger append-only:
```sql
DELIMITER //

CREATE TRIGGER trg_wcsi_no_update
BEFORE UPDATE ON watchlist_confirm_snapshot_items
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'watchlist_confirm_snapshot_items is append-only (UPDATE blocked)';
END//

CREATE TRIGGER trg_wcsi_no_delete
BEFORE DELETE ON watchlist_confirm_snapshot_items
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'watchlist_confirm_snapshot_items is append-only (DELETE blocked)';
END//

DELIMITER ;
```

---

## B. Cara Mengambil Data dari Ajaib (LOCKED)

### B1) Sumber field (mapping)
- `last_price` diambil dari **Close/Last** pada panel Order Book.
- `volume_shares` diambil dari **Volume** pada panel Order Book (format M/B/T).
- Panel “Order Book” dipakai hanya untuk membaca **angka aggregate di bagian atas**; ladder bid/ask di bawahnya **bukan** input CONFIRM.
- `turnover_idr` diambil dari **Detail Price → Turnover** (NOMINAL). 
  **DILARANG** memakai `Turnover %` sebagai `turnover_idr`.

### B2) Normalisasi angka (LOCKED)
- Harga: `4.610` → `4610` (hapus pemisah ribuan `.`)
- Volume: `39,33 M` → `39.33 × 1_000_000` → `39330000`
- Turnover: `143,96 B` → `143.96 × 1_000_000_000` → `143960000000`
- Suffix: `K/M/B/T` wajib didukung. Koma `,` adalah desimal.

**LOCKED parser rules:**
- Hapus spasi, ganti `,` → `.` untuk parsing desimal.
- Jika ada suffix:
  - `K`×1_000, `M`×1_000_000, `B`×1_000_000_000, `T`×1_000_000_000_000
- Hasil akhir **wajib integer** dengan aturan pembulatan: `ROUND_HALF_UP` (LOCKED).
- Reject input (INVALID) jika:
  - ada karakter selain digit + `.` + `,` + suffix (`K/M/B/T`)
  - nilai negatif / kosong
  - format campur yang tidak valid (mis. `39.33 M` atau `39,33,1`)

---

## C. Contoh Data (REALISTIC)

### C1) Insert header
```sql
INSERT INTO watchlist_confirm_snapshots
(policy_code, trade_date, captured_at, source, note)
VALUES
('WS', '2026-03-01', '2026-03-01 10:05:00', 'manual', 'intraday aggregate manual input (Ajaib)');
```

Misal menghasilkan `snapshot_id = 1001`.

### C2) Insert item (contoh ANTM)
```sql
INSERT INTO watchlist_confirm_snapshot_items (
  snapshot_id, ticker_code,
  last_price, chg_pct, volume_shares, turnover_idr
) VALUES (
  1001, 'ANTM',
  4610, 0.0000, 39330000, 143960000000
);
```

---

## D. Query Snapshot Terbaru + Validasi TTL (reference)

Ambil snapshot terbaru untuk policy + trade_date:
```sql
SELECT *
FROM watchlist_confirm_snapshots
WHERE policy_code='WS' AND trade_date = :trade_date
ORDER BY captured_at DESC, snapshot_id DESC
LIMIT 1;
```

Cek valid (TTL 15 menit) dengan `effective_captured_at`:
```sql
SELECT
  snapshot_id,
  policy_code,
  trade_date,
  captured_at,
  inserted_at,
  LEAST(captured_at, inserted_at) AS effective_captured_at,
  TIMESTAMPDIFF(SECOND, LEAST(captured_at, inserted_at), NOW()) AS age_sec,
  CASE WHEN TIMESTAMPDIFF(SECOND, LEAST(captured_at, inserted_at), NOW()) <= 900
       THEN 1 ELSE 0 END AS is_valid
FROM watchlist_confirm_snapshots
WHERE snapshot_id = :snapshot_id;
```

## Next
### Weekly Swing
- 12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md