# TradeAxis Watchlist — Cross-Policy Contract (EOD-driven)
File: `watchlist.md`

Watchlist di TradeAxis **bukan “penentu beli”**. Watchlist adalah:
- Selektor kandidat (ranking) berbasis data.
- Penyaji rencana eksekusi yang realistis (timing berbentuk **window**, bukan jam absolut).
- Penghasil alasan yang bisa diaudit (**reason codes** deterministik).

Dokumen ini adalah **kontrak lintas-policy**: schema output, data dictionary, governance reason codes, tick rounding, lot sizing, fee model, dan invariants.

**Single source of truth untuk angka/threshold/rules per strategi ada di policy docs:**
- `policy/weekly_swing.md`
- `policy/dividend_swing.md`
- `policy/intraday_light.md`
- `policy/position_trade.md`
- `policy/no_trade.md`

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

### 1.2 Data freshness gate (wajib)
Watchlist ini EOD-driven. Rekomendasi **NEW ENTRY** hanya boleh keluar jika data EOD **CANONICAL** untuk `trade_date` tersedia.

Kontrak minimal:
- `meta.eod_canonical_ready` = boolean
- `meta.missing_trading_dates[]` = daftar trading day dari (`trade_date` .. `as_of_trade_date`) yang belum punya canonical.

Jika `meta.eod_canonical_ready == false`:
- `recommendations.mode` wajib `NO_TRADE` (NEW ENTRY diblok).
- Kandidat tetap boleh ditampilkan sebagai **watch-only** (untuk monitoring), tetapi:
  - `timing.trade_disabled = true`
  - `timing.entry_style = "No-trade"`
  - `timing.size_multiplier = 0.0`
  - `recommendations.max_positions_today = 0`
  - `timing.entry_windows = []`
  - `timing.avoid_windows = ["09:00-close"]`
- Tambahkan reason code global: `GL_EOD_NOT_READY`.

Catatan: manajemen posisi existing boleh tetap berjalan (lihat `no_trade.md`).

---


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
- breadth: `breadth_advancers`, `breadth_decliners`, `breadth_new_high_20d`, `breadth_new_low_20d`, `breadth_adv_decl_ratio`.

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
- `preopen_last_price` (int|null): harga indikatif sebelum market buka pada hari eksekusi (IDR, integer).
- `intraday_last_price` (int|null): last price intraday pada hari eksekusi (IDR, integer), jika snapshot tersedia.
- `open_or_last_exec` (int|null): derived = first non-null dari `preopen_last_price`, lalu `intraday_last_price`. Watchlist **tidak boleh** fallback ke `open` EOD.

---

### 2.5.1 Derived metrics lintas-policy (wajib definisi)

Beberapa policy memakai metrik turunan berikut. Definisi harus konsisten:

- `gap_pct` (float|null):
  - Definisi: `(open_or_last_exec / close) - 1`
  - `close` adalah close canonical pada `trade_date` (EOD basis).
  - Jika `open_or_last_exec` null → `gap_pct = null` (policy yang butuh gap guard harus treat sebagai “unknown”).

- `ret_since_entry_pct` (float|null) untuk posisi berjalan:
  - Definisi EOD basis: `(close / position.position_avg_price) - 1` (menggunakan `close` pada `trade_date`)
  - Jika ingin versi eksekusi intraday, gunakan `(open_or_last_exec / position.position_avg_price) - 1` ketika snapshot tersedia, tapi ini harus dinyatakan eksplisit oleh engine (jangan diam-diam).

- `close_near_high` (bool):
  - Definisi: `((high - close) / max(high - low, 1)) <= 0.25`
  - Menggunakan OHLC canonical pada `trade_date`.

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






### 2.6.4 Base eligibility lintas-policy vs policy-specific

Kontrak lintas-policy hanya mengunci **tradeability** (suspension/FCA/X, window eksekusi, readiness data).
Kriteria seleksi kandidat yang bersifat strategi (contoh: threshold trend/volume/RSI, liquidity minimum, scoring weights) adalah domain **policy docs** dan tidak boleh “dipindah-diam-diam” ke `watchlist.md`.


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
Jika sebelumnya engine/UI pernah memakai kode **generik** (tanpa prefix policy), maka:

**Rule hard (contract):**
- `reason_codes[]` **tidak boleh** berisi kode tanpa prefix resmi (`WS_ / DS_ / IL_ / PT_ / NT_ / GL_`).
- Kode generik lama **wajib dimigrasikan** secara deterministik ke canonical UI code.
- Kode generik boleh disimpan untuk audit/scoring **hanya** di `debug.rank_reason_codes[]` (bukan di `reason_codes[]`).

#### Mapping minimal (generic lama → canonical UI code)

> Prinsip: map ke `{policy_prefix}_*` berdasarkan policy aktif; untuk gate global pakai `GL_*`.

| legacy (jangan dipublish ke UI) | canonical UI code (publish) |
|---|---|
| `GAP_UP_BLOCK` | `{policy_prefix}_GAP_UP_BLOCK` |
| `CHASE_BLOCK_DISTANCE_TOO_FAR` | `{policy_prefix}_CHASE_BLOCK_DISTANCE_TOO_FAR` |
| `MIN_EDGE_FAIL` | `{policy_prefix}_MIN_TRADE_VIABILITY_FAIL` *(atau code edge/viability yang dipakai policy)* |
| `TIME_STOP_TRIGGERED` | `{policy_prefix}_TIME_STOP_T2` *(atau T3 sesuai rule yang kena)* |
| `TIME_STOP_T2` | `{policy_prefix}_TIME_STOP_T2` |
| `TIME_STOP_T3` | `{policy_prefix}_TIME_STOP_T3` |
| `FRIDAY_EXIT_BIAS` | `{policy_prefix}_FRIDAY_EXIT_BIAS` |
| `WEEKEND_RISK_BLOCK` | `{policy_prefix}_FRIDAY_EXIT_BIAS` *(fallback jika policy tidak punya code weekend spesifik)* |
| `VOLATILITY_HIGH` | `{policy_prefix}_VOL_HIGH` *(atau code volatility yang dipakai policy)* |
| `FEE_IMPACT_HIGH` | `{policy_prefix}_MIN_TRADE_VIABILITY_FAIL` *(fee/edge gagal)* |
| `NO_FOLLOW_THROUGH` | `{policy_prefix}_TIME_STOP_T2` *(fallback; atau buat code follow-through spesifik di policy)* |
| `SETUP_EXPIRED` | `{policy_prefix}_SIGNAL_STALE` *(atau code stale yang dipakai policy)* |

Mapping global (lintas-policy):
- `EOD_NOT_READY` → `GL_EOD_NOT_READY`
- `EOD_STALE` → `GL_EOD_STALE`
- `MARKET_RISK_OFF` → `GL_MARKET_RISK_OFF`
- `POLICY_INACTIVE` → `GL_POLICY_INACTIVE`

Catatan implementasi:
- `{policy_prefix}` adalah salah satu: `WS`, `DS`, `IL`, `PT`, `NT`.
- Kalau ada legacy code yang **tidak dikenal**, engine wajib:
  - taruh di `debug.rank_reason_codes[]`, dan
  - tambahkan `GL_LEGACY_CODE_UNMAPPED` ke `reason_codes[]` (agar mudah diaudit).

---
## 7) Output JSON schema (final)

### 7.1 Root schema (wajib)
```json
{
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



### 7.2.1 `watchlist_score` & `confidence` (lintas-policy)

- `watchlist_score` adalah skor ranking internal watchlist untuk mengurutkan kandidat dalam policy yang dipilih.
- Kontrak tipe & arah:
  - Tipe: number (float atau int).
  - Range yang disarankan: `0..100` (semakin besar semakin baik).
  - Tidak boleh `NaN/Infinity`.

- `confidence` adalah label kualitatif berbasis **percentile** dari `watchlist_score` dalam universe kandidat policy pada `trade_date`.
  - `High` : top 20% (percentile >= 80)
  - `Med`  : percentile 40–79
  - `Low`  : percentile < 40

Kontrak:
- Engine wajib menghitung `confidence` dari ranking score (bukan manual/acak).
- Policy boleh mengubah mapping percentile, tapi **tidak boleh** mengubah key/enum value (`High|Med|Low`).



### 7.2.2 `slices` & `slice_pct` (lintas-policy)

Untuk membantu user memilih kandidat selain rekomendasi dan tetap sizing rapi, setiap kandidat wajib menyediakan:

- `sizing.slices` (int): jumlah pembagian modal per posisi jika user ingin “beli bertahap” atau memilih lebih banyak ticker. Default `1`.
- `sizing.slice_pct` (float): porsi per-slice terhadap `capital_total` (atau terhadap modal kerja policy). Default `1.0`.

Kontrak:
- `slices >= 1`
- `0 < slice_pct <= 1`
- Default mapping: `slice_pct = 1 / slices` (toleransi floating ±0.0001)
- `slices/slice_pct` adalah **helper UI/manual**, tidak mengubah rekomendasi engine kecuali user memilih memakai mode manual.

Jika `recommendations.capital_total` null, `slice_pct` tetap dihitung dari `slices` (tanpa konversi ke rupiah).

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
    "slices": 1,
    "slice_pct": 1.0,
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

### 7.3 Rules wajib untuk groups (tujuan: kandidat, bukan semua ticker)

Watchlist **bukan** “listing semua ticker”. Output hanya berisi **kandidat yang relevan** untuk eksekusi atau monitoring.

#### 7.3.1 Tahapan (wajib)
Engine wajib menjalankan 3 tahap ini agar output tetap ringkas:

1) **Universe filter (drop dulu, baru ranking)**  
   Ticker yang **tidak relevan** harus di-`DROP` (tidak dimasukkan ke `groups.*`), contoh:
   - gagal hard gate lintas-policy (suspensi/FCA/X, data invalid, dsb), atau
   - gagal minimum liquidity/universe policy (policy-specific), atau
   - tidak punya setup dan tidak punya alasan monitoring yang kuat.

2) **Ranking (hanya untuk kandidat yang lolos universe filter)**  
   Hitung `watchlist_score` dan `rank` hanya untuk kandidat yang akan ditampilkan.

3) **Bucketing + cap output**  
   Kandidat yang ditampilkan dibagi ke 3 group dengan batas maksimum (cap) agar tidak membengkak.

Catatan: jumlah ticker yang diproses internal boleh besar, tapi jumlah ticker yang dipublish harus kecil.

#### 7.3.2 Definisi group (wajib)
- `groups.top_picks[]` = kandidat terbaik untuk **NEW ENTRY** (eksekusi).  
  Rule:
  - hanya berisi kandidat dengan `timing.trade_disabled == false`
  - berisi kandidat yang memenuhi eligibility policy untuk entry **hari eksekusi**
  - diurutkan deterministik (lihat Section 8.7)

- `groups.secondary[]` = kandidat cadangan untuk **NEW ENTRY**, tetapi bukan prioritas utama.  
  Rule:
  - `timing.trade_disabled == false`
  - kualitas masih layak dieksekusi, namun kalah ranking / minor penalty / bukan pilihan utama mode hari ini
  - dipakai sebagai **fallback** ketika `top_picks` sedikit/0 atau user ingin memilih manual

- `groups.watch_only[]` = kandidat **monitoring** yang *masih relevan*, bukan dumping ground.  
  Wajib memenuhi salah satu:
  - `position.has_position == true` (posisi existing yang perlu dipantau/manage), atau
  - `timing.trade_disabled == true` **karena guard yang bersifat situasional** (mis. window kosong, snapshot missing, eod not ready), atau
  - “near-eligible” (hampir masuk entry) dan masih punya nilai monitoring.

Ticker yang tidak memenuhi definisi di atas harus **DROP** (tidak dimunculkan di output).

#### 7.3.3 Output limits (wajib)
Agar watchlist tetap fungsional sebagai daftar kandidat, engine wajib menerapkan cap berikut (configurable):

Config key (disarankan):
- `output_limits.top_picks_max` (default: 10)
- `output_limits.secondary_max` (default: 20)
- `output_limits.watch_only_max` (default: 30)
- `output_limits.watch_only_min_score` (default: 50)  → kandidat monitoring “near-eligible” minimal harus memenuhi skor ini

Aturan:
- Setelah ranking, ambil:
  - `top_picks` = top N pertama yang `trade_disabled == false` (N = `top_picks_max`, dan tetap harus konsisten dengan `recommendations.mode` + `allocations`).
  - `secondary` = kandidat berikutnya yang `trade_disabled == false` sampai `secondary_max`.
  - `watch_only` = gabungan dari:
    1) semua kandidat dengan `position.has_position == true` (wajib ditampilkan), lalu
    2) kandidat monitoring lain yang memenuhi definisi 7.3.2, dipilih berdasarkan `watchlist_score desc`, sampai `watch_only_max`.
- Kandidat monitoring yang tidak punya `watchlist_score` (mis. karena mode NO_TRADE) tetap boleh masuk `watch_only`, tetapi tetap harus mengikuti cap (kecuali posisi existing).

Jika `recommendations.mode in ["NO_TRADE","CARRY_ONLY"]`:
- `groups.top_picks` wajib `[]` (lihat invariant).
- `groups.secondary` default `[]`.
- `groups.watch_only` tetap **dibatasi**: posisi existing + monitoring kandidat (cap tetap berlaku).

#### 7.3.4 Definisi `meta.counts` (klarifikasi)
`meta.counts.*` mengacu pada **jumlah kandidat yang dipublish** (setelah filter + cap), bukan jumlah seluruh ticker yang diproses internal.

Opsional (disarankan untuk audit, tapi tidak wajib ada di schema):
- `meta.counts.universe_total` = jumlah kandidat sebelum cap
- `meta.counts.dropped_total` = jumlah ticker yang di-drop sebelum publish


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

### 7.5 Output compatibility mapping (legacy keys)

Dokumen lama (`WATCHLIST_check1.md`) menyebut beberapa key di level root. Kontrak final memakai struktur root+`meta`.

Jika engine/UI masih memakai key lama, lakukan mapping deterministik berikut (tanpa mengubah makna):

| legacy key | canonical key |
|---|---|
| `dow` | `meta.dow` |
| `market_regime` | `meta.market_regime` |
| `market_notes` / `notes` | `meta.notes[]` |
| `market_open` | `meta.session.open_time` |
| `market_close` | `meta.session.close_time` |
| `market_breaks` | `meta.session.breaks[]` |

Untuk per-ticker:
- `ticker` → `ticker_code`
- `score` → `watchlist_score`
- `reasons[]` → `reason_codes[]` (wajib prefixed)
- `buy_window[]` → `timing.entry_windows[]`
- `avoid_window[]` → `timing.avoid_windows[]`

Catatan:
- Ini hanya untuk kompatibilitas migrasi. Semua pengembangan baru harus menulis canonical schema.

---

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



### 8.8 Konsistensi matematika `allocations` (lintas-policy)

Jika `recommendations.allocations[]` tidak kosong, aturan berikut wajib dipenuhi:

**Uniqueness & linking**
- `ticker_code` unik di `allocations[]`.
- Setiap `ticker_code` di `allocations[]` harus ada di `groups.top_picks[]`.

**Model alokasi (jangan campur)**
- Gunakan salah satu model secara konsisten untuk seluruh item:
  - **Percent model**: semua item punya `alloc_pct` (dan `alloc_budget` boleh diisi sebagai hasil hitung), atau
  - **Budget model** : semua item punya `alloc_budget` (dan `alloc_pct` boleh diisi sebagai hasil turunan).
- Tidak boleh sebagian item hanya `alloc_pct` dan sebagian hanya `alloc_budget`.

**Aturan sum**
- Jika menggunakan `alloc_pct`:
  - `sum(alloc_pct) == 1.0` untuk mode `BUY_*` (toleransi floating: ±0.0001).
- Jika menggunakan `alloc_budget` dan `capital_total` non-null:
  - `sum(alloc_budget) <= capital_total`.

**Aturan cost**
Untuk setiap allocation:
- `estimated_cost == lots_recommended * lot_size * entry_price_ref`
- `estimated_cost <= alloc_budget`
- `remaining_cash == alloc_budget - estimated_cost`
- Semua nilai uang wajib integer IDR dan mengikuti kontrak rounding (Section 3.3).

Jika ada pelanggaran → output dianggap invalid (contract test harus fail).



### 8.9 Konsistensi `slices` & `slice_pct` (lintas-policy)

Untuk setiap kandidat:
- `sizing.slices` wajib integer `>= 1`.
- `sizing.slice_pct` wajib memenuhi `0 < slice_pct <= 1`.
- Default rule: `abs(slice_pct - (1 / slices)) <= 0.0001`.

Jika tidak memenuhi → output invalid (contract test harus fail).

Catatan:
- Jika user memilih mode manual dan memilih `k` ticker dari grup (mis. bukan hanya top_picks),
  UI dapat menggunakan `slice_pct` untuk menghitung `alloc_budget_manual = capital_total * slice_pct`
  lalu sizing lots mengikuti kontrak lot sizing (Section 4) dan fee/rounding (Section 3 & 5).

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
3) `dividend_swing.md`
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


## 12) Persistence & post-mortem (wajib)

Agar watchlist bisa dievaluasi ulang (post-mortem), output JSON **wajib disimpan** setiap trade date.

Kontrak minimal:
- Simpan 1 file JSON per `trade_date` + `policy.selected`.
- Nama file deterministik (contoh): `watchlist_{trade_date}_{policy.selected}.json`.
- Jika engine menghasilkan mode `NO_TRADE`, file tetap disimpan (supaya terlihat kenapa tidak trade).

Field yang wajib sudah cukup untuk audit:
- `trade_date`, `exec_trade_date`, `generated_at`
- `policy.selected`, `policy.policy_version`
- `meta.eod_canonical_ready`, `meta.market_regime`, `meta.notes[]`
- per kandidat: `reason_codes[]`, `timing.*`, `levels.*`, `sizing.*`

Opsional tapi sangat disarankan (kalau nanti ada tempat penyimpanan DB):
- `meta.run_id` (angka/uuid)
- `meta.source_snapshot` (ringkas: canonical run id, coverage, dsb)

Kalau `meta.run_id` ditambahkan:
- jangan ubah struktur kandidat; cukup menambah field baru di `meta` agar backward-compatible.


