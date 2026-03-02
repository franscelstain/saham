# 11 — Tabel Input Manual Intraday Snapshot (CONFIRM) — Weekly Swing

Dokumen ini mengunci **struktur tabel**, **kolom wajib**, dan **contoh data** untuk input manual intraday snapshot yang dipakai oleh CONFIRM (Weekly Swing).

## Prinsip (LOCKED)

1) **CONFIRM bukan real-time.** CONFIRM selalu menjawab berdasarkan **snapshot** yang terakhir diinput.

2) Snapshot yang diinput manual adalah **sumber kebenaran CONFIRM**. Jika data diambil di masa lalu namun baru diinput sekarang, isi datanya **tidak diperdebatkan**. Yang dinilai hanya **validitas usia snapshot**.

3) Snapshot wajib punya **dua timestamp**:
- `captured_at` = waktu data order book **diambil** (diisi manual, dari jam perangkat saat mengambil data).
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
  policy_code       VARCHAR(8)  NOT NULL,
  trade_date        DATE        NOT NULL,
  captured_at       DATETIME    NOT NULL,
  inserted_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source            VARCHAR(32) NOT NULL DEFAULT 'manual',
  note              VARCHAR(255) NULL,
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
- `last_price`
- `bid1_price`, `bid1_lots`
- `ask1_price`, `ask1_lots`
- `bid_lots_sum_5`, `ask_lots_sum_5`
- `bid_lots_sum_10`, `ask_lots_sum_10`
- `spread`, `spread_pct`
- `imbalance_5`, `imbalance_10`
- `orderbook_json` (boleh `{}` jika tidak menyimpan ladder)

DDL:
```sql
CREATE TABLE IF NOT EXISTS watchlist_confirm_snapshot_items (
  item_id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_id       BIGINT UNSIGNED NOT NULL,

  ticker_code       VARCHAR(16) NOT NULL,
  ticker_id         BIGINT UNSIGNED NULL,

  last_price        INT UNSIGNED NOT NULL,
  bid1_price        INT UNSIGNED NOT NULL,
  bid1_lots         INT UNSIGNED NOT NULL,
  ask1_price        INT UNSIGNED NOT NULL,
  ask1_lots         INT UNSIGNED NOT NULL,

  bid_lots_sum_5    INT UNSIGNED NOT NULL,
  ask_lots_sum_5    INT UNSIGNED NOT NULL,
  bid_lots_sum_10   INT UNSIGNED NOT NULL,
  ask_lots_sum_10   INT UNSIGNED NOT NULL,

  spread            INT UNSIGNED NOT NULL,
  spread_pct        DECIMAL(10,6) NOT NULL,
  imbalance_5       DECIMAL(10,6) NOT NULL,
  imbalance_10      DECIMAL(10,6) NOT NULL,

  orderbook_json    JSON NOT NULL,
  item_hash         CHAR(64) NULL,

  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (item_id),
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

## B. Cara Mengambil Data dari Order Book (LOCKED)

### B1) Normalisasi angka dari UI
UI sering menampilkan pemisah ribuan dengan titik.

- Harga: `4.350` → `4350`
- Lot: `1.012` → `1012`

**LOCKED:** semua kolom price dan lots di DB disimpan sebagai integer hasil normalisasi.

### B2) Field mapping (per ticker)
Ambil dari order book:
- `last_price` = harga berjalan
- `bid1_*` = level bid teratas
- `ask1_*` = level ask teratas
- `bid_lots_sum_5` = total lot bid level 1..5
- `ask_lots_sum_5` = total lot ask level 1..5
- `bid_lots_sum_10` = total lot bid level 1..10
- `ask_lots_sum_10` = total lot ask level 1..10

Turunan (wajib dihitung sebelum insert item):
- `spread = ask1_price - bid1_price`
- `spread_pct = spread / last_price`
- `imbalance_n = (bid_sum_n - ask_sum_n) / (bid_sum_n + ask_sum_n)` untuk n=5 dan n=10

`orderbook_json`:
- boleh isi `{}` jika tidak menyimpan ladder
- jika menyimpan, format minimal:
```json
{"bid":[{"p":4350,"l":1012},...],"ask":[{"p":4360,"l":6649},...]}
```

---

## C. Contoh Data (REALISTIC)

### C1) Insert header
```sql
INSERT INTO watchlist_confirm_snapshots
(policy_code, trade_date, captured_at, source, note)
VALUES
('WS', '2026-03-01', '2026-03-01 10:05:00', 'manual', 'orderbook manual input');
```

Misal menghasilkan `snapshot_id = 1001`.

### C2) Insert item (contoh ANTM)
```sql
INSERT INTO watchlist_confirm_snapshot_items (
  snapshot_id, ticker_code,
  last_price, bid1_price, bid1_lots, ask1_price, ask1_lots,
  bid_lots_sum_5, ask_lots_sum_5, bid_lots_sum_10, ask_lots_sum_10,
  spread, spread_pct, imbalance_5, imbalance_10,
  orderbook_json
) VALUES (
  1001, 'ANTM',
  4350, 4350, 1012, 4360, 6649,
  28422, 101080, 60176, 132045,
  10, 0.002299, -0.560000, -0.374000,
  '{}'
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