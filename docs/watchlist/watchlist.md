# TradeAxis Watchlist — Cross-Policy Contract (EOD-driven)
File: `watchlist.md`  
Build: `w1.0`

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
- `has_position` (bool)
- `position_avg_price` (float), `position_lots` (int)
- `entry_trade_date` (YYYY-MM-DD), `days_held` (trading days)
- `position_state`: `HOLD | REDUCE | EXIT | TRAIL_SL`
- `action_windows[]` (window eksekusi untuk aksi posisi)
- `updated_stop_loss_price` (jika trailing/BE mengubah SL)

### 2.5 Execution snapshot (opsional)
Untuk guard anti-gap/anti-chasing yang dievaluasi **hari eksekusi**:
- `preopen_last_price` (float|null): harga indikatif sebelum market buka pada hari eksekusi.
- `open_or_last_exec` (float|null): derived = `preopen_last_price` (watchlist **tidak boleh** fallback ke `open` EOD).

---

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
    "notes": []
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

## 9) Policy doc loading & failure behavior

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

## 10) Contoh reason codes (sesuai governance)

Contoh ringkas (WEEKLY_SWING):
- `reason_codes`: `["WS_TREND_ALIGN_OK","WS_VOLUME_OK","WS_SETUP_BREAKOUT"]`
- `debug.rank_reason_codes`: `["TREND_STRONG","VOL_RATIO_HIGH","BREAKOUT_BIAS"]`

Tidak boleh:
- `reason_codes`: `["TREND_STRONG","MA_ALIGN_BULL"]`  ❌ (harus prefixed policy)
