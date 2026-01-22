# TradeAxis Watchlist — Cross-Policy Contract (EOD-driven)
File: `watchlist.md`

Watchlist di TradeAxis **bukan “penentu beli”**. Watchlist adalah:
- Selektor kandidat (ranking) berbasis data.
- Penyaji rencana eksekusi yang realistis (timing berbentuk **window**, bukan jam absolut).
- Penghasil alasan yang bisa diaudit (**reason codes** deterministik).

Dokumen ini adalah **kontrak lintas-policy**: schema output, data dictionary, governance reason codes, tick rounding, lot sizing, fee model, dan invariants.

**Single source of truth untuk angka/threshold/rules per strategi ada di policy docs:**
- `weekly_swing.md`
- `dividen_swing.md`
- `intraday_light.md`
- `position_trade.md`
- `no_trade.md`

---

## 1) Terminologi waktu & data readiness

### 1.1 Field waktu (wajib konsisten)
- `generated_at` (RFC3339, WIB): waktu JSON dibuat.
- `trade_date` (YYYY-MM-DD): tanggal EOD yang dipakai untuk scoring/plan (basis data **canonical**).
- `as_of_trade_date` (YYYY-MM-DD): trading day terakhir yang **seharusnya** sudah punya EOD canonical pada saat `generated_at`.

Definisi `as_of_trade_date`:
- Jika `generated_at` **sebelum cutoff EOD** (pagi/pre-open) → `as_of_trade_date` = trading day **kemarin**.
- Jika **sesudah cutoff + publish sukses** → `as_of_trade_date` = trading day **hari ini**.


- `exec_trade_date` (YYYY-MM-DD): tanggal trading **target eksekusi** untuk rencana entry/exit (biasanya **next trading day** setelah `trade_date`).

Catatan:
- `trade_date` = basis EOD untuk scoring/level.
- `exec_trade_date` = basis **jam sesi** (`open/close`) untuk time-window eksekusi.

### 1.3 Kontrak resolusi token `open` / `close` (lintas-policy)

Token `open` dan `close` pada `entry_windows[]` / `avoid_windows[]` **wajib** di-resolve menggunakan jam sesi untuk `exec_trade_date`.

Kontrak minimal (wajib di `meta.session`):
- `meta.session.open_time`  (HH:MM, WIB)
- `meta.session.close_time` (HH:MM, WIB)
- (opsional) `meta.session.breaks[]` (array string, format `HH:MM-HH:MM`)

Aturan:
- `open` → `meta.session.open_time`
- `close` → `meta.session.close_time`
- Jika ada `breaks[]`, engine wajib mengurangi `entry_windows` yang overlap dengan break (atau memecah window).

Sumber jam sesi harus berasal dari kalender bursa (bisa berubah pada hari tertentu), **bukan hardcode** di policy.


### 1.2 Data freshness gate (wajib)
Watchlist ini EOD-driven. Rekomendasi **NEW ENTRY** hanya boleh keluar jika data EOD **CANONICAL** untuk `trade_date` tersedia.

Kontrak minimal:
- `meta.eod_canonical_ready` = boolean
- `meta.missing_trading_dates[]` = daftar trading day dari (`trade_date` .. `as_of_trade_date`) yang belum punya canonical.

Jika `meta.eod_canonical_ready == false`:
- `recommendations.mode` wajib `NO_TRADE` (NEW ENTRY diblok).
- Kandidat tetap boleh ditampilkan sebagai **watch-only** (untuk monitoring), tetapi:
  - `trade_disabled = true`
  - `entry_style = "No-trade"`
  - `size_multiplier = 0.0`
  - `max_positions_today = 0`
  - `entry_windows = []`
  - `avoid_windows = ["09:00-close"]`
- Tambahkan reason code global: `GL_EOD_NOT_READY`.

Catatan: manajemen posisi existing boleh tetap berjalan (lihat `no_trade.md`).

---

## 2) Data dictionary (lintas-policy)

### 2.1 Per-ticker EOD (wajib, canonical)
Sumber: `ticker_ohlc_daily` untuk `trade_date`.
- `open`, `high`, `low`, `close`, `volume`

### 2.2 Per-ticker indicators/features (wajib)
Sumber: `ticker_indicators_daily` (atau tabel feature harian lain) untuk `trade_date`.
Minimal yang umum dipakai lintas policy:
- `ma20`, `ma50`, `ma200`
- `rsi14`
- `atr14`, `atr_pct` (= atr14/close)
- `vol_sma20`, `vol_ratio`
- `dv20` (SMA20 dari `close*volume`, pakai 20 **trading days**), `liq_bucket` (A/B/C)
- candle derived:
  - `candle_body_pct`, `upper_wick_pct`, `lower_wick_pct`
  - flags (opsional): `is_inside_day`, `engulfing_type`, `is_long_upper_wick`, `is_long_lower_wick`
- setup lifecycle:
  - `signal_code`, `signal_label` (opsional tapi disarankan)
  - `signal_first_seen_date`, `signal_age_days` (trading days; opsional tapi sangat disarankan)

### 2.3 Market context (wajib minimal)
- `market_calendar`: `cal_date`, `is_trading_day`, `holiday_name`
- `market_index_daily` (minimal IHSG): `trade_date`, `close`, `ret_1d`, `ret_5d`, opsional `ma20`, `ma50`
- `meta.market_regime`: `risk-on | neutral | risk-off`

Opsional (kalau tersedia):
- breadth: `advancers`, `decliners`, `new_high_20d`, `new_low_20d`, ratio.

### 2.4 Portfolio context (opsional input; wajib jika ingin output manage-mode)
Jika ada input portfolio, watchlist boleh menambahkan konteks posisi:
- `position.has_position` (bool)
- `position.position_avg_price` (float), `position.position_lots` (int)
- `position.entry_trade_date` (YYYY-MM-DD), `position.days_held` (trading days)

#### 2.4.1 Input alias compatibility (backward compatibility)
- Jika input portfolio memakai `avg_price`, mapping → `position.position_avg_price`
- Jika input portfolio memakai `lots`, mapping → `position.position_lots`

Policy docs wajib mengacu ke canonical fields. Alias hanya untuk normalisasi input.

### 2.5 Execution snapshot (opsional)
Untuk guard anti-gap/anti-chasing yang dievaluasi **hari eksekusi**:
- `preopen_last_price` (float|null): harga indikatif sebelum market buka pada hari eksekusi.
- `open_or_last_exec` (float|null): derived = `preopen_last_price` (watchlist **tidak boleh** fallback ke `open` EOD).

---


### 2.6 Ticker tradeability & special notations (lintas-policy)

Watchlist wajib mengunci **kondisi ticker** yang membuat eksekusi berbeda/berisiko secara mekanisme.

#### 2.6.1 Field (wajib jika data tersedia)
Tambahkan object berikut di setiap kandidat:

- `ticker_flags.special_notations[]` (array string; contoh: `["E","X"]`)
- `ticker_flags.is_suspended` (boolean)
- `ticker_flags.status_quality` (string): `OK|STALE|UNKNOWN`
- `ticker_flags.status_asof_trade_date` (YYYY-MM-DD|null): tanggal status yang dipakai untuk `special_notations/is_suspended/trading_mechanism`.

- `ticker_flags.trading_mechanism` (string):
  - `REGULAR` (default)
  - `FULL_CALL_AUCTION` (mis. papan pemantauan khusus / kondisi tertentu)

Jika data tidak tersedia, set nilai default aman:
- `special_notations = []`, `is_suspended = false`, `trading_mechanism = "REGULAR"`.

#### 2.6.2 Global gating (wajib)
Aturan lintas-policy berikut harus selalu berlaku:

- Jika `ticker_flags.is_suspended == true`:
  - `timing.trade_disabled = true`
  - `levels.entry_type = "WATCH_ONLY"`
  - reason codes: `GL_SUSPENDED`

- Jika `ticker_flags.trading_mechanism == "FULL_CALL_AUCTION"` **atau** `ticker_flags.special_notations` mengandung `"X"`:
  - Default kontrak: **block NEW ENTRY** (karena mekanisme FCA beda dari regular)
  - `timing.trade_disabled = true`
  - `levels.entry_type = "WATCH_ONLY"`
  - reason codes: `GL_SPECIAL_NOTATION_X` dan/atau `GL_MECHANISM_FCA`

- Jika `ticker_flags.special_notations` mengandung `"E"`:
  - Tidak auto-block oleh kontrak, tapi wajib **warning** di UI
  - reason code: `GL_SPECIAL_NOTATION_E`

Catatan:
- Kalau suatu hari kamu menambah policy yang mendukung FCA, policy itu harus eksplisit men-declare dukung FCA dan kontrak ini perlu revisi (jangan diam-diam).



#### 2.6.3 Status feed quality (wajib)

Karena notasi/suspensi bisa berubah, engine wajib menandai kualitas data status:

- Jika data status untuk `exec_trade_date` tersedia → `status_quality = "OK"`, `status_asof_trade_date = exec_trade_date`.
- Jika yang dipakai adalah last-known (tanggal < `exec_trade_date`) → `status_quality = "STALE"`, `status_asof_trade_date = <tanggal last-known>`, tambahkan reason code `GL_TICKER_STATUS_STALE`.
- Jika data status tidak tersedia sama sekali (atau tidak bisa dipastikan) → `status_quality = "UNKNOWN"`, `status_asof_trade_date = null`, tambahkan reason code `GL_TICKER_STATUS_UNKNOWN`.

Catatan:
- `STALE/UNKNOWN` tidak otomatis memblokir trade oleh kontrak, kecuali juga memenuhi gating Section 2.6.2 (suspension/FCA/X).
- Policy boleh memperketat (mis. block jika STALE) tetapi harus ditulis di policy doc.


## 3) Tick size & rounding (wajib lintas-policy)

### 3.1 Tabel fraksi (IDX equities — Reguler/Tunai)
Gunakan `last_price`/harga referensi terbaru untuk menentukan tick:

| Range harga (Rp) | Tick (Rp) |
|---|---:|
| `< 200` | 1 |
| `200 – < 500` | 2 |
| `500 – < 2.000` | 5 |
| `2.000 – < 5.000` | 10 |
| `>= 5.000` | 25 |

Catatan:
- Tabel ini harus menjadi **config** (bukan hardcode), karena regulasi bisa berubah.
- Jika sistem punya `tick_size` per ticker/hari dari market rules table, itu yang dipakai sebagai sumber utama.

### 3.2 Kontrak rounding harga
Semua harga plan (entry/SL/TP/trigger) **wajib** di-round ke tick.

`round_to_tick(price, tick, mode)`:
- mode `DOWN`  : floor ke tick
- mode `UP`    : ceil ke tick
- mode `NEAREST`: round terdekat (tie → UP)

Default yang disarankan (kontrak lintas-policy; policy boleh override jika perlu):
- Entry trigger (breakout): `UP`
- Entry limit (pullback): `DOWN` untuk batas bawah, `UP` untuk batas atas
- Stop loss: `DOWN` (lebih ketat/konservatif)
- Take profit: `DOWN` (lebih realistis untuk eksekusi)
- Break-even / trailing SL: `DOWN`

Jika policy butuh “+1 tick”:
- pakai `price + tick` (bukan +1 rupiah), lalu round lagi sesuai mode.

---


### 3.3 Price typing & rounding output (lintas-policy)

Untuk konsistensi lintas-policy dan menghindari bug float:

- Semua field `*_price` di output (`entry_*`, `stop_loss_price`, `tp*`, `be_price`, dll) wajib bertipe **integer IDR** (tanpa desimal).
- Semua harga wajib sudah melalui tick rounding (lihat Section 3.2).
- Field uang hasil hitung (contoh: `estimated_cost`, `remaining_cash`, `buy_fee`, `sell_fee`, `slip_cost`, `net_pnl`) juga wajib integer IDR.

Kontrak pembulatan (deterministik):
- Biaya/cost (`estimated_cost`, fee, slippage) → **ceil ke Rupiah** (konservatif, tidak meng-underestimate biaya).
- PnL (`net_pnl`) → **floor ke Rupiah** (konservatif, tidak meng-overestimate cuan).
- `remaining_cash = alloc_budget - estimated_cost` setelah pembulatan cost.

## 4) Lot sizing & rounding (wajib lintas-policy)

### 4.1 Definisi lot
- `lot_size = 100` lembar untuk Pasar Reguler & Tunai.
- Semua output lots di watchlist mengacu ke **round lot**.

### 4.2 Kontrak sizing minimum
- Lots **harus integer >= 0**.
- Jika hasil sizing < 1 lot → watchlist **tidak boleh** memaksa BUY, harus turun menjadi `WATCH_ONLY` (policy menentukan reason code-nya).

### 4.3 Pembulatan lots
Kontrak default:
- `lots = floor( alloc_budget / (entry_price * lot_size) )`
- `estimated_cost = lots * lot_size * entry_price`
- `remaining_cash = alloc_budget - estimated_cost`

Policy boleh menambahkan guard viability (min alloc, min lots, min net edge) di dokumen policy.

---

## 5) Fee model (wajib lintas-policy)

Fee berbeda antar broker. Watchlist **tidak boleh** hardcode fee broker; fee harus configurable.

### 5.1 Config keys (contoh)
- `fee.buy_pct`  (contoh 0.0015 = 0.15%)
- `fee.sell_pct` (contoh 0.0025 = 0.25%)
- (opsional) `fee.min_idr` (minimal fee)
- (opsional) `slippage_pct` (penalti spread/slippage konservatif, ex: 0.001)

### 5.2 Rumus net P&L (kontrak)
Untuk sebuah trade dengan:
- `entry_price`, `exit_price`
- `shares = lots * lot_size`

Gross:
- `gross_pnl = (exit_price - entry_price) * shares`

Fees (model sederhana):
- `buy_fee  = entry_price * shares * fee.buy_pct`
- `sell_fee = exit_price  * shares * fee.sell_pct`

Slippage (opsional):
- `slip_cost = (entry_price + exit_price) * shares * (slippage_pct / 2)`

Net:
- `net_pnl = gross_pnl - buy_fee - sell_fee - slip_cost`
- `net_pnl_pct = net_pnl / (entry_price * shares)`

Kontrak output (opsional, tapi kalau ada harus konsisten):
- `profit_tp2_net`, `rr_tp2_net`, `net_edge_pct_est`

---

## 6) Reason code governance (wajib)

### 6.1 Namespace rule (UI reason codes)
`reason_codes[]` adalah **UI codes** dan wajib prefixed sesuai policy:
- WEEKLY_SWING: `WS_*`
- DIVIDEND_SWING: `DS_*`
- INTRADAY_LIGHT: `IL_*`
- POSITION_TRADE: `PT_*`
- NO_TRADE: `NT_*`
- Global gate lintas-policy: `GL_*` (contoh: `GL_EOD_NOT_READY`, `GL_POLICY_DOC_MISSING`)

### 6.2 Debug vs UI
- `reason_codes[]` (UI): **tidak boleh** pakai kode generik seperti `TREND_STRONG`.
- Kode generik (untuk audit/scoring) boleh disimpan di:
  - `debug.rank_reason_codes[]` (opsional).

### 6.3 Legacy mapping (wajib kalau masih ada output lama)
Jika ada kode generik lama, engine wajib mapping ke policy prefix:
- `GAP_UP_BLOCK` → `WS_GAP_UP_BLOCK` / `IL_GAP_UP_BLOCK` / dst (sesuai policy aktif)
- `MARKET_RISK_OFF` → `GL_MARKET_RISK_OFF` (atau `NT_MARKET_RISK_OFF` jika dianggap spesifik NO_TRADE)

---

## 7) Output JSON schema (final)

### 7.1 Root schema (wajib)
```json
{
  "schema_version": "watchlist.v1",
  "trade_date": "YYYY-MM-DD",
  "exec_trade_date": "YYYY-MM-DD",
  "generated_at": "RFC3339",
  "policy": {
    "selected": "WEEKLY_SWING|DIVIDEND_SWING|INTRADAY_LIGHT|POSITION_TRADE|NO_TRADE",
    "policy_version": "string|null"
  },
  "meta": {
    "dow": "Mon|Tue|Wed|Thu|Fri",
    "market_regime": "risk-on|neutral|risk-off",
    "eod_canonical_ready": true,
    "as_of_trade_date": "YYYY-MM-DD",
    "missing_trading_dates": [],
    "counts": {
      "total": 0,
      "top_picks": 0,
      "secondary": 0,
      "watch_only": 0
    },
    "notes": [],
    "session": {
      "open_time": "HH:MM",
      "close_time": "HH:MM",
      "breaks": []
    }
  },
  "recommendations": {
    "mode": "NO_TRADE|CARRY_ONLY|BUY_1|BUY_2_SPLIT|BUY_3_SMALL",
    "max_positions_today": 0,
    "risk_per_trade_pct": null,
    "capital_total": null,
    "allocations": []
  },
  "groups": {
    "top_picks": [],
    "secondary": [],
    "watch_only": []
  }
}
```


### 7.0 Schema `recommendations.allocations[]` (lintas-policy)


### 7.1.1 Arti `risk_per_trade_pct` dan `capital_total` (lintas-policy)

- `capital_total` adalah input/angka referensi modal yang digunakan engine untuk menghitung `alloc_budget` dan `lots_recommended`.
- `risk_per_trade_pct` adalah parameter risiko per posisi (mis. 0.5%–2% dari `capital_total`) yang digunakan untuk mengukur sizing berbasis stop-loss (jika policy menerapkan risk-based sizing).

Kontrak:
- Keduanya boleh `null` jika sizing menggunakan metode lain (mis. fixed-alloc tanpa risk model).
- Jika `recommendations.allocations[]` diisi dan `alloc_budget` dihitung dari modal:
  - `capital_total` harus non-null.
- Jika policy menggunakan risk-based sizing (`risk_pct` / risk model di output):
  - `risk_per_trade_pct` harus non-null.

Jika saat ini belum dipakai penuh:
- Field tetap boleh ada sebagai “reserved”, tetapi aturan di atas menjadi target implementasi dan contract test bisa memilih untuk hanya memvalidasi tipe (`null|number`) sampai engine memakai sepenuhnya.



Jika `recommendations.allocations[]` digunakan, setiap item wajib mengikuti schema berikut (minimum):

```json
{
  "ticker_code": "ABCD",
  "alloc_pct": 0.25,
  "alloc_budget": 12500000,
  "entry_price_ref": 1230,
  "lots_recommended": 10,
  "estimated_cost": 1230000,
  "remaining_cash": 20000,
  "reason_codes": ["WS_ALLOC_BALANCED"]
}
```

Aturan:
- `ticker_code` wajib ada dan harus cocok dengan kandidat di `groups.*[]`.
- Minimal salah satu ada: `alloc_pct` atau `alloc_budget`.
- `entry_price_ref` wajib integer IDR dan sudah tick-rounded (Section 3).
- `lots_recommended` wajib integer >= 0 (Section 4).
- `estimated_cost`/`remaining_cash` wajib integer IDR dan mengikuti kontrak rounding (Section 3.3 bila ada di dokumen ini).
- `reason_codes[]` optional, tapi jika ada harus mengikuti governance prefix (Section 6).

Jika mode `NO_TRADE` atau `CARRY_ONLY` → `allocations` wajib `[]`.


### 7.2 Candidate object (wajib minimal)
Semua kandidat di `groups.*[]` menggunakan struktur yang sama.

```json
{
  "ticker_id": 0,
  "ticker_code": "ABCD",
  "rank": 1,
  "watchlist_score": 0,
  "confidence": "High|Med|Low",
  "setup_type": "Breakout|Pullback|Continuation|Reversal|Base",
  "reason_codes": ["WS_TREND_ALIGN_OK"],
  "debug": {
    "rank_reason_codes": ["TREND_STRONG"]
  },

  "ticker_flags": {
    "special_notations": [],
    "is_suspended": false,
    "status_quality": "OK",
    "status_asof_trade_date": null,
    "trading_mechanism": "REGULAR"
  },

  "timing": {
    "entry_windows": ["09:20-10:30"],
    "avoid_windows": ["09:00-09:15"],
    "entry_style": "Breakout-confirm|Pullback-wait|Reversal-confirm|No-trade",
    "size_multiplier": 1.0,
    "trade_disabled": false,
    "trade_disabled_reason": null,
    "trade_disabled_reason_codes": []
  },

  "levels": {
    "entry_type": "BREAKOUT_TRIGGER|PULLBACK_LIMIT|REVERSAL_CONFIRM|WATCH_ONLY",
    "entry_trigger_price": null,
    "entry_limit_low": null,
    "entry_limit_high": null,
    "stop_loss_price": null,
    "tp1_price": null,
    "tp2_price": null,
    "be_price": null
  },

  "sizing": {
    "lot_size": 100,
    "lots_recommended": null,
    "estimated_cost": null,
    "remaining_cash": null,
    "risk_pct": null,
    "profit_tp2_net": null,
    "rr_tp2_net": null
  },

  "position": {
    "has_position": false,
    "position_avg_price": null,
    "position_lots": null,
    "days_held": null,
    "position_state": null,
    "action_windows": [],
    "updated_stop_loss_price": null
  },

  "checklist": [
    "Spread rapat, bid/ask padat (cek top-5)",
    "Tidak gap-up terlalu jauh dari close kemarin",
    "Ada follow-through, bukan spike 1 menit"
  ]
}
```

### 7.3 Rules wajib untuk groups
- `groups.top_picks[]` hanya boleh berisi kandidat yang **trade_disabled == false** (kecuali mode `NO_TRADE/CARRY_ONLY`, lihat invariant).
- Kandidat yang gagal viability/sizing boleh dipindahkan ke `watch_only` (policy menentukan reason code).

---



### 7.4 Kontrak format time window (lintas-policy)

Semua `entry_windows[]` dan `avoid_windows[]` wajib menggunakan format string yang konsisten:

- Format dasar: `HH:MM-HH:MM` (24h, tanpa detik, WIB).
- Endpoint khusus yang diizinkan:
  - `open` (pembukaan sesi reguler)
  - `close` (penutupan sesi reguler)
  Contoh valid: `open-09:15`, `13:30-close`, `open-close`.

Aturan validasi:
- Start < end (setelah resolve `open/close` ke jam nyata sesuai kalender bursa).
- Windows harus diurutkan naik berdasarkan start time.
- Tidak boleh ada overlap duplikat; kalau overlap terjadi, engine wajib normalisasi/merge.

Aturan konflik:
- `avoid_windows` **menang** atas `entry_windows`.
- Engine wajib melakukan `effective_entry_windows = entry_windows - avoid_windows`.
- Jika hasil `effective_entry_windows` kosong → kandidat harus menjadi `WATCH_ONLY` (`timing.trade_disabled = true`), reason `GL_NO_EXEC_WINDOW`.

## 8) Invariants (hard rules)

### 8.1 Global gating lock (NO_TRADE)
Jika `recommendations.mode == "NO_TRADE"`:
- semua kandidat wajib:
  - `timing.trade_disabled = true`
  - `timing.entry_style = "No-trade"`
  - `timing.size_multiplier = 0.0`
  - `timing.entry_windows = []`
  - `timing.avoid_windows = ["09:00-close"]`
- `recommendations.allocations = []`
- Tambahkan `meta.notes` + reason code global `GL_EOD_NOT_READY` atau reason `NT_*` sesuai pemicu.

### 8.2 CARRY_ONLY
Jika `recommendations.mode == "CARRY_ONLY"`:
- NEW ENTRY tidak boleh direkomendasikan (`allocations = []`).
- Kandidat boleh tampil untuk monitoring, tapi `top_picks` harus kosong.
- `position.*` boleh berisi aksi `HOLD/REDUCE/EXIT/TRAIL_SL` sesuai policy.

### 8.3 Reason codes validity
- Semua `reason_codes[]` harus memenuhi rule namespace di Section 6.
- Jika ada unknown/invalid prefix → output dianggap **invalid** (contract test harus fail).

---



### 8.4 Ticker tradeability lock (lintas-policy)

Jika kandidat memenuhi salah satu kondisi berikut:
- `ticker_flags.is_suspended == true`, atau
- `ticker_flags.trading_mechanism == "FULL_CALL_AUCTION"`, atau
- `ticker_flags.special_notations` mengandung `"X"`,

maka kandidat wajib:
- `timing.trade_disabled = true`
- `levels.entry_type = "WATCH_ONLY"`
- `timing.entry_windows = []`
- `timing.avoid_windows = ["open-close"]`
- reason codes sesuai Section 2.6 (`GL_SUSPENDED`, `GL_MECHANISM_FCA`, `GL_SPECIAL_NOTATION_X`)



### 8.5 Konsistensi `recommendations.mode` vs `allocations` vs `groups` (lintas-policy)

Aturan ini wajib untuk mencegah output yang “nggak nyambung” antara mode, sizing, dan daftar kandidat:

- Jika `recommendations.mode == "BUY_1"`:
  - `recommendations.max_positions_today == 1`
  - `recommendations.allocations.length == 1`
- Jika `recommendations.mode == "BUY_2_SPLIT"`:
  - `recommendations.max_positions_today == 2`
  - `recommendations.allocations.length == 2`
- Jika `recommendations.mode == "BUY_3_SMALL"`:
  - `recommendations.max_positions_today == 3`
  - `recommendations.allocations.length == 3`
- Jika `recommendations.mode in ["NO_TRADE","CARRY_ONLY"]`:
  - `recommendations.allocations == []`
  - `groups.top_picks == []` (tidak boleh ada NEW ENTRY picks)

Linking rule:
- Setiap item `recommendations.allocations[]` wajib menunjuk ke kandidat yang ada di `groups.top_picks[]` (match `ticker_code`).
- Kandidat yang tidak ada di `groups.top_picks[]` **tidak boleh** muncul di `allocations[]`.



### 8.6 Konsistensi `meta.counts` vs isi `groups` (lintas-policy)

Jika `meta.counts` disediakan, nilainya wajib konsisten:

- `meta.counts.top_picks == len(groups.top_picks)`
- `meta.counts.secondary == len(groups.secondary)`
- `meta.counts.watch_only == len(groups.watch_only)`
- `meta.counts.total == meta.counts.top_picks + meta.counts.secondary + meta.counts.watch_only`

Jika terjadi mismatch → output dianggap invalid (contract test harus fail).



### 8.7 Ordering & uniqueness (lintas-policy)

Untuk memastikan output deterministik dan tidak “lompat-lompat”:

- `ticker_code` harus unik di seluruh `groups.*[]` (tidak boleh muncul dua kali di group berbeda).
- `rank` harus unik per kandidat dan berada pada range `1..N` (tanpa duplikat).
- Ordering wajib:
  - `groups.top_picks` diurutkan berdasarkan `rank` ascending.
  - `groups.secondary` diurutkan berdasarkan `rank` ascending.
  - `groups.watch_only` diurutkan berdasarkan `rank` ascending.

Jika engine membutuhkan tiebreaker (mis. rank dihitung ulang):
- tiebreaker order: `watchlist_score desc`, lalu `ticker_code asc`.


## 9) Policy selection precedence (default)

Bagian ini hanya memastikan pemilihan policy **deterministik** dan tidak saling bertabrakan.

Yang boleh ada di sini (lintas-policy):
1) **Global gates**:
   - Jika `meta.eod_canonical_ready == false` → `NO_TRADE`.
     - Jika `position.has_position == true` → boleh set `recommendations.mode = "CARRY_ONLY"` (manage posisi saja).
   - Jika `meta.market_regime == "risk-off"` → `NO_TRADE` (reason: `GL_MARKET_RISK_OFF`).

2) **Urutan prioritas** (jika >1 policy eligible pada hari yang sama):
   1. `DIVIDEND_SWING`
   2. `INTRADAY_LIGHT`
   3. `POSITION_TRADE`
   4. `WEEKLY_SWING`

Yang tidak boleh ada di sini:
- definisi eligibility / threshold / scoring / timing spesifik policy.

Eligibility rules harus ditulis di dokumen policy masing-masing (atau doc router khusus bila dibuat).

## 10) Policy doc loading & failure behavior

Read order (wajib):
1) `watchlist.md` (dokumen ini)
2) `weekly_swing.md`
3) `dividen_swing.md`
4) `intraday_light.md`
5) `position_trade.md`
6) `no_trade.md`

Jika salah satu policy doc yang dibutuhkan tidak bisa diload:
- set `recommendations.mode = "NO_TRADE"` untuk NEW ENTRY,
- `meta.notes` tambahkan “Policy doc missing”,
- reason code global: `GL_POLICY_DOC_MISSING`.

---

## 11) Contoh reason codes (sesuai governance)

Contoh ringkas (WEEKLY_SWING):
- `reason_codes`: `["WS_TREND_ALIGN_OK","WS_VOLUME_OK","WS_SETUP_BREAKOUT"]`
- `debug.rank_reason_codes`: `["TREND_STRONG","VOL_RATIO_HIGH","BREAKOUT_BIAS"]`

Tidak boleh:
- `reason_codes`: `["TREND_STRONG","MA_ALIGN_BULL"]`  ❌ (harus prefixed policy)
