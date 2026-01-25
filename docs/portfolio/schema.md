# Portfolio Schema (TradeAxis) — Detailed Column Semantics

Dokumen ini menjelaskan schema **Portfolio** yang dipakai TradeAxis: **5 tabel utama** untuk transaksi/lot/matching/audit/plan, serta tabel **`portfolio_positions`** sebagai cache posisi terkini.

Fokus dokumen ini bukan hanya “arti kolom”, tapi juga:
- **Sumber data** (siapa yang mengisi)
- **Aturan hitung/derivasi**
- **Invariants** (harus selalu benar)
- **Kapan di-update** (ingest vs valuasi EOD)
- **Kegunaan praktis** (UI/report/debug)

---

## Prinsip desain

- **Source of truth transaksi**: `portfolio_trades` (BUY/SELL fills).
- **FIFO cost basis**: `portfolio_lots` (BUY lots) + `portfolio_lot_matches` (SELL matching).
- **Cache posisi cepat**: `portfolio_positions` (turunan dari trades/lots; untuk dashboard/query cepat).
- **Audit trail**: `portfolio_position_events` (jejak perubahan + alasan).
- **Plan/intent strategi**: `portfolio_plans` (snapshot rekomendasi/aturan saat akan dieksekusi).

---

# 1) `portfolio_trades`

**Fungsi:** menyimpan setiap **fill transaksi** (BUY/SELL) per akun & ticker, lengkap dengan nilai gross/fee/tax/net serta idempotency key.

**Invariants:**
- Satu transaksi “yang sama” **tidak boleh dobel** (dikunci dengan `external_ref` atau `trade_hash`).
- `qty > 0`, `price >= 0`.
- `side ∈ {BUY, SELL}`.

### Kolom (dengan detail operasional)

- `id` (bigint, PK)  
  **Kegunaan:** internal reference untuk relasi lot/match.  
  **Diisi oleh:** DB auto-increment.

- `account_id` (bigint, index)  
  **Kegunaan:** memisahkan portofolio per akun. Semua query posisi & PnL selalu scoped ke akun.  
  **Diisi oleh:** ingestion (manual/import/broker adapter).  
  **Invariant:** tidak null.

- `ticker_id` (bigint, index)  
  **Kegunaan:** relasi master ticker. Jadi join utama untuk harga EOD dan metadata.  
  **Diisi oleh:** ingestion (mapping symbol → ticker_id).  
  **Invariant:** tidak null.

- `symbol` (varchar(16), nullable, index)  
  **Kegunaan:** display/debug (mis. saat mapping ticker_id gagal tetap ada string).  
  **Diisi oleh:** ingestion.  
  **Catatan:** jangan dijadikan canonical key; canonical tetap `ticker_id`.

- `trade_date` (date, index)  
  **Kegunaan:** pengelompokan histori dan pembentukan posisi harian (TradeAxis pakai tanggal ini sebagai basis).  
  **Diisi oleh:** ingestion.  
  **Catatan:** kalau ke depan ingin T+2 settlement logic, kolom ini tetap dipakai sebagai trade date, bukan settlement date.

- `side` (varchar(8), index)  
  **Kegunaan:** menentukan alur: BUY → buat lot, SELL → match FIFO.  
  **Diisi oleh:** ingestion.  
  **Invariant:** hanya BUY/SELL.

- `qty` (int)  
  **Kegunaan:** shares. Ini unit dasar semua perhitungan.  
  **Diisi oleh:** ingestion.  
  **Invariant:** `qty > 0`.

- `price` (double/decimal 18,4)  
  **Kegunaan:** harga per share (gross price).  
  **Diisi oleh:** ingestion.  
  **Catatan:** profit/loss seharusnya **fee-aware**, jadi `price` saja tidak cukup untuk PnL.

- `gross_amount` (double/decimal 18,4, nullable)  
  **Kegunaan:** rekonsiliasi: biasanya `qty * price`.  
  **Diisi oleh:** ingestion (boleh dihitung bila sumber tidak menyediakan).  
  **Invariant (jika diisi):** mendekati `qty * price` (toleransi rounding).

- `fee_amount` (double/decimal 18,4, nullable)  
  **Kegunaan:** memodelkan biaya broker yang mempengaruhi cost basis & proceeds.  
  **Diisi oleh:** ingestion atau fee model internal.  
  **Catatan:** jika kamu pakai fee model terpusat, field ini bisa diisi computed agar traceable.

- `tax_amount` (double/decimal 18,4, nullable)  
  **Kegunaan:** pajak transaksi (umumnya pada sell).  
  **Diisi oleh:** ingestion atau fee model internal.

- `net_amount` (double/decimal 18,4, nullable)  
  **Kegunaan:** **angka canonical** untuk cashflow & fee-aware basis:  
  - BUY: `gross + fee + tax` (uang keluar)  
  - SELL: `gross - fee - tax` (uang masuk)  
  **Diisi oleh:** ingestion atau dihitung dari gross/fee/tax.  
  **Invariant (jika diisi):** konsisten dengan gross/fee/tax.

- `external_ref` (varchar(64), nullable)  
  **Kegunaan:** idempotency dari broker/import (satu transaksi = satu external_ref).  
  **Diisi oleh:** adapter broker/importer.  
  **Invariant:** unik per `(account_id, external_ref)`.

- `trade_hash` (varchar(64), nullable)  
  **Kegunaan:** idempotency fallback kalau `external_ref` kosong; biasanya hash dari (account,ticker,date,side,qty,price,net).  
  **Diisi oleh:** ingestion.  
  **Invariant:** unik per `(account_id, trade_hash)`.

- `broker_ref` (varchar(64), nullable)  
  **Kegunaan:** referensi tambahan untuk trace (tidak dipakai untuk uniqueness).

- `source` (varchar(32))  
  **Kegunaan:** audit “data ini masuk dari mana” (`manual`, `import`, dll).  
  **Diisi oleh:** ingestion.  
  **Rekomendasi:** gunakan enum/konvensi yang konsisten.

- `currency` (varchar(16), nullable)  
  **Kegunaan:** multi-currency readiness (IDX umumnya `IDR`).  
  **Diisi oleh:** ingestion.

- `meta_json` (json, nullable)  
  **Kegunaan:** tempat aman untuk raw payload / field tambahan tanpa ubah schema.

- `created_at`, `updated_at` (timestamp)  
  **Kegunaan:** audit waktu ingest/update.

---

# 2) `portfolio_lots`

**Fungsi:** menyimpan **lot BUY** untuk FIFO. Satu BUY menghasilkan satu lot, lalu SELL mengurangi `remaining_qty` lot-lot paling tua dulu.

**Invariants:**
- `remaining_qty` selalu `0..qty`.
- `unit_cost` dan `total_cost` konsisten (toleransi rounding).

### Kolom

- `id` (bigint, PK)  
  Reference lot untuk matching.

- `account_id` (bigint, index)  
  Scope owner.

- `ticker_id` (bigint, index)

- `buy_trade_id` (bigint, index)  
  **Kegunaan:** traceability: lot ini berasal dari trade BUY mana.  
  **Invariant:** satu BUY → satu lot.

- `buy_date` (date, index)  
  **Kegunaan:** FIFO ordering (lebih cepat daripada join `portfolio_trades` saat matching).

- `qty` (int)  
  **Kegunaan:** qty awal lot.

- `remaining_qty` (int)  
  **Kegunaan:** sisa qty yang belum terjual.  
  **Di-update:** saat SELL match, dikurangi sesuai `matched_qty`.  
  **Invariant:** tidak boleh negatif.

- `unit_cost` (double/decimal 18,6)  
  **Kegunaan:** cost basis per share **fee-aware**.  
  **Formula umum:** `unit_cost = net_amount_buy / qty`.  
  **Kenapa 6 desimal:** pembagian fee sering menghasilkan pecahan, dan ini mengurangi drift saat matching parsial.

- `total_cost` (double/decimal 18,4)  
  **Kegunaan:** total biaya lot.  
  **Formula umum:** `total_cost = unit_cost * qty` (atau langsung `net_amount_buy` sebagai canonical).

- `created_at`, `updated_at` (timestamp)

---

# 3) `portfolio_lot_matches`

**Fungsi:** menyimpan hasil **matching SELL ke BUY lots** (FIFO). Satu SELL bisa match ke beberapa lot.

**Invariants:**
- Total `matched_qty` untuk satu `sell_trade_id` = `qty` SELL.
- Tidak ada match ke lot yang `remaining_qty == 0`.

### Kolom

- `id` (bigint, PK)

- `account_id` (bigint, index)

- `ticker_id` (bigint, index)

- `sell_trade_id` (bigint, index)  
  **Kegunaan:** mengelompokkan semua potongan match dari satu SELL.

- `buy_lot_id` (bigint, index)  
  **Kegunaan:** lot BUY yang diambil.

- `matched_qty` (int)  
  **Kegunaan:** qty yang diambil dari lot tersebut.  
  **Diisi oleh:** matching engine (FIFO).

- `buy_unit_cost` (double/decimal 18,6)  
  **Kegunaan:** snapshot cost basis untuk match ini (stabil walau lot berubah karena match berikutnya).

- `sell_unit_price` (double/decimal 18,6)  
  **Kegunaan:** proceeds per share yang fee-aware.  
  **Formula umum:** `sell_unit_price = net_amount_sell / qty_sell`.  
  **Catatan:** ini menghindari harus mengalokasikan fee per match secara rumit.

- `buy_fee_alloc` (double/decimal 18,6, nullable)  
  **Kegunaan:** opsional jika kamu memilih mengalokasikan fee buy secara eksplisit per match.  
  **Jika unit_cost sudah net:** field ini boleh null.

- `sell_fee_alloc` (double/decimal 18,6, nullable)  
  **Kegunaan:** opsional jika kamu memilih alokasi fee sell per match.  
  **Jika sell_unit_price sudah net:** field ini boleh null.

- `realized_pnl` (double/decimal 18,4)  
  **Kegunaan:** realized PnL untuk potongan ini.  
  **Formula baseline:** `matched_qty * (sell_unit_price - buy_unit_cost)`.

- `created_at`, `updated_at` (timestamp)

---

# 4) `portfolio_position_events`

**Fungsi:** audit trail perubahan posisi + alasan. Ini log untuk explainability/debug, bukan pengganti `portfolio_trades`.

**Kapan dibuat:**
- Saat ingest trade (BUY/SELL) → minimal event `FILL`.
- Saat posisi berubah state (ENTRY/ADD/REDUCE/CLOSED) → event tambahan.
- Saat auto-exit (TP/SL/TIMEBOX) → isi `reason_code` sesuai policy.

### Kolom

- `id` (bigint, PK)

- `account_id` (bigint, index)

- `ticker_id` (bigint, index)

- `strategy_code` (varchar(32), nullable, index)  
  **Kegunaan:** mengikat event ke strategi yang memicu tindakan.

- `plan_version` (varchar(16), nullable)  
  **Kegunaan:** trace perubahan aturan strategi.

- `as_of_trade_date` (date, nullable, index)  
  **Kegunaan:** konteks tanggal rekomendasi/scan.

- `event_type` (varchar(32), index)  
  **Kegunaan:** kategori event untuk filtering timeline (`ENTRY`, `ADD`, `REDUCE`, `CLOSED`, dll).

- `qty_before` (int, nullable) / `qty_after` (int, nullable)  
  **Kegunaan:** delta posisi tanpa harus compute ulang dari trades saat debug.

- `price` (double/decimal 18,4, nullable)  
  **Kegunaan:** referensi harga event (umumnya harga fill).

- `reason_code` (varchar(32), nullable)  
  **Kegunaan:** *why* — `TP`, `SL`, `TIMEBOX`, `MANUAL`, dll. Ini yang membuat explainability bisa ditampilkan.

- `notes` (varchar(255), nullable)  
  **Kegunaan:** catatan manusia (mis. “breakout gagal”).

- `payload_json` (json, nullable)  
  **Kegunaan:** tempat snapshot indikator/reason detail (mis. RSI, ATR, vol ratio) untuk audit.

- `created_at` (timestamp)  
  **Kegunaan:** ordering timeline.

---

# 5) `portfolio_plans`

**Fungsi:** menyimpan **plan/intent eksekusi** (snapshot watchlist/engine) untuk sebuah ticker pada tanggal tertentu. Plan ini bisa dilink ke posisi untuk explainability.

**Invariants yang disarankan:**
- Unik per `(account_id, ticker_id, strategy_code, as_of_trade_date, plan_version)`.
- Saat `status = OPENED`, snapshot sebaiknya dianggap immutable; perubahan dicatat lewat events.

### Kolom

- `id` (bigint, PK)

- `account_id` (bigint, index)

- `ticker_id` (bigint, index)

- `strategy_code` (varchar(32), index)  
  **Kegunaan:** policy code yang menghasilkan plan.

- `as_of_trade_date` (date, index)  
  **Kegunaan:** tanggal basis data (EOD) yang dipakai engine.

- `intent` (varchar(24), index)  
  **Kegunaan:** hasil keputusan high-level (`BUY`, `HOLD`, `AVOID`, `SELL`, dll).  
  **Dipakai oleh:** UI watchlist dan gating eksekusi.

- `alloc_pct` (double/decimal 8,4, nullable)  
  **Kegunaan:** porsi dana yang disarankan.  
  **Catatan:** ini input sizing, bukan angka final “lots”.

- `plan_snapshot_json` (json)  
  **Kegunaan:** snapshot lengkap agar explainability bisa selalu direplay walau engine berubah.

- `entry_json` (json, nullable)  
  **Kegunaan:** struktur entry (harga, trigger, ladder).

- `risk_json` (json, nullable)  
  **Kegunaan:** stop loss, risk per trade, sizing params.

- `take_profit_json` (json, nullable)  
  **Kegunaan:** target(s) TP, partial take, dll.

- `timebox_json` (json, nullable)  
  **Kegunaan:** expiry entry, max hold.

- `reason_codes_json` (json, nullable)  
  **Kegunaan:** daftar reason codes yang membuat plan terbentuk.

- `plan_version` (varchar(16), index)  
  **Kegunaan:** version pinning untuk reproducibility.

- `status` (varchar(16), index)  
  **Kegunaan:** state plan lifecycle: `PLANNED` → (opsional) `OPENED` → `EXPIRED/CANCELLED`.

- `entry_expiry_date` (date, nullable, index)  
  **Kegunaan:** batas waktu entry valid.

- `max_holding_days` (int, nullable)  
  **Kegunaan:** aturan timebox untuk auto-exit.

- `created_at`, `updated_at` (timestamp)

---

# 6) `portfolio_positions`

**Fungsi:** cache posisi **terkini** per akun + ticker (dan optional per strategi). Data ini dibentuk dari `portfolio_trades` + FIFO lots, lalu dipakai untuk dashboard, ringkasan posisi, dan query cepat.

**Invariants yang harus dijaga:**
- `qty` = total open shares berdasarkan lot FIFO (sum `remaining_qty` untuk ticker+account).
- `is_open` konsisten dengan `qty > 0` dan/atau `state = OPEN`.
- `avg_price` konsisten dengan cost basis open lots.
- `realized_pnl` = agregasi realized pnl dari semua sell match terkait posisi (scope akun+ticker, dan bila multi-strategy: scope strategy juga harus jelas).

### Kolom (dengan detail operasional)

- `id` (bigint, PK)

- `account_id` (bigint, index)  
  **Diisi:** saat posisi dibuat/di-upsert oleh service.  
  **Dipakai:** filter portfolio per user.

- `ticker_id` (bigint, index)  
  **Diisi:** saat posisi dibuat/di-upsert.

- `strategy_code` (varchar(32), nullable, index)  
  **Diisi:** saat posisi dikaitkan dengan plan/strategy; bisa null jika posisi “manual”.  
  **Dipakai:** memisahkan posisi per strategi (kalau 1 ticker bisa punya lebih dari 1 strategi).  
  **Catatan:** kalau kamu memutuskan 1 ticker = 1 posisi saja, field ini tetap berguna untuk explainability.

- `policy_code` (varchar(32), nullable)  
  **Tujuan:** legacy/alias policy yang sudah pernah dipakai.  
  **Rekomendasi:** pilih satu canonical (idealnya `strategy_code`) supaya tidak bikin data ganda.

- `state` (varchar(16), index)  
  **Diisi/di-update:** saat open/close posisi.  
  **Konvensi:** minimal `OPEN` / `CLOSED`.  
  **Dipakai:** UI status ringkas.

- `is_open` (boolean, index)  
  **Tujuan:** query cepat tanpa string compare (`where is_open = 1`).  
  **Invariant:** jika `qty == 0`, seharusnya `is_open = false` (kecuali ada konsep pending).

- `qty` (int)  
  **Di-update:**  
  - BUY: bertambah sebesar qty buy  
  - SELL: berkurang sebesar qty sell (setelah match sukses)  
  **Invariant:** tidak boleh negatif.

- `avg_price` (double/decimal 18,6, nullable)  
  **Definisi:** weighted average cost basis **untuk shares yang masih open** (fee-aware).  
  **Cara update yang benar:** recompute dari open lots:  
  `avg_price = (sum(remaining_qty * unit_cost) / sum(remaining_qty))`.  
  **Kenapa nullable:** saat qty=0, avg_price tidak relevan.

- `position_lots` (int, nullable)  
  **Definisi:** jumlah lot BUY yang masih punya `remaining_qty > 0`.  
  **Kegunaan:** UI ringkas (“posisi ini terdiri dari N lots”) + sanity check FIFO.

- `entry_date` (date, nullable, index)  
  **Definisi:** tanggal BUY pertama yang membuka posisi (ketika qty berubah dari 0 → >0).  
  **Kegunaan:** untuk timebox/hold duration.

- `realized_pnl` (double/decimal 18,4)  
  **Definisi:** akumulasi realized PnL dari semua SELL.  
  **Update rule:** tiap SELL → tambah `sum(realized_pnl)` dari rows `portfolio_lot_matches` untuk sell tersebut.

- `unrealized_pnl` (double/decimal 18,4, nullable)  
  **Definisi:** estimasi PnL jika posisi dilikuidasi pada harga valuasi terakhir.  
  **Formula:** `unrealized_pnl = qty * (last_price - avg_price)` (fee sell future tidak dimodelkan di sini kecuali kamu menambahkan).  
  **Update timing:** job valuasi EOD / refresh harga.

- `market_value` (double/decimal 18,4, nullable)  
  **Definisi:** `qty * last_price` pada valuasi terakhir.  
  **Update timing:** job valuasi EOD / refresh harga.

- `last_valued_date` (date, nullable, index)  
  **Definisi:** tanggal yang dipakai untuk `last_price` (umumnya close EOD canonical).  
  **Kegunaan:** UI tahu data valuasi ini “as of kapan”.

- `plan_id` (bigint, nullable, index)  
  **Definisi:** link ke `portfolio_plans.id` yang melahirkan/menjadi basis posisi.  
  **Diisi:** saat entry/open position dari plan.  
  **Kegunaan:** drill-down explainability.

- `plan_snapshot_json` (json, nullable)  
  **Definisi:** salinan snapshot plan yang ditempel ke posisi untuk performa UI (tanpa join).  
  **Update rule:** idealnya hanya diisi saat entry; perubahan plan setelah open dicatat via events (bukan overwrite).

- `as_of_trade_date` (date, nullable, index)  
  **Definisi:** tanggal basis data yang melekat pada plan/posisi (selaras dengan `portfolio_plans.as_of_trade_date`).  
  **Kegunaan:** trace bahwa posisi ini berasal dari rekomendasi tanggal X.

- `plan_version` (varchar(16), nullable)  
  **Definisi:** versi strategi pada saat posisi dibuka.

- `created_at`, `updated_at` (timestamp)  
  **Kegunaan:** audit dan debugging.

---

## Relasi ringkas

- `portfolio_trades` (BUY) → `portfolio_lots.buy_trade_id`  
- `portfolio_trades` (SELL) → `portfolio_lot_matches.sell_trade_id`  
- `portfolio_lots.id` → `portfolio_lot_matches.buy_lot_id`  
- `portfolio_plans.id` → `portfolio_positions.plan_id` (opsional)  
- `portfolio_position_events` menggunakan `(account_id, ticker_id, ...)` sebagai konteks log

---

## Alur pengisian data

- **Plan** dibuat dari output watchlist/engine → `portfolio_plans`.
- Saat **BUY** di-ingest → tulis `portfolio_trades`, buat `portfolio_lots`, upsert `portfolio_positions`, tulis `portfolio_position_events`.
- Saat **SELL** di-ingest → tulis `portfolio_trades`, FIFO match ke `portfolio_lots` → `portfolio_lot_matches`, update `portfolio_positions` (qty/avg/realized), tulis `portfolio_position_events`.
- Saat **EOD valuation** → update `portfolio_positions.market_value` & `unrealized_pnl` berdasarkan canonical close, dan set `last_valued_date`.

