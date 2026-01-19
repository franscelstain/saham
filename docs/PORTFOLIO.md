# PORTFOLIO.md (TradeAxis) — build_id: v2.2.52+portfolio_policy

Dokumen ini mendefinisikan **aturan Portfolio** yang **kompatibel** dengan:
- `SRP_Performa.md` (aturan tertinggi: SRP, performa, logging, deterministik)
- `MARKET_DATA.md` (CANONICAL + cutoff/effective date + market calendar)
- `compute_eod.md` (feature/indikator deterministik berbasis trading days)
- `WATCHLIST.md` (Strategy Policies + Trade Plan output)

Tujuan: Portfolio **bukan tempat “menebak” ulang sinyal**, tapi **mengeksekusi & mengelola posisi** secara konsisten agar hasil cuan bisa dievaluasi **per strategi** dan akurat.

---

## 0) Prinsip inti (wajib)
1) **Source of truth = transaksi eksekusi (fills).** Posisi & P/L adalah turunan.
2) **Strategy Policy harus end-to-end:** Watchlist menetapkan strategi & plan, Portfolio mengeksekusi lifecycle sesuai strategi itu.
3) **Plan harus disnapshot saat entry.** Portfolio tidak menghitung ulang plan dari indikator saat posisi sudah dibuka.
4) **Deterministik:** input yang sama (trades + canonical close) → output portfolio yang sama.
5) **Idempotent:** ingest trade yang sama tidak boleh menggandakan posisi/PnL.
6) **SRP:** compute/indikator tetap di compute-eod; portfolio fokus lifecycle, lot matching, risk/exit, audit.

---

## 1) Vocabulary
- **Strategy Policy (Portfolio):** paket aturan eksekusi & manajemen posisi (entry validity, risk, exit, timebox, re-entry).
- **Plan Snapshot:** JSON/fields yang disimpan saat membuka posisi (entry/SL/TP/timebox/alloc/reasons/as_of_date).
- **Lifecycle State:** status posisi dari PLANNED → OPEN → MANAGED → CLOSING → CLOSED (plus CANCELLED/EXPIRED).
- **Lot Matching:** metode menghitung realized P/L (default: FIFO).

---

## 2) Kontrak input dari Watchlist (wajib ada)
Watchlist harus mengeluarkan **“Execution Intent”** yang disimpan Portfolio sebagai plan snapshot.

### 2.1 Field wajib (minimum contract)
- `account_id`
- `ticker_id` (atau `symbol` yang dapat dipetakan)
- `strategy_code` (lihat Section 4)
- `as_of_trade_date` (effective trade date yang dipakai watchlist/compute-eod)
- `intent` (BUY_0/BUY_1/BUY_2_SPLIT/NO_TRADE/CARRY_ONLY)
- `alloc_pct` (0–100)
- `entry` (zone/trigger)
- `risk` (SL hard + rule BE/trailing bila ada)
- `take_profit` (TP1/TP2 + partial rule bila ada)
- `timebox` (expiry entry + max holding)
- `reason_codes` (array singkat)
- `plan_version`

### 2.2 Aturan plan snapshot
- Plan snapshot **wajib immutable** untuk posisi yang sudah OPEN.
- Update plan hanya boleh via **event** (lihat Section 8) dan harus punya alasan + jejak (audit).

---

## 3) Source of truth: transaksi & ledger (wajib)
Portfolio **wajib** punya ledger transaksi (fills) yang lengkap; derived tables boleh ada untuk performa UI.

### 3.1 Tabel minimum (konseptual)
- `portfolio_trades` (fills, source of truth)
- `portfolio_lots` (lot dari BUY, remaining_qty)
- `portfolio_lot_matches` (mapping SELL → BUY lots untuk realized P/L)
- `portfolio_positions` (derived posisi cepat untuk UI, per ticker per account)
- `portfolio_position_events` (audit lifecycle & perubahan plan)

> Catatan: Cash ledger & daily snapshot boleh ditambah, tapi bukan blocker untuk “posisi beli→jual yang akurat”.

### 3.2 Aturan idempotensi ingest trades
- Unique key disarankan: `(account_id, external_ref)` atau `(account_id, ticker_id, trade_date, side, qty, price, broker_ref)`.
- Jika trade duplikat masuk → harus **update/skip** tanpa menggandakan lot, match, atau posisi.

---

## 4) Strategy Policies resmi (Portfolio)
Kode strategi harus sama dengan WATCHLIST.md.

### 4.1 Wajib ada minimal ini
1) `WEEKLY_SWING`
2) `DIVIDEND_SWING`
3) `INTRADAY_LIGHT` (boleh DISABLE kalau data intraday belum ada)
4) `POSITION_TRADE`
5) `NO_TRADE` (bukan posisi; hasil evaluasi)

### 4.2 Policy bukan indikator
Policy portfolio **tidak menghitung sinyal**. Policy hanya:
- validasi entry terhadap plan snapshot,
- risk control (SL/BE/trailing/time exit),
- exit rules dan re-entry cooldown,
- audit event.

---

## 5) Lifecycle state machine (posisi)
### 5.1 Status
- `PLANNED` : plan ada, belum ada fill
- `OPEN` : entry fill pertama sudah terjadi (qty>0)
- `MANAGED` : setelah OPEN, policy mengelola SL/TP/timebox
- `CLOSING` : sedang proses exit (partial/full)
- `CLOSED` : qty=0 dan semua lot tertutup
- `CANCELLED` : entry tidak terjadi dan plan dibatalkan
- `EXPIRED` : entry tidak terjadi sampai expiry

### 5.2 Transisi (aturan ringkas)
- PLANNED → OPEN: saat BUY fill masuk & valid terhadap plan
- PLANNED → EXPIRED: lewat `entry_expiry_date` tanpa fill
- OPEN/MANAGED → CLOSING: exit triggered (TP/SL/time)
- CLOSING → CLOSED: qty=0
- PLANNED → CANCELLED: user/engine cancel (harus event)

---

## 6) Lot matching & P/L (wajib akurat)
### 6.1 Metode default: FIFO
- SELL selalu mengurangi lot BUY terlama yang masih OPEN.
- `realized_pnl` dihitung per match:
  - `sell_net_proceeds - buy_unit_cost * matched_qty`
  - fee/tax wajib dialokasikan agar basis cost akurat.

### 6.2 Partial sell
- Boleh kapan saja sesuai policy.
- Setelah partial sell:
  - lot remaining_qty berkurang,
  - posisi qty berkurang,
  - realized P/L bertambah,
  - plan snapshot tetap sama (kecuali policy mengubah SL/TP via event).

### 6.3 Aturan konsistensi
- `positions.qty` harus = sum `lots.remaining_qty` untuk `(account_id,ticker_id)`.
- Jika mismatch → error + audit event `INCONSISTENT_STATE` + job harus stop untuk akun itu (fail-fast).

---

## 7) Risk management (global rules)
Ini aturan yang **berlaku untuk semua strategi** kecuali policy menyebut override secara eksplisit.

1) **Hard SL wajib ada** di plan snapshot untuk posisi yang boleh dibuka.
2) **Max exposure cap** per ticker & per strategi (konfigurasi), contoh:
   - weekly swing max 50% equity total dalam 1–3 posisi
   - intraday max 20% per posisi (kalau enabled)
3) **Gap risk rule**:
   - jika open price melewati SL dengan gap besar → exit “damage control” (policy event).
4) **Cooldown re-entry** (default):
   - setelah exit by SL → cooldown minimal N trading days (konfig per strategy).
5) **No averaging down** kecuali strategy/policy mengizinkan secara eksplisit di plan snapshot (default: dilarang).

---

## 8) Event log (audit wajib)
Semua hal penting harus tercatat di `portfolio_position_events`.

### 8.1 Event type minimal
- `PLAN_CREATED`
- `PLAN_EXPIRED`
- `PLAN_CANCELLED`
- `ENTRY_FILLED`
- `ADD_FILLED` (jika nambah posisi sesuai plan)
- `TP1_TAKEN`
- `TP2_TAKEN`
- `EXIT_SL`
- `EXIT_TIME`
- `EXIT_MANUAL`
- `SL_MOVED`
- `BE_ARMED`
- `POLICY_BREACH`
- `INCONSISTENT_STATE`

### 8.2 Payload minimal
- `account_id, ticker_id, strategy_code, plan_version`
- `as_of_trade_date`
- `qty_before/after`
- `price`
- `reason_code` (singkat, 1–3 token)
- `notes` (optional)

---

## 9) Harga & kalender (valuasi yang benar)
Portfolio untuk valuasi (unrealized/market value) **wajib** memakai harga CANONICAL dan market calendar.
Rujukan: `MARKET_DATA.md` (kontrak downstream + effective date) dan `SRP_Performa.md` (aturan global).

### 9.1 Aturan valuasi
- Valuasi harian (EOD) pakai `close` CANONICAL pada **effective trade date**.
- Intraday valuation (jika ada) harus jelas sumbernya dan dipisah dari EOD (optional).

### 9.2 Kapan hitung unrealized
- Setelah publish-eod selesai untuk effective date.
- Jika canonical “held” (coverage rendah) → portfolio valuation untuk tanggal itu harus:
  - tetap pakai last known canonical (stale flag), atau
  - skip update (pilih satu, dan log).

---

## 10) Logging (per-domain, wajib)
- Semua log portfolio masuk `storage/logs/portfolio.log`.
- Domain compute/classifier **tidak boleh** log; logging hanya di orchestrator/command/service.

### 10.1 Level
- `info` : start/end job, summary
- `warning` : data anomali tapi lanjut (stale price, partial fill)
- `error` : gagal posisi/ticker tertentu tapi job lanjut aman
- `critical` : inconsistency state / schema mismatch → stop

### 10.2 Context wajib
Setiap log penting minimal memuat:
- `account_id`, `ticker_id`, `strategy_code`
- `trade_date` atau `as_of_trade_date`
- `position_state`
- `job_run_id` (jika ada)

---

## 11) Performa & SRP boundaries (ringkas)
- Jangan scan semua trades untuk tiap request UI; gunakan `portfolio_positions` sebagai derived cache.
- Batch update per chunk, disable query log.
- Repository hanya DB access; policy/logic di service/policy class.
- Semua rule strategy berada di **policy layer**, bukan di repository.

---

## 12) Akurasi “cuan” (cara evaluasi yang valid)
Akurasi bukan “profit tinggi”, tapi **hasil bisa dipercaya** dan bisa dipisah per strategi.

Minimal metrik per strategy per minggu/bulan:
- Win rate
- Avg win / avg loss
- Expectancy
- Max drawdown per strategy
- Slippage vs plan
- Plan adherence rate (berapa exit by plan vs exit by panic/manual)
- Stale-data incidents (berapa kali canonical held/stale)

---

## 13) Checklist implementasi (wajib lewat sebelum dianggap valid)
1) Trade ingest idempotent (tidak dobel)
2) FIFO lot matching benar (partial sell aman)
3) positions.qty == sum lots.remaining_qty (strict)
4) plan snapshot tersimpan saat PLANNED dan immutable saat OPEN
5) semua transisi state menghasilkan event
6) valuasi EOD memakai canonical effective date (bukan “tanggal lokal”)
7) semua error penting masuk `portfolio.log` dengan context lengkap

---

## 14) Rekomendasi integrasi cepat (praktis)
- Watchlist → simpan `plan_snapshot` ke `portfolio_plans` (atau langsung ke `portfolio_positions` saat PLANNED).
- Entry fill → buat/adjust lots → update position → write event.
- Exit fill → match lots → realized pnl → update position → write event.
- EOD → update last_price & unrealized dari canonical.

Selesai.
