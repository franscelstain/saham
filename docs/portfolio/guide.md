# PORTFOLIO_ACCOUNTS.md — Input Data Guide (Ajaib → TradeAxis)

Dokumen ini menjelaskan **tabel apa saja yang perlu kamu isi** (manual/import), **kolom apa saja yang diisi**, dan **contoh mapping** dari data yang bisa kamu lihat di Ajaib (seperti screenshot “Rincian Order”).

Tujuan utamanya: kamu bisa input transaksi dan akun dengan benar, lalu sistem TradeAxis otomatis membentuk:
- lots FIFO
- matching SELL
- posisi (`portfolio_positions`)
- realized/unrealized PnL
- event log

---

## 1) Konsep kunci: `account_id` bukan SID

- **`account_id`** adalah ID internal aplikasi (angka 1,2,3,…).
- **SID/RDN** adalah identitas eksternal dan disimpan sebagai kolom di master account.

Kalau kamu cuma punya satu akun trading, aman pakai:
- `portfolio_accounts.id = 1`
- semua transaksi input dengan `account_id = 1`

---

## 2) Tabel yang kamu isi (manual/import)

### A) Wajib: `portfolio_accounts` (sekali saja per akun)

**Kapan diisi:** saat setup awal akun di aplikasi.

**Kolom minimum yang kamu isi:**
- `id` (opsional jika auto-increment; kalau seed manual, bisa set `1`)
- `user_id` (kalau single-user, isi `1`)
- `account_name` (mis. `Ajaib - Utama`)
- `broker_code` (mis. `AJAIB`)
- `currency` (default `IDR`)
- `sid` (optional)
- `rdn` (optional)
- `default_strategy_code` (optional, mis. `weekly_swing`)
- `is_active` (true)

**Contoh data:**
```json
{
  "id": 1,
  "user_id": 1,
  "account_name": "Ajaib - Utama",
  "broker_code": "AJAIB",
  "currency": "IDR",
  "sid": null,
  "rdn": null,
  "default_strategy_code": "weekly_swing",
  "is_active": true
}
```

> Setelah ini, semua transaksi dari Ajaib pakai `account_id = 1`.

---

### B) Wajib: `portfolio_trades` (setiap transaksi BUY/SELL)

**Kapan diisi:** setiap kali kamu buy/sell (manual input atau import).

**Kolom yang kamu isi dari Ajaib (minimal wajib):**
- `account_id`
- `symbol` *(atau map ke `ticker_id` jika UI kamu langsung pakai ticker_id)*
- `trade_date` *(di Ajaib ada “Waktu”, ambil tanggalnya)*
- `side` *(Beli/Jual → BUY/SELL)*
- `qty`
- `price`
- `external_ref` *(IDX Order ID, sangat disarankan untuk anti dobel)*

**Kolom yang sangat disarankan kamu isi (buat PnL net akurat):**
- `gross_amount` *(Nominal Order)*
- `fee_amount` *(Biaya Transaksi)*
- `tax_amount` *(kalau ada; kalau tidak ada, isi 0)*
- `net_amount` *(Total Transaksi → definisi beda untuk BUY vs SELL, lihat bagian 4)*

**Kenapa `net_amount` penting:**
- BUY: cash out sebenarnya = gross + fee (+tax)
- SELL: cash in sebenarnya = gross - fee (-tax)

Kalau kamu hanya pakai PnL versi Ajaib yang biasanya “gross P&L”, angka bisa beda dengan “uang beneran”.

---

### C) Opsional: `portfolio_plans` (kalau mau strategi/plan dicatat)

**Kapan diisi:** kalau kamu mau menyimpan “rencana entry/SL/TP/timebox” (manual atau output watchlist engine).

**Kalau kamu pakai watchlist engine:** ini bisa otomatis (tidak perlu manual).

---

## 3) Tabel yang TIDAK kamu isi (otomatis oleh sistem)

Setelah `portfolio_trades` masuk, sistem otomatis membentuk:
- `portfolio_lots` (lot BUY FIFO)
- `portfolio_lot_matches` (matching SELL FIFO)
- `portfolio_positions` (posisi cache)
- `portfolio_position_events` (audit trail)

Kamu tidak perlu input manual ke tabel-tabel ini.

---

## 4) Mapping detail dari Ajaib (“Rincian Order”)

Ajaib biasanya menampilkan:
- IDX Order ID
- Waktu
- Harga
- Lot
- Nominal Order
- Biaya Transaksi
- Total Transaksi

### Konversi penting: `qty` = lot × 100

Di IDX: **1 lot = 100 saham**.

Jadi:
- `qty` (shares) = `lot * 100`

**Contoh:**
- Lot = 11 → `qty = 1100`

> Ini wajib konsisten, karena FIFO dan PnL dihitung dalam unit shares.

---

### Mapping field Ajaib → `portfolio_trades`

| Ajaib | `portfolio_trades` | Catatan |
|---|---|---|
| IDX Order ID | `external_ref` | Kunci idempotency (hindari dobel input) |
| Waktu | `trade_date` | ambil bagian tanggal (YYYY-MM-DD) |
| Harga | `price` | per share |
| Lot | (turunan) | `qty = lot * 100` |
| Nominal Order | `gross_amount` | biasanya = `qty * price` |
| Biaya Transaksi | `fee_amount` | biaya broker |
| Total Transaksi | `net_amount` | BUY: out; SELL: in (lihat definisi di bawah) |
| (Beli/Jual) | `side` | `BUY` / `SELL` |
| Ticker | `symbol` | mis. `ITMA` |

---

### Definisi `net_amount` yang dipakai TradeAxis

Agar “uang beneran” konsisten:

- Untuk **BUY**:  
  `net_amount = gross_amount + fee_amount + tax_amount`  
  (uang keluar)

- Untuk **SELL**:  
  `net_amount = gross_amount - fee_amount - tax_amount`  
  (uang masuk)

Kalau Ajaib tidak menampilkan `tax_amount`, isi `0` dan anggap sudah include fee.

---

## 5) Contoh input nyata (berdasarkan screenshot ITMA)

### BUY (ITMA Beli)

Ajaib menampilkan:
- IDX Order ID: 202601140000635336
- Waktu: 14 Jan 2026 08:51
- Harga: 2.000
- Lot: 11
- Nominal Order: 2.200.000
- Biaya Transaksi: 3.329
- Total Transaksi: 2.203.329

Konversi:
- `qty = 11 * 100 = 1100`

Row `portfolio_trades`:
```json
{
  "account_id": 1,
  "symbol": "ITMA",
  "trade_date": "2026-01-14",
  "side": "BUY",
  "qty": 1100,
  "price": 2000,
  "gross_amount": 2200000,
  "fee_amount": 3329,
  "tax_amount": 0,
  "net_amount": 2203329,
  "external_ref": "202601140000635336",
  "source": "manual"
}
```

---

### SELL (ITMA Jual)

Ajaib menampilkan:
- IDX Order ID: 202601140001802794
- Waktu: 14 Jan 2026 09:16
- Harga: 1.900
- Lot: 11
- Nominal Order: 2.090.000
- Biaya Transaksi: 5.252
- Total Transaksi: 2.084.748

Konversi:
- `qty = 11 * 100 = 1100`

Row `portfolio_trades`:
```json
{
  "account_id": 1,
  "symbol": "ITMA",
  "trade_date": "2026-01-14",
  "side": "SELL",
  "qty": 1100,
  "price": 1900,
  "gross_amount": 2090000,
  "fee_amount": 5252,
  "tax_amount": 0,
  "net_amount": 2084748,
  "external_ref": "202601140001802794",
  "source": "manual"
}
```

---

## 6) Efek otomatis setelah input transaksi

Setelah BUY masuk:
- Sistem membuat 1 row di `portfolio_lots`:
  - `qty=1100`, `remaining_qty=1100`
  - `unit_cost = net_amount / qty = 2203329 / 1100`

Setelah SELL masuk:
- Sistem membuat row `portfolio_lot_matches` untuk match FIFO:
  - `matched_qty=1100`
  - `sell_unit_price = net_amount_sell / qty_sell = 2084748 / 1100`
  - `realized_pnl = matched_qty * (sell_unit_price - buy_unit_cost)` (fee-aware)

Lalu sistem update `portfolio_positions`:
- `qty` turun jadi 0
- `is_open=false`, `state=CLOSED`
- `realized_pnl` bertambah sesuai hasil match

---

## 7) Ringkasan: yang kamu isi vs yang sistem isi

**Kamu isi:**
- `portfolio_accounts` (sekali per akun)
- `portfolio_trades` (setiap transaksi)
- `portfolio_plans` (opsional untuk strategi)

**Sistem isi otomatis:**
- `portfolio_lots`
- `portfolio_lot_matches`
- `portfolio_positions`
- `portfolio_position_events`

---

## 8) Catatan penting soal PnL Ajaib

Ajaib sering menampilkan “Realized P&L” yang terlihat seperti **selisih harga × qty** (gross), belum tentu mengurangi fee.

TradeAxis (fee-aware) akan menghitung:
- `PnL_net = net_sell - net_buy`

Kalau kamu ingin tampilkan angka yang sama seperti Ajaib, kamu bisa simpan “gross_pnl” terpisah (mis. di meta_json) atau hitung di UI:
- `PnL_gross = (sell_price - buy_price) * qty`
