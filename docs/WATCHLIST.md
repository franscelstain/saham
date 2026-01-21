# build_id: watch_v1.9

# TradeAxis Watchlist – Design Spec (EOD-driven)  
File: `WATCHLIST.md`

> Tujuan watchlist di TradeAxis **bukan “menentukan beli”**, tapi:
> - Memilih kandidat paling kuat berdasarkan data yang tersedia.
> - Memberi **analisa kuat** + **saran jam beli** yang realistis (window), termasuk **jam yang sebaiknya dihindari**.
> - Menjelaskan **alasan** secara ringkas dan bisa diaudit.
> - Menyimpan “daftar saran & alasan” agar bisa dievaluasi ulang (post-mortem).

Watchlist di bawah ini **berbasis data EOD** sebagai sumber utama, namun boleh memakai:
- Perhitungan turunan dari OHLC (ATR, gap risk, candle structure, dv20, dll).
- Konteks market (IHSG regime, breadth).
- Kalender bursa (hari kerja, libur).
- (Opsional) snapshot intraday ringan (opening range) jika suatu saat dibutuhkan untuk meningkatkan akurasi timing.

---

## 1) Output yang harus dihasilkan watchlist

### 1.1 Output global (per hari)
- `trade_date` *(mengikuti effective date Market Data: cutoff + trading day)*
- `dow` (Mon/Tue/Wed/Thu/Fri)
- `market_regime` (risk-on / neutral / risk-off)
- `market_notes` (contoh: “IHSG down 5D”, “breadth lemah”)

### 1.1.1 Data freshness gate (wajib)
Watchlist ini **EOD-driven**. Karena itu, rekomendasi **NEW ENTRY** hanya boleh keluar jika data EOD **CANONICAL** untuk `trade_date` sudah tersedia.

**Rule deterministik**
1) Tentukan `trade_date` dari **effective date Market Data** (cutoff + trading day).

**Definisi waktu (wajib konsisten)**
- `generated_at`: waktu JSON dibuat.
- `trade_date`: tanggal EOD yang dipakai untuk scoring/plan (basis data canonical).
- `as_of_trade_date`: *trading day terakhir yang seharusnya sudah punya EOD canonical pada saat `generated_at`*.
  - Jika **sebelum cutoff EOD** (pre-open / pagi hari) → `as_of_trade_date` = trading day **kemarin**.
  - Jika **sesudah cutoff + publish sukses** → `as_of_trade_date` = trading day **hari ini**.
- `missing_trading_dates`: daftar trading day dari (`trade_date` .. `as_of_trade_date`) yang belum punya canonical. **Jangan pernah** memasukkan “today” sebelum cutoff.

2) Cek ketersediaan CANONICAL:
   - `ticker_ohlc_daily` tersedia untuk `trade_date` (coverage lolos / publish sudah jalan), dan
   - fitur `ticker_indicators_daily` tersedia untuk `trade_date`.
3) Jika salah satu **tidak tersedia** (missing / held / incomplete window):
   - Set output global: `trade_plan_mode = NO_TRADE` untuk **NEW ENTRY**,
   - Semua ticker hanya boleh berstatus **watch-only** (`entry_style = No-trade`, `size_multiplier = 0.0`),
   - Tambahkan `market_notes`: “EOD CANONICAL belum tersedia (atau held). Entry ditahan.”
   - Wajib tulis reason code global: `GL_EOD_NOT_READY`.

**Catatan**
- Policy `INTRADAY_LIGHT` boleh dipakai **hanya** jika snapshot intraday yang disyaratkan benar-benar ada (kalau tidak ada → tetap NO_TRADE).
- `CARRY_ONLY` untuk posisi yang sudah ada tetap boleh tampil, tetapi **tanpa** membuka posisi baru.


### 1.2 Output per kandidat (per ticker)
Minimal field yang harus ada dalam hasil watchlist:

**A. Identitas & skor**
- `ticker_id`, `ticker_code`
- `rank` (1..N)
- `watchlist_score` (0–100)
- `confidence` (High/Med/Low)
- `setup_type` (Breakout / Pullback / Continuation / Reversal / Base)
- `reason_codes[]` (list kode alasan yang deterministik)

**B. Saran eksekusi (timing & risk)**
- `entry_windows[]` (mis. `["09:20-10:30", "13:35-14:30"]`)
- `avoid_windows[]` (mis. `["09:00-09:15", "15:50-close"]`)
- `entry_style` (Breakout-confirm / Pullback-wait / Reversal-confirm / No-trade)
- `size_multiplier` (1.0 / 0.8 / 0.6 / 0.3)
- `max_positions_today` (1 atau 2, bergantung hari)
- `trade_disabled` (boolean; **true** jika global mode = `NO_TRADE` atau policy melarang entry)
- `trade_disabled_reason` (ringkas; contoh: `EOD_STALE`, `EOD_NOT_READY`, `RISK_OFF`)
- `risk_notes` (1–2 kalimat, bukan essay)

- `preopen_guard` (`PENDING|PASS|FAIL|NA`) — status guard anti-chasing/gap untuk hari eksekusi.
- `preopen_checks[]` (opsional) — daftar check pre-open yang harus dipenuhi (jika `preopen_guard=PENDING`).

**D. Position context (kalau ticker sudah kamu pegang)**
- `has_position` (boolean)
- `position_avg_price`, `position_lots` (opsional jika ada input portfolio)
- `days_held` (trading days)
- `position_state`: `HOLD | REDUCE | EXIT | TRAIL_SL`
- `action_window[]` (jam eksekusi untuk aksi di atas; terutama exit/trim)
- `updated_stop_loss_price` (jika rule trailing/BE mengubah SL)
- `position_notes` (1 kalimat; contoh: “Naikkan SL ke BE karena TP1 tercapai; bias exit Jumat.”)

**E. Disable reason (lebih detail, biar UI tidak ambigu)**
- `trade_disabled_reason_codes[]` (array; contoh: `WS_GAP_UP_BLOCK`, `WS_CHASE_BLOCK_DISTANCE_TOO_FAR`, `WS_WEEKEND_RISK_BLOCK`)

**C. “Checklist sebelum buy” (untuk meyakinkan)**
Walau berbasis EOD, watchlist harus selalu memberi checklist minimal:
- `pre_buy_checklist[]` contoh:
  1) “Spread rapat, bid/ask padat (cek top-5)”
  2) “Tidak gap-up terlalu jauh dari close kemarin”
  3) “Ada follow-through, bukan spike 1 menit”
  4) “Stoploss level jelas (support/MA/ATR)”

> Catatan: checklist ini bukan memerlukan intraday data di sistem. Ini “human checklist” agar saran watchlist realistis.

---

## 2) Filosofi desain: EOD-driven tapi realistis soal jam beli

Karena watchlist menggunakan EOD, watchlist **tidak boleh** bilang “beli jam 09:07 pasti benar”.
Yang benar:
- Saran “jam beli” harus berupa **window** (rentang waktu).
- Setiap window harus punya **alasan** yang diturunkan dari metrik EOD + konteks market.
- Watchlist harus bisa bilang: **“Tidak disarankan entry hari ini”** walau ada kandidat (NO TRADE).



### 2.1 Strategy Policy itu apa (dan kenapa harus eksplisit)
Watchlist punya beberapa **Strategy Policy**. Policy adalah paket aturan end-to-end yang deterministik:
- horizon holding (berapa hari/minggu)
- syarat data (EOD saja atau butuh snapshot intraday)
- kandidat apa yang boleh dipilih
- kapan entry (window) + kapan harus NO TRADE
- exit template (SL/TP/BE/time-stop)
- expiry rule (setup kadaluarsa kapan)

> Prinsip SRP: Market Data = data, Compute EOD = feature/indikator, Watchlist = policy/seleksi/plan.

### 2.2 Daftar Strategy Policy (resmi)
| policy_code | Target | Holding | Data minimum | Kapan dipakai |
|---|---|---|---|---|
| `WEEKLY_SWING` | profit 2–5% mingguan dengan disiplin | 2–5 trading days | EOD canonical + indikator | default (paling sering) |
| `DIVIDEND_SWING` | capture dividen + tetap risk-controlled | 3–10 trading days | EOD + calendar event (ex-date) | hanya saat ada event dividen yang valid |
| `INTRADAY_LIGHT` | timing entry lebih presisi (tanpa full intraday system) | 0–2 trading days | EOD + *opening range snapshot* | opsional, kalau snapshot ada |
| `POSITION_TRADE` | trend-follow 2–8 minggu | 10–40 trading days | EOD + trend quality | saat market risk-on & trend kuat |
| `NO_TRADE` | proteksi modal | n/a | market regime + quality gate | saat data/market tidak mendukung |

### 2.3 Policy: WEEKLY_SWING (default)
**Tujuan:** ambil move mingguan yang realistis, bukan “tebak puncak”.

> Catatan: bagian ini dibuat **policy-centric** (minim shared logic). Semua angka tegas dan bisa langsung dipindah ke config/engine.

#### 2.3.1 Policy execution block (wajib, deterministik)
```yaml
policy_code: WEEKLY_SWING

# 0) Policy identity (kontrak)
policy_version: "1.1"                 # bump jika threshold/logic berubah
horizon:
  target_holding_days: [2, 5]         # trading days
  max_holding_days_hard_cap: 5         # hard cap (override hanya untuk carry-rule weekend)

# 1) Data dependency (wajib/opsional)
# NOTE: WEEKLY_SWING butuh "posisi berjalan" sebagai input bila engine dipakai untuk manage posisi existing.
data_dependency:
  required:
    - ticker_ohlc_daily.canonical: [open, high, low, close, volume, prev_close]
    - ticker_indicators_daily: [ma20, ma50, ma200, rsi14, vol_sma20, vol_ratio, signal_code, signal_age_days]
    - features_cheap: [atr14, atr_pct, gap_pct, dv20, liq_bucket, candle_flags, hhv20, llv20]
    - market_context: [dow, market_regime]
    - market_calendar: [is_trading_day]
  required_when_portfolio_enabled:
    - portfolio_context:
        fields_minimum: [has_position, avg_price, lots, entry_trade_date, days_held]
        fields_for_r_based_rules: [mfe_r, mae_r]     # wajib bila mau time-stop berbasis R secara presisi
  optional:
    - fee_config: [fee_buy, fee_sell]               # untuk net-edge guard & minimum viability
    - tick_size: [tick_size]                        # rounding level harga
    - spread_proxy: [avg_spread_pct]                # jika ada (opsional), untuk viability small-capital

# 2) Threshold contract (angka tegas, bukan “contoh”)
# Semua threshold ini dianggap kontrak. Jika mau diubah, ubah di config dan bump policy_version.
threshold_contract:
  liq:
    dv20_b_min_idr: 5000000000          # 5B → bucket B minimal untuk eligible recommended
    dv20_a_min_idr: 20000000000         # 20B → bucket A
  risk:
    atr_pct_max_drop: 0.10              # >10% drop (terlalu liar untuk weekly swing)
    atr_pct_warn_hi: 0.07               # >7% warning, sizing diturunkan
    rsi_overheat_warn: 74
    max_gap_up_pct_cap: 0.025           # 2.5% cap
    gap_up_atr_mult: 1.00               # gap cap = min(2.5%, 1.0*atr_pct)
  signal:
    max_signal_age_recommended: 3
    max_signal_age_watch_only: 5
  edge:
    rr_tp1_min: 1.00                    # minimum edge untuk jadi recommended
  holding:
    max_holding_by_setup:
      Breakout: 3
      Pullback: 5
      Continuation: 5
      Reversal: 4
      Base: 0

# 3) Hard filters (angka tegas)
hard_filters:
  - id: WS_DATA_COMPLETE_FAIL
    expr: "all(required_columns_not_null)"
    on_fail: DROP
    add_reason: [WS_DATA_COMPLETE_FAIL]

  - id: WS_SIGNAL_STALENESS
    expr: "signal_age_days <= 5"
    on_fail: DROP

  - id: WS_LIQ_MIN_FOR_RECOMMENDED
    expr: "dv20 >= 5000000000"
    on_fail: WATCH_ONLY                 # masih boleh tampil, tapi tidak boleh recommended

  - id: WS_ATR_MAX
    expr: "atr_pct <= 0.10"
    on_fail: DROP

  - id: WS_CORP_ACTION_BLOCK
    expr: "corp_action_suspected == false"
    on_fail: DROP

# 4) Soft filters + score weight override (angka tegas)
soft_filters:
  - id: WS_ATR_WARN
    when: "0.07 < atr_pct and atr_pct <= 0.10"
    effect:
      score_penalty: 6
      max_size_multiplier: 0.80
      add_reason: [WS_VOLATILITY_HIGH_WARN]

  - id: WS_LONG_UPPER_WICK_WARN
    when: "is_long_upper_wick == true"
    effect:
      score_penalty: 5
      add_reason: [WS_DISTRIBUTION_WICK_WARN]

  - id: WS_RSI_OVERHEAT_WARN
    when: "rsi14 >= 74"
    effect:
      score_penalty: 5
      entry_style_override: PULLBACK_WAIT
      add_reason: [WS_RSI_OVERHEAT_WARN]

  - id: WS_MARKET_NEUTRAL_PENALTY
    when: "market_regime == neutral"
    effect:
      score_penalty: 3
      add_reason: [WS_MARKET_NEUTRAL]

score_weight_override:
  trend_quality: 0.30
  momentum_candle: 0.20
  volume_liquidity: 0.20
  risk_quality: 0.20
  market_alignment: 0.10

# 5) Setup allowlist (setup_type yang boleh jadi recommended)
setup_allowlist:
  recommended: [Breakout, Pullback, Continuation, Reversal]
  secondary_only: [Base]               # Base boleh tampil tapi tidak boleh recommended
  forbidden: []

# 6) Lifecycle & position state machine (tegas)
# Ini yang bikin weekly swing punya karakter sendiri (bukan sekadar exit rules).
lifecycle:
  states:
    - PENDING_ENTRY         # kandidat recommended tapi belum entry (menunggu window/guard)
    - LIVE                  # posisi sudah entry dan sedang berjalan
    - MANAGE                # posisi butuh aksi (TRAIL/REDUCE/EXIT) hari ini
    - EXIT_QUEUED           # sudah memenuhi syarat exit; eksekusi di window yang ditentukan
    - EXITED                # posisi selesai
  transitions:
    - from: PENDING_ENTRY
      to: LIVE
      when: "entry_filled == true"
      add_reason: [WS_ENTRY_FILLED]
    - from: LIVE
      to: MANAGE
      when: "any(exit_triggered, trail_update_needed, reduce_needed, review_due)"
      add_reason: [WS_MANAGE_SIGNAL]
    - from: MANAGE
      to: EXIT_QUEUED
      when: "exit_triggered == true"
      add_reason: [WS_EXIT_SIGNAL]
    - from: EXIT_QUEUED
      to: EXITED
      when: "exit_filled == true"
      add_reason: [WS_EXIT_FILLED]

# 6b) Output mapping (wajib, supaya UI tidak ambigu)
output_mapping:
  # mapping lifecycle → field output kandidat/posisi
  from_lifecycle_to_ui:
    PENDING_ENTRY:
      trade_disabled: false
      position_state: null
      preopen_guard_default: PENDING
    LIVE:
      position_state: HOLD
    MANAGE:
      # pilih salah satu berdasarkan driver yang aktif:
      # - trail_update_needed → TRAIL_SL
      # - reduce_needed → REDUCE
      # - exit_triggered → EXIT (atau EXIT_QUEUED)
      position_state_priority: [EXIT, REDUCE, TRAIL_SL, HOLD]
    EXIT_QUEUED:
      position_state: EXIT
    EXITED:
      position_state: EXIT

  # trade_disabled_reason_codes[] harus policy-prefixed:
  trade_disabled_reason_namespace: "WS_* (atau GL_* untuk global gate)"

# 7) Entry rules (termasuk anti-chasing/gap) — angka tegas
entry_rules:
  day_of_week_rules:
    Mon:
      allow_new_entry: true
      max_positions_today: 1
      base_size_multiplier: 0.70
      entry_windows: ["09:35-11:00", "13:35-14:30"]
      avoid_windows: ["09:00-09:20", "11:45-12:00", "15:50-close"]
      add_reason: [WS_MONDAY_FAKE_MOVE_BIAS]
    Tue:
      allow_new_entry: true
      max_positions_today: 2
      base_size_multiplier: 1.00
      entry_windows: ["09:20-10:30", "13:35-14:30"]
      avoid_windows: ["09:00-09:15", "11:45-12:00", "15:50-close"]
    Wed:
      allow_new_entry: true
      max_positions_today: 1
      base_size_multiplier: 0.85
      entry_windows: ["09:30-10:45", "13:35-14:15"]
      avoid_windows: ["09:00-09:20", "11:45-12:00", "15:50-close"]
      add_reason: [WS_MIDWEEK_SELECTIVE]
    Thu:
      allow_new_entry: true
      max_positions_today: 1
      base_size_multiplier: 0.55
      entry_windows: ["09:35-10:30", "13:40-14:15"]
      avoid_windows: ["09:00-09:25", "11:45-12:00", "15:35-close"]
      add_reason: [WS_LATE_WEEK_ENTRY_PENALTY]
    Fri:
      allow_new_entry: false
      max_positions_today: 0
      base_size_multiplier: 0.00
      entry_windows: []
      avoid_windows: ["09:00-close"]
      add_reason: [WS_FRIDAY_NO_ENTRY_DEFAULT]

  # Anti-chasing/gap guard berbasis EOD (pre-open decision)
  # - Gap guard: memblokir entry bila open/last (indikasi pre-open) terlalu jauh dari prev_close.
  # - No-chase breakout: memblokir entry bila close EOD sudah terlalu jauh dari trigger.
  anti_chasing_and_gap_guards:
    - id: WS_PREOPEN_PRICE_MISSING
      expr: "open_or_last is null"
      action: "DISABLE_AUTOMATED_ENTRY"
      add_reason: [WS_PREOPEN_PRICE_MISSING]

    - id: WS_GAP_UP_BLOCK
      expr: "open_or_last > prev_close * (1 + min(0.025, 1.00*atr_pct))"
      action: "DISABLE_ENTRY_FOR_TODAY"
      add_reason: [WS_GAP_UP_BLOCK]

    - id: WS_NO_CHASE_BREAKOUT
      expr: "setup_type == Breakout and close > (breakout_trigger + 0.50*atr14)"
      action: "DOWNGRADE_TO_PULLBACK_OR_WATCH_ONLY"
      add_reason: [WS_CHASE_BLOCK_DISTANCE_TOO_FAR]

    - id: WS_CHASE_PULLBACK_TOO_HIGH
      expr: "setup_type == Pullback and close > (pullback_value_zone_high + 0.35*atr14)"
      action: "WATCH_ONLY"
      add_reason: [WS_CHASE_BLOCK_PULLBACK_TOO_HIGH]

    - id: WS_MIN_EDGE_GUARD
      expr: "rr_tp1 < 1.00"
      action: "WATCH_ONLY"
      add_reason: [WS_MIN_EDGE_FAIL]

    - id: WS_STALE_SIGNAL_DOWNGRADE
      expr: "signal_age_days > 3"
      action: "DOWNGRADE_CONFIDENCE_AND_SIZE"
      add_reason: [WS_SIGNAL_STALE_WARN]

# 8) Exit rules (time stop / max holding / trailing) — angka tegas
exit_rules:
  time_stop:
    t_plus_2_days:
      condition: "(mfe_r is not null and mfe_r < 0.5) or (mfe_r is null and ret_since_entry_pct is not null and ret_since_entry_pct < 0.010)"
      action: "REDUCE_50_OR_EXIT"
      add_reason: [WS_TIME_STOP_T2]
    t_plus_3_days:
      condition: "(mfe_r is not null and mfe_r < 0.5) or (mfe_r is null and ret_since_entry_pct is not null and ret_since_entry_pct < 0.010)"
      action: "EXIT"
      add_reason: [WS_TIME_STOP_T3]

  max_holding_days_by_setup:
    Breakout: 3
    Pullback: 5
    Continuation: 5
    Reversal: 4
    Base: 0

  trailing_rule:
    after_tp1:
      action: "MOVE_SL_TO_BE_OR_ENTRY_PLUS_0_2R"
      add_reason: [WS_MOVE_SL_TO_BE]

  friday_exit_bias:
    when: "dow == Fri and tp1_not_hit"
    action: "PRIORITIZE_EXIT"
    add_reason: [WS_FRIDAY_EXIT_BIAS]

  weekend_rule:
    allow_carry_weekend_only_if:
      condition: "confidence == High and sl_is_be_or_trailing == true and trend_quality_strong == true"
    otherwise:
      action: "BLOCK_CARRY_WEEKEND"
      add_reason: [WS_WEEKEND_RISK_BLOCK]

# 9) Sizing defaults + minimum trade viability (weekly swing modal kecil) — angka tegas
sizing_defaults:
  risk_per_trade_pct_default: 0.010      # 1.0% modal per trade
  max_positions_total_default: 2
  size_multiplier_caps:
    High: 1.00
    Med: 0.80
    Low: 0.60
  liq_bucket_caps:
    A: 1.00
    B: 0.80
    C: 0.00

min_trade_viability:
  # Guard ini mencegah weekly swing modal kecil “buang-buang fee/spread”.
  # Jika gagal, action = WATCH_ONLY (atau NO_TRADE jika semua gagal).
  # Kontrak evaluasi:
  # - Jika `capital_total` tidak tersedia → skip semua rule viability dan tambahkan reason `WS_VIABILITY_NOT_EVAL_NO_CAPITAL`.
  # - Jika sizing engine tidak dijalankan → `lots_recommended` dan `alloc_amount_idr` dianggap null dan rule yang membutuhkannya otomatis FAIL (WATCH_ONLY).
  - id: WS_MIN_LOTS_1
    expr: "lots_recommended >= 1"
    on_fail: WATCH_ONLY
    add_reason: [WS_MIN_VIABLE_TRADE_FAIL]

  - id: WS_MIN_ALLOC_AMOUNT
    expr: "alloc_amount_idr >= 300000"          # default 300k (ubah via config)
    on_fail: WATCH_ONLY
    add_reason: [WS_MIN_ALLOC_TOO_SMALL]

  - id: WS_MIN_NET_EDGE_AFTER_FEE
    expr: "fee_config_present == false or expected_net_tp1_pct >= 0.0075"
    on_fail: WATCH_ONLY
    add_reason: [WS_FEE_EDGE_FAIL]

  - id: WS_MAX_SPREAD_GUARD
    expr: "avg_spread_pct is null or avg_spread_pct <= 0.004"   # <=0.40% jika tersedia
    on_fail: WATCH_ONLY
    add_reason: [WS_SPREAD_TOO_WIDE_WARN]

# 10) “Kapan review” (SOP) weekly swing — wajib
review_sop:
  # Review ini menghasilkan flag di output: review_due=true + review_reason_codes[].
  # Kalau portfolio_context tidak ada, review SOP tetap jalan untuk kandidat (PENDING_ENTRY) dan posisi (jika bisa).
  intraday_review_windows:
    - window: "10:30-10:45"
      purpose: "cek follow-through & gap/spread (human checklist)"
    - window: "14:25-14:40"
      purpose: "cek late-session reversal risk; siapkan exit jika sinyal jelek"
  eod_review:
    when: "after_market_close"
    actions:
      - "recompute_position_state"
      - "apply_time_stop"
      - "apply_trailing_update"
      - "flag_review_due_if_rules_hit"
  triggers:
    - id: WS_REVIEW_DUE_TPLUS2_NO_PROGRESS
      when: "days_held >= 2 and mfe_r < 0.5"
      flag: review_due
      add_reason: [WS_REVIEW_DUE_TIME_STOP_T2]
    - id: WS_REVIEW_DUE_WED_SELECTIVE
      when: "dow == Wed and has_position == true and tp1_not_hit == true"
      flag: review_due
      add_reason: [WS_REVIEW_DUE_MIDWEEK]
    - id: WS_REVIEW_DUE_THU_WEEKEND_RISK
      when: "dow == Thu and has_position == true and sl_is_be_or_trailing == false"
      flag: review_due
      add_reason: [WS_REVIEW_DUE_WEEKEND_RISK]
    - id: WS_REVIEW_DUE_FRI_EXIT_BIAS
      when: "dow == Fri and has_position == true"
      flag: review_due
      add_reason: [WS_REVIEW_DUE_FRIDAY]

# 11) Reason codes khusus policy (biar output tidak ambigu)
policy_reason_codes:
  prefix: WS_
  catalog:
    # Data / gating
    - WS_DATA_COMPLETE_FAIL
    - WS_SIGNAL_STALE_WARN
    - WS_CORP_ACTION_BLOCK

    # Liquidity / risk
    - WS_LIQ_MIN_FAIL
    - WS_VOLATILITY_HIGH_WARN
    - WS_RSI_OVERHEAT_WARN
    - WS_DISTRIBUTION_WICK_WARN
    - WS_SPREAD_TOO_WIDE_WARN

    # Entry guards
    - WS_GAP_UP_BLOCK
    - WS_CHASE_BLOCK_DISTANCE_TOO_FAR
    - WS_CHASE_BLOCK_PULLBACK_TOO_HIGH
    - WS_MIN_EDGE_FAIL

    - WS_PREOPEN_PRICE_MISSING
    - WS_VIABILITY_NOT_EVAL_NO_CAPITAL
    - WS_SETUP_BREAKOUT
    - WS_SETUP_PULLBACK
    - WS_SETUP_CONTINUATION
    - WS_SETUP_REVERSAL
    - WS_TREND_ALIGN_OK
    - WS_VOLUME_OK
    - WS_LIQ_OK
    - WS_RR_OK
    - WS_FEE_EDGE_FAIL
    - WS_MIN_VIABLE_TRADE_FAIL
    - WS_MIN_ALLOC_TOO_SMALL

    # Lifecycle / management
    - WS_ENTRY_FILLED
    - WS_MANAGE_SIGNAL
    - WS_TRAIL_UPDATE
    - WS_EXIT_SIGNAL
    - WS_EXIT_FILLED
    - WS_CARRY_ONLY_ACTIVE
    - WS_MAX_POSITIONS_CUT_BY_EXISTING

    # Exit / lifecycle drivers
    - WS_TIME_STOP_T2
    - WS_TIME_STOP_T3
    - WS_MOVE_SL_TO_BE
    - WS_FRIDAY_NO_ENTRY_DEFAULT
    - WS_FRIDAY_EXIT_BIAS
    - WS_WEEKEND_RISK_BLOCK
    - WS_MAX_HOLDING_REACHED

    # Review SOP
    - WS_REVIEW_DUE_TIME_STOP_T2
    - WS_REVIEW_DUE_MIDWEEK
    - WS_REVIEW_DUE_WEEKEND_RISK
    - WS_REVIEW_DUE_FRIDAY
```

#### 2.3.2 Kontrak operasional WEEKLY_SWING (yang wajib dipatuhi engine & UI)

Bagian YAML di atas adalah “mesin keputusan” WEEKLY_SWING. Poin penting yang membedakan dari policy lain:

1) **Exit & lifecycle tegas**
- WEEKLY_SWING punya **state machine** (`PENDING_ENTRY → LIVE → MANAGE → EXIT_QUEUED → EXITED`) dan setiap transisi wajib memunculkan reason code lifecycle (`WS_ENTRY_FILLED`, `WS_EXIT_SIGNAL`, dll).
- Exit tidak cuma “aturan”, tapi **mengubah `position_state`** dan **menghasilkan action window** (terutama Jumat dan menjelang weekend).

2) **Entry plan anti-chasing berbasis EOD**
- Guard gap-up pre-open (`WS_GAP_UP_BLOCK`) memblokir entry bila open/last terlalu jauh dari `prev_close` (cap 2.5% atau 1.0×ATR%).
- Guard “no chase breakout” (`WS_CHASE_BLOCK_DISTANCE_TOO_FAR`) memblokir entry bila close EOD sudah terlalu jauh di atas trigger (>0.5×ATR14).
- Pullback juga punya chase guard (`WS_CHASE_BLOCK_PULLBACK_TOO_HIGH`) supaya tidak entry “setelah sudah jalan”.

3) **Threshold jadi kontrak**
- Semua angka penting dipindahkan ke `threshold_contract` (bukan “contoh”).
- Perubahan threshold **wajib** bump `policy_version` agar hasil mudah diaudit lintas build.

4) **Portfolio context wajib untuk “posisi berjalan”**
- Jika portfolio integration diaktifkan, input minimal wajib ada: `avg_price`, `lots`, `entry_trade_date`, `days_held`.
- Jika ingin time-stop yang presisi berbasis R, `mfe_r/mae_r` harus tersedia (atau engine harus fallback ke proxy non-R dan menambah reason `WS_DATA_COMPLETE_FAIL` untuk field R).

5) **Lot sizing + minimum trade viability (modal kecil)**
- WEEKLY_SWING tidak boleh menyarankan trade kalau `lots_recommended < 1` atau alokasi terlalu kecil (default 300k).
- Jika fee config tersedia, harus lolos `expected_net_tp1_pct >= 0.75%` (default) agar tidak habis oleh fee/spread.
- Jika ada spread proxy, spread terlalu lebar akan downgrade ke watch-only.

6) **SOP “kapan review”**
- Ada review windows (10:30–10:45 dan 14:25–14:40) sebagai SOP checklist.
- Ada trigger review_due deterministik: T+2 no progress, Rabu midweek, Kamis weekend risk, Jumat exit bias.

7) **Reason codes khusus WEEKLY_SWING**
- Reason codes dipisahkan dari policy lain dengan prefix `WS_` dan katalog diperluas untuk:
  - data/gating, entry guard, viability guard,
  - lifecycle/management,
  - exit drivers dan review SOP.

> Output JSON/UI yang benar: WEEKLY_SWING harus bisa menampilkan **status** yang tidak ambigu (mis. `trade_disabled`, `position_state`, `review_due`) plus reason codes yang menjelaskan *kenapa*, bukan cuma “skor”.

### 2.4 Policy: DIVIDEND_SWING (event-driven)
**Tujuan:** capture dividen **tanpa** bunuh diri karena gap risk.

> Policy ini **wajib** punya event (ex-date). Kalau data event tidak ada → policy nonaktif (fallback ke WEEKLY_SWING atau NO_TRADE).

#### 2.4.1 Policy execution block (wajib, deterministik)
```yaml
policy_code: DIVIDEND_SWING

# 1) Data dependency (wajib/opsional)
data_dependency:
  required:
    - ticker_ohlc_daily.canonical: [open, high, low, close, volume, prev_close]
    - ticker_indicators_daily: [ma20, ma50, ma200, rsi14, vol_ratio, atr14, atr_pct, dv20, liq_bucket]
    - market_context: [dow, market_regime]
    - dividend_calendar: [ticker_id, ex_date, pay_date, cash_dividend, yield_est]
  optional:
    - corporate_actions: [is_adjusted, split_factor, rights_issue_flag]
    - fee_config: [fee_buy, fee_sell]
    - tick_size: [tick_size]

# 2) Hard filters (angka tegas)
hard_filters:
  - id: DS_EVENT_PRESENT
    expr: "has_dividend_event == true"
    on_fail: POLICY_INACTIVE
  - id: DS_EXDATE_WINDOW
    expr: "ex_date in [trade_date+1 .. trade_date+3]"   # target entry H-3..H-1
    on_fail: DROP
  - id: DS_LIQ_A_ONLY
    expr: "liq_bucket == 'A'"
    on_fail: DROP
  - id: DS_ATR_MAX_TIGHT
    expr: "atr_pct <= 0.07"
    on_fail: DROP
  - id: DS_MARKET_NOT_RISK_OFF
    expr: "market_regime != risk-off"
    on_fail: NO_TRADE
  - id: DS_CORP_ACTION
    expr: "corp_action_suspected == false"
    on_fail: DROP

# 3) Soft filters + score weight override (angka tegas)
soft_filters:
  - id: DS_RSI_OVERHEAT_WARN
    when: "rsi14 >= 75"
    effect:
      score_penalty: 6
      entry_style_override: PULLBACK_WAIT
      add_reason: [RSI_OVERHEAT_WARN]
  - id: DS_SPREAD_RISK_WARN
    when: "liq_bucket == 'A' and dv20 < 25_000_000_000" # A bawah (lebih rawan spread melebar)
    effect:
      score_penalty: 3
      add_reason: [SPREAD_RISK_WARN]

# score weight override (lebih event-driven, lebih risk-aware)
score_weight_override:
  trend_quality: 0.25
  momentum_candle: 0.10
  volume_liquidity: 0.20
  risk_quality: 0.30
  market_alignment: 0.10
  event_alignment: 0.05

# 4) Setup allowlist (setup_type mana yang boleh jadi recommended)
setup_allowlist:
  recommended: [Pullback, Continuation]
  secondary_only: [Breakout]     # breakout dekat ex-date sering gap risk; tampil tapi lebih hati-hati
  forbidden: [Reversal, Base]    # hindari reversal/base untuk event trade

# 5) Entry rules (termasuk anti-chasing/gap)
entry_rules:
  allowed_entry_days_relative_to_exdate: [-3, -2, -1]   # H-3..H-1
  disallow_entry_on_exdate: true                        # default: jangan entry H0
  entry_windows: ["09:35-11:00", "13:35-14:15"]         # hindari terlalu pagi & terlalu sore
  avoid_windows: ["09:00-09:25", "15:00-close"]         # hindari late-day risk jelang ex-date
  gap_guard:
    expr: "open_or_last > prev_close * (1 + 0.015)"     # lebih ketat dari weekly swing
    action: DISABLE_ENTRY_FOR_TODAY
    add_reason: [DS_GAP_UP_BLOCK]
  anti_chasing:
    expr: "distance_to_entry_trigger > 0.35*atr14"
    action: DOWNGRADE_TO_WATCH_ONLY
    add_reason: [DS_CHASE_BLOCK]

# 6) Exit rules (time stop / max holding / trailing) — event-driven
exit_rules:
  primary_exit_bias:
    - when: "trade_date == ex_date - 1"
      action: "TAKE_PROFIT_OR_EXIT_BY_AFTERNOON"
      add_reason: [DS_EXIT_H_MINUS_1]
    - when: "trade_date == ex_date"
      action: "EXIT_EARLY_SESSION_IF_NOT_STRONG"
      add_reason: [DS_EXIT_ON_EXDATE]
  max_holding_days: 10
  trailing_rule:
    after_tp1:
      action: "MOVE_SL_TO_BE"
      add_reason: [MOVE_SL_TO_BE]
  time_stop:
    t_plus_3_days:
      condition: "(mfe_r is not null and mfe_r < 0.5) or (mfe_r is null and ret_since_entry_pct is not null and ret_since_entry_pct < 0.010)"
      action: EXIT
      add_reason: [DS_TIME_STOP_T3]

# 7) Sizing defaults
sizing_defaults:
  risk_per_trade_pct_default: 0.0075     # 0.75% (lebih ketat karena gap risk)
  max_positions_total_default: 1
  size_multiplier_caps:
    High: 0.90
    Med: 0.70
    Low: 0.50

# 8) Reason codes khusus policy
policy_reason_codes:
  prefix: DS_
  codes:
    - DS_EVENT_PRESENT_FAIL
    - DS_EXDATE_WINDOW_FAIL
    - DS_LIQ_A_ONLY_FAIL
    - DS_ATR_MAX_TIGHT_FAIL
    - DS_MARKET_RISK_OFF_NO_TRADE
    - DS_GAP_UP_BLOCK
    - DS_CHASE_BLOCK
    - DS_EXIT_H_MINUS_1
    - DS_EXIT_ON_EXDATE
    - DS_TIME_STOP_T3
```

#### 2.4.2 Catatan implementasi
- Kalau `dividend_calendar` belum ada → policy **wajib** nonaktif (jangan “dipaksa” pakai indikator saja).
- Target utama: risk control. Kalau ada gap up/down besar jelang ex-date, lebih baik keluar cepat daripada berharap “mean reversion”.

### 2.5 Policy: INTRADAY_LIGHT (opsional)
**Tujuan:** memperbaiki timing entry untuk setup EOD kuat **tanpa** membangun sistem intraday penuh.

**Syarat mutlak:** ada *opening range snapshot* (09:00–09:15 atau 09:00–09:30).

#### 2.5.1 Policy execution block (wajib, deterministik)
```yaml
policy_code: INTRADAY_LIGHT

# 1) Data dependency (wajib/opsional)
data_dependency:
  required:
    - ticker_ohlc_daily.canonical: [open, high, low, close, volume, prev_close]
    - ticker_indicators_daily: [ma20, ma50, ma200, rsi14, vol_ratio, atr14, atr_pct, dv20, liq_bucket, setup_type]
    - market_context: [dow, market_regime]
    - intraday_opening_range_snapshot:
        fields: [or_high, or_low, or_volume, gap_pct_real]
        window: "09:00-09:15|09:00-09:30"
  optional:
    - tick_size: [tick_size]
    - fee_config: [fee_buy, fee_sell]

# 2) Hard filters (angka tegas)
hard_filters:
  - id: IL_SNAPSHOT_PRESENT
    expr: "opening_range_snapshot_available == true"
    on_fail: POLICY_INACTIVE
  - id: IL_LIQ_MIN
    expr: "liq_bucket in ['A','B']"
    on_fail: DROP
  - id: IL_ATR_MAX
    expr: "atr_pct <= 0.09"
    on_fail: DROP
  - id: IL_MARKET_NOT_RISK_OFF
    expr: "market_regime != risk-off"
    on_fail: NO_TRADE

# 3) Soft filters + score weight override
soft_filters:
  - id: IL_GAP_REAL_WARN
    when: "abs(gap_pct_real) >= 0.020"
    effect:
      score_penalty: 5
      add_reason: [IL_GAP_REAL_WARN]
  - id: IL_OR_RANGE_TOO_WIDE
    when: "(or_high - or_low) / prev_close >= 0.020"
    effect:
      score_penalty: 4
      add_reason: [IL_OPENING_RANGE_WIDE_WARN]

score_weight_override:
  trend_quality: 0.25
  momentum_candle: 0.15
  volume_liquidity: 0.20
  risk_quality: 0.15
  market_alignment: 0.10
  intraday_confirmation: 0.15

# 4) Setup allowlist
setup_allowlist:
  recommended: [Breakout, Pullback, Continuation]
  secondary_only: [Reversal]
  forbidden: [Base]

# 5) Entry rules (intraday confirmation + anti fake) — angka tegas
entry_rules:
  base_entry_windows: ["09:20-10:30"]     # fokus sesi pagi setelah OR terbentuk
  avoid_windows: ["09:00-09:20", "15:30-close"]
  confirmation_rules:
    Breakout:
      trigger: "price_breaks_above(or_high + 1_tick)"
      invalidation: "close_back_below(or_high) within 10m"
      action_on_invalidation: NO_TRADE_FOR_TODAY
      add_reason: [IL_OR_BREAKOUT_FAIL]
    Pullback:
      trigger: "reclaim(or_mid) then holds_above(or_mid) for 5m"
      invalidation: "breaks_below(or_low)"
      add_reason: [IL_PULLBACK_FAIL]
    Continuation:
      trigger: "holds_above(or_high) and vol_opening >= 0.8 * expected_opening_vol"
      invalidation: "returns_inside(or_range)"
      add_reason: [IL_CONT_FAIL]
  gap_guard:
    expr: "gap_pct_real > 0.025"
    action: DISABLE_ENTRY_FOR_TODAY
    add_reason: [IL_GAP_UP_BLOCK]
  anti_chasing:
    expr: "distance_from_or_high > 0.30*atr14"
    action: WAIT_PULLBACK_ONLY
    add_reason: [IL_CHASE_BLOCK]

# 6) Exit rules
exit_rules:
  max_holding_days: 2
  time_stop_intraday:
    condition: "no_follow_through_by_11_00"
    action: "EXIT_OR_REDUCE"
    add_reason: [IL_NO_FOLLOW_THROUGH_MORNING]
  trailing_rule:
    after_tp1:
      action: "MOVE_SL_TO_BE"
      add_reason: [MOVE_SL_TO_BE]

# 7) Sizing defaults
sizing_defaults:
  risk_per_trade_pct_default: 0.006       # 0.6% (lebih ketat karena entry cepat)
  max_positions_total_default: 1
  size_multiplier_caps:
    High: 0.85
    Med: 0.65
    Low: 0.45

# 8) Reason codes khusus policy
policy_reason_codes:
  prefix: IL_
  codes:
    - IL_SNAPSHOT_PRESENT_FAIL
    - IL_GAP_REAL_WARN
    - IL_OPENING_RANGE_WIDE_WARN
    - IL_OR_BREAKOUT_FAIL
    - IL_PULLBACK_FAIL
    - IL_CONT_FAIL
    - IL_GAP_UP_BLOCK
    - IL_CHASE_BLOCK
    - IL_NO_FOLLOW_THROUGH_MORNING
```

#### 2.5.2 Batasan keras
- Ini **bukan scalping**. Kalau konfirmasi tidak kejadian di window → `NO_TRADE` untuk ticker itu.
- Semua level SL/TP tetap mengacu ke plan EOD (Bagian 20), intraday hanya memutuskan *kapan* entry boleh dilakukan.

### 2.6 Policy: POSITION_TRADE (2–8 minggu)
**Tujuan:** ride trend besar (trend-follow), bukan trading mingguan.

#### 2.6.1 Policy execution block (wajib, deterministik)
```yaml
policy_code: POSITION_TRADE

# 1) Data dependency
data_dependency:
  required:
    - ticker_ohlc_daily.canonical: [open, high, low, close, volume, prev_close]
    - ticker_indicators_daily: [ma20, ma50, ma200, rsi14, vol_ratio, atr14, atr_pct, dv20, liq_bucket]
    - market_context: [dow, market_regime]
    - trend_features: [ma20_slope, ma50_slope, distance_to_ma50, drawdown_from_20d_high]
  optional:
    - breadth: [adv_decl, new_high_20d, new_low_20d]
    - portfolio_context: [has_position, days_held]

# 2) Hard filters
hard_filters:
  - id: PT_MARKET_RISK_ON_ONLY
    expr: "market_regime == risk-on"
    on_fail: NO_TRADE
  - id: PT_LIQ_MIN
    expr: "liq_bucket in ['A','B']"
    on_fail: DROP
  - id: PT_TREND_ALIGN
    expr: "ma20 > ma50 and ma50 > ma200"
    on_fail: DROP
  - id: PT_SLOPE_MIN
    expr: "ma50_slope >= 0.0005"       # ≥ 0.05% per trading day (proxy)
    on_fail: DROP
  - id: PT_ATR_MAX
    expr: "atr_pct <= 0.08"
    on_fail: DROP

# 3) Soft filters + score weight override
soft_filters:
  - id: PT_RSI_TOO_HIGH_WARN
    when: "rsi14 >= 78"
    effect:
      score_penalty: 5
      entry_style_override: PULLBACK_WAIT
      add_reason: [RSI_OVERHEAT_WARN]
  - id: PT_DRAWDOWN_WARN
    when: "drawdown_from_20d_high <= -0.08"   # >8% dari high 20D
    effect:
      score_penalty: 4
      add_reason: [PT_DRAWDOWN_WARN]

score_weight_override:
  trend_quality: 0.45
  momentum_candle: 0.10
  volume_liquidity: 0.15
  risk_quality: 0.20
  market_alignment: 0.10

# 4) Setup allowlist
setup_allowlist:
  recommended: [Pullback, Continuation]
  secondary_only: [Breakout]
  forbidden: [Reversal, Base]

# 5) Entry rules
entry_rules:
  allow_new_entry: true
  preferred_days: [Mon, Tue, Wed]         # avoid late week entry untuk posisi trade
  entry_windows: ["09:35-11:15", "13:35-14:30"]
  avoid_windows: ["09:00-09:25", "15:30-close"]
  anti_chasing:
    expr: "distance_to_ma20 > 1.2*atr14"  # terlalu jauh dari mean
    action: WAIT_PULLBACK_ONLY
    add_reason: [PT_CHASE_BLOCK]
  gap_guard:
    expr: "gap_pct_real_or_open > 0.020"
    action: DISABLE_ENTRY_FOR_TODAY
    add_reason: [PT_GAP_UP_BLOCK]

# 6) Exit rules (lebih longgar)
exit_rules:
  max_holding_days: 40
  trailing_rule:
    method: "ATR_TRAIL"
    params:
      trail_atr_mult: 2.0
    add_reason: [PT_ATR_TRAIL]
  time_stop:
    t_plus_10_days:
      condition: "(mfe_r is not null and mfe_r < 0.5) or (mfe_r is null and ret_since_entry_pct is not null and ret_since_entry_pct < 0.010)"
      action: "REDUCE_OR_EXIT"
      add_reason: [PT_TIME_STOP_T10]

# 7) Sizing defaults
sizing_defaults:
  risk_per_trade_pct_default: 0.0125      # 1.25% (lebih long-horizon, tapi tetap disiplin)
  max_positions_total_default: 2
  size_multiplier_caps:
    High: 1.00
    Med: 0.85
    Low: 0.65

# 8) Reason codes khusus policy
policy_reason_codes:
  prefix: PT_
  codes:
    - PT_MARKET_RISK_ON_ONLY_FAIL
    - PT_TREND_ALIGN_FAIL
    - PT_SLOPE_MIN_FAIL
    - PT_CHASE_BLOCK
    - PT_GAP_UP_BLOCK
    - PT_ATR_TRAIL
    - PT_TIME_STOP_T10
    - PT_DRAWDOWN_WARN
```

#### 2.6.2 Catatan implementasi
- Policy ini hanya aktif saat market jelas risk-on. Kalau tidak, jangan “maksa” jadi position trade.
- Exit template default adalah trailing (bukan TP2 fixed) karena targetnya trend-follow.

### 2.7 Policy: NO_TRADE (proteksi modal)
**Tujuan:** proteksi modal. Policy ini **bukan** “tidak ada output”, tapi output deterministik yang memaksa sistem *tidak membuka posisi baru*.

#### 2.7.1 Policy execution block (wajib, deterministik)
```yaml
policy_code: NO_TRADE

# 1) Data dependency
data_dependency:
  required:
    - market_context: [market_regime, dow]
    - data_freshness_gate: [eod_canonical_ready, missing_trading_dates]
  optional:
    - portfolio_context: [has_position]   # untuk menentukan apakah mode = CARRY_ONLY

# 2) Hard filters (trigger NO_TRADE) — angka tegas
hard_triggers:
  - id: NT_EOD_NOT_READY
    expr: "eod_canonical_ready == false"
    action: NO_TRADE
    add_reason: [NT_EOD_NOT_READY]
  - id: NT_MARKET_RISK_OFF
    expr: "market_regime == risk-off"
    action: NO_TRADE
    add_reason: [NT_MARKET_RISK_OFF]
  - id: NT_BREADTH_CRASH
    expr: "breadth_new_low_20d >= 120 and breadth_adv_decl_ratio <= 0.40"  # jika breadth tersedia
    action: NO_TRADE
    add_reason: [NT_BREADTH_CRASH]

# 3) Soft filters (di NO_TRADE tidak relevan untuk scoring) — tetap eksplisit
soft_filters: []

# 4) Setup allowlist
setup_allowlist:
  recommended: []
  secondary_only: []
  forbidden: [Breakout, Pullback, Continuation, Reversal, Base]

# 5) Entry rules
entry_rules:
  allow_new_entry: false
  entry_windows: []
  avoid_windows: ["09:00-close"]
  trade_disabled: true
  size_multiplier: 0.0
  max_positions_today: 0

# 6) Exit rules (untuk posisi existing)
exit_rules:
  if_has_position:
    action: "MANAGE_ONLY"
    allowed_actions: [HOLD, REDUCE, EXIT, TRAIL_SL]
    add_reason: [NT_CARRY_ONLY_MANAGEMENT]

# 7) Sizing defaults
sizing_defaults:
  risk_per_trade_pct_default: 0.0
  max_positions_total_default: 0

# 8) Reason codes khusus policy
policy_reason_codes:
  prefix: NT_
  codes:
    - NT_EOD_NOT_READY
    - NT_MARKET_RISK_OFF
    - NT_BREADTH_CRASH
    - NT_CARRY_ONLY_MANAGEMENT
```

#### 2.7.2 Invariant output (wajib)
Kalau policy aktif:
- Semua kandidat harus `trade_disabled = true`
- `entry_windows = []`, `avoid_windows = []` (atau `["09:00-close"]` secara global)
- `size_multiplier = 0.0`, `max_positions_today = 0`
- `entry_style = "No-trade"`

### 2.8 Urutan pemilihan policy (deterministik)
Agar hasil watchlist konsisten (dan tidak “campur aduk” antar policy), pemilihan policy harus **satu arah**:

1) Jika `EOD CANONICAL` belum ready (freshness gate gagal) → **NO_TRADE** (atau `CARRY_ONLY` jika ada posisi existing).
2) Jika `market_regime = risk-off` → **NO_TRADE**.
3) Jika ada event dividen valid + lolos hard filters → **DIVIDEND_SWING**.
4) Jika snapshot intraday tersedia + kandidat EOD kuat → **INTRADAY_LIGHT** (opsional).
5) Jika market risk-on & trend quality tinggi → boleh override ke **POSITION_TRADE** (mode long horizon).
6) Default → **WEEKLY_SWING**.

> Ini urutan default. Kalau nanti mau “lock mode” (mis. minggu ini hanya weekly swing), override dilakukan di layer config/UI (bukan dengan memodifikasi rule internal policy).

---

## 3) Data yang dibutuhkan (akurat & audit-able)

### 3.1 Data mentah wajib (ticker_ohlc_daily)
Catatan penting:
- `ticker_ohlc_daily` dianggap **CANONICAL output** dari Market Data.
- Watchlist **tidak boleh** membaca RAW market data; hanya canonical.

Per `ticker_id + trade_date`:
- `open`, `high`, `low`, `close`, `volume`

Wajib ada constraint:
- unik `(ticker_id, trade_date)`
- validasi: `low <= open/close <= high`
- `volume >= 0`

### 3.2 Hasil compute EOD wajib (ticker_indicators_daily)
Minimal:
- `ma20`, `ma50`, `ma200`
- `rsi14`
- `vol_sma20`, `vol_ratio`
- `signal_code`, `signal_label`
- `signal_first_seen_date`, `signal_age_days` (streak)

### 3.3 Perhitungan tambahan (sangat disarankan) – murah tapi meningkatkan akurasi
> Semua bisa dihitung dari OHLC (tanpa intraday).

**A. Volatilitas & gap risk**
- `prev_close`
- `gap_pct = (open - prev_close)/prev_close`
- `tr` (true range)
- `atr14`
- `atr_pct = atr14/close`
- `range_pct = (high-low)/close`

**B. Candle structure (jebakan open vs continuation)**
- `body_pct = abs(close-open)/(high-low)`
- `upper_wick_pct`, `lower_wick_pct`
- flag: `is_long_upper_wick`, `is_long_lower_wick`, `is_inside_day`, `is_engulfing`

**C. Likuiditas proxy (untuk masalah “match/antrian”)**
- `dvalue = close * volume`
- `dv20 = SMA20(dvalue)`
- `liq_bucket` (A/B/C berdasarkan dv20)
  - **Default bucket (IDR, bisa diubah via config):**
    - **A (liquid):** `dv20 >= 20_000_000_000` (≥ 20B)
    - **B (ok):** `5_000_000_000 <= dv20 < 20_000_000_000` (5B – <20B)
    - **C (illiquid):** `dv20 < 5_000_000_000` (<5B)
  - **Catatan wajib:**
    - `dv20` dihitung dari **20 trading days** terakhir (exclude today) menggunakan **CANONICAL EOD**.
    - Threshold harus berasal dari config (mis. `liq.dv20_a_min`, `liq.dv20_b_min`) dan boleh dituning setelah lihat distribusi pasar & constraints broker.


### 3.4 Konteks market wajib
**market_calendar**
- `cal_date`, `is_trading_day`, `holiday_name`

**market_index_daily** (minimal IHSG)
- `trade_date`
- `close`, `ret_1d`, `ret_5d`
- `ma20`, `ma50` (opsional tapi disarankan)
- `regime` (risk-on/neutral/risk-off)

**market_breadth_daily** (opsional tapi kuat)
- `advancers`, `decliners`
- `new_high_20d`, `new_low_20d`

### 3.5 Corporate actions (agar indikator tidak “palsu”)
Jika memungkinkan:
- `is_adjusted`, `split_factor` / `adj_factor`
Minimal:
- flag “data discontinuity suspected” untuk outlier extreme.

### 3.6 Data Dictionary & Derived Fields Contract (formal, engine-grade)

Bagian ini adalah **kontrak definisi field**. Engine **wajib** pakai definisi ini, bukan “interpretasi”.
Jika suatu field tidak bisa dipenuhi sesuai kontrak, output harus deterministik: **DROP / WATCH_ONLY / NO_TRADE** + reason code yang benar.

#### 3.6.1 Aturan null/missing (wajib)
- Field yang masuk **Hard filter** (di policy) jika `null/missing` → treat sebagai **FAIL**.
- Field yang dipakai **Entry guard / Viability / Exit** jika `null/missing` → treat sebagai **fail-safe**:
  - NEW ENTRY: **WATCH_ONLY / DISABLE_ENTRY_FOR_TODAY**
  - posisi existing: **MANAGE CONSERVATIVE** (bias reduce/exit) jika rule butuh field itu
- Field yang dipakai untuk **Soft filter / scoring** jika `null/missing` → **skip** (tidak menambah penalti), tapi boleh tulis debug note.

#### 3.6.2 Field catalog (core)
Format: `field` — **type** — *source* — **definisi/formula** — **notes & invariants**

##### A) Global context
- `generated_at` — datetime (WIB) — *system* — waktu JSON dibuat.
- `trade_date` — date — *system* — tanggal EOD canonical yang dipakai untuk scoring/plan.
- `as_of_trade_date` — date — *system* — trading day terakhir yang seharusnya sudah punya EOD canonical saat `generated_at` (lihat 1.1.1).
- `dow` — enum(`Mon|Tue|Wed|Thu|Fri`) — *market_calendar* — hari dalam minggu untuk `trade_date`.
- `is_trading_day` — bool — *market_calendar* — true jika `trade_date` hari bursa.
- `market_regime` — enum(`risk-on|neutral|risk-off`) — *market_index_daily (+ breadth opsional)* — label regime market.

##### B) OHLC canonical (per ticker, per trade_date)
- `open` — float — *ticker_ohlc_daily.canonical* — harga open EOD.
- `high` — float — *ticker_ohlc_daily.canonical* — harga high EOD.
- `low` — float — *ticker_ohlc_daily.canonical* — harga low EOD.
- `close` — float — *ticker_ohlc_daily.canonical* — harga close EOD.
- `volume` — float/int — *ticker_ohlc_daily.canonical* — volume EOD.
- `prev_close` — float — *ticker_ohlc_daily.canonical* atau derived — close hari bursa sebelumnya.
- `prev_high` — float — derived — `high` pada trading day sebelumnya.
- `prev_low` — float — derived — `low` pada trading day sebelumnya.

**Invariant OHLC**
- `low <= open <= high`
- `low <= close <= high`
- `high > 0`, `low > 0`, `close > 0`
- `volume >= 0`

##### C) Indicators (compute-eod output)
- `ma20` — float — *ticker_indicators_daily* — SMA 20 trading days (exclude today).
- `ma50` — float — *ticker_indicators_daily* — SMA 50 trading days.
- `ma200` — float — *ticker_indicators_daily* — SMA 200 trading days.
- `rsi14` — float — *ticker_indicators_daily* — RSI 14 trading days.
- `vol_sma20` — float — *ticker_indicators_daily* — SMA20(volume).
- `vol_ratio` — float — *ticker_indicators_daily* — `volume / vol_sma20` (jika vol_sma20==0 → null).
- `signal_code` — string — *ticker_indicators_daily* — kode sinyal/setup (internal).
- `signal_label` — string — *ticker_indicators_daily* — label yang readable (opsional).
- `signal_first_seen_date` — date — *ticker_indicators_daily* — trading day pertama sinyal dianggap aktif.
- `signal_age_days` — int — *ticker_indicators_daily* — jumlah **trading days** sejak `signal_first_seen_date` sampai `trade_date`.

##### D) Features_cheap (derived dari OHLC/indicators; boleh disimpan di table feature harian)
- `gap_pct` — float — derived — `(open - prev_close)/prev_close` (jika prev_close null/0 → null).
- `tr` — float — derived — `max(high-low, abs(high-prev_close), abs(low-prev_close))`.
- `atr14` — float — *compute-eod* — SMA/EMA 14 trading days dari `tr`.
- `atr_pct` — float — derived — `atr14 / close` (jika close==0 → null).
- `range_pct` — float — derived — `(high-low)/close` (jika close==0 → null).

**Candle metrics**
- `body_abs` — float — derived — `abs(close-open)`.
- `range_abs` — float — derived — `high-low`.
- `body_pct` — float — derived — `body_abs / range_abs` (jika range_abs==0 → null).
- `upper_wick_pct` — float — derived — `(high - max(open,close)) / range_abs` (jika range_abs==0 → null).
- `lower_wick_pct` — float — derived — `(min(open,close) - low) / range_abs` (jika range_abs==0 → null).
- `is_long_upper_wick` — bool — derived — `upper_wick_pct >= 0.55` (default; kalau policy override, ikuti policy).
- `is_long_lower_wick` — bool — derived — `lower_wick_pct >= 0.55` (default).
- `is_inside_day` — bool — derived — `high <= prev_high AND low >= prev_low`.

**HHV/LLV**
- `hhv20` — float — *compute-eod/derived* — highest high 20 trading days (exclude today).
- `llv20` — float — *compute-eod/derived* — lowest low 20 trading days (exclude today).

##### E) Liquidity proxy
- `dvalue` — float — derived — `close * volume`.
- `dv20` — float — *compute-eod/derived* — SMA20(dvalue) (20 trading days, exclude today).
- `liq_bucket` — enum(`A|B|C|U`) — derived — bucket dari `dv20`:
  - A: `dv20 >= liq.dv20_a_min_idr`
  - B: `liq.dv20_b_min_idr <= dv20 < liq.dv20_a_min_idr`
  - C: `dv20 < liq.dv20_b_min_idr`
  - U: dv20 null (unknown)

##### F) Corporate action / data discontinuity
- `corp_action_suspected` — bool — *market data normalization / compute-eod* — true jika ada indikasi split/rights/adjustment atau discontinuity yang membuat indikator “palsu”.
  - Jika field ini **tidak tersedia** → untuk safety treat sebagai **true** di policy yang memblokir corporate action.

##### G) Execution-time helpers (anti-chasing / gap guard)
- `preopen_last_price` — float|null — *optional snapshot* — harga indikatif sebelum market buka pada **hari eksekusi entry** (T+1), jika ada.
- `open_or_last` — float|null — derived — `preopen_last_price ?? null`.
  - **Tidak boleh** fallback ke `open` EOD (karena `open` di dokumen ini adalah *EOD open pada trade_date*, bukan open hari entry).
  - Jika `open_or_last` null: engine **tidak boleh** melakukan **automated NEW ENTRY**, tapi UI boleh tetap menampilkan plan dengan status `preopen_guard = PENDING` (lihat 1.2).

- `preopen_guard` — enum(`PENDING|PASS|FAIL|NA`) — *engine/UI* — status gap/anti-chasing guard di pre-open.
  - `PENDING` jika `open_or_last` belum ada.
  - `PASS/FAIL` jika snapshot ada dan guard dievaluasi.
  - `NA` jika policy tidak butuh preopen guard.

##### H) Tick size & rounding

- `tick_size` — float — *tick_size table / price band* — fraksi harga (IDX).
- `round_to_tick(price, direction)` — function — *engine* — pembulatan ke tick:
  - `UP`: ceil(price / tick_size) * tick_size
  - `DOWN`: floor(price / tick_size) * tick_size
  - Jika tick_size tidak ada → fallback `tick_size=1` (harus menambah reason code data-missing).

---

#### 3.6.3 Trade-plan derived fields (wajib bila policy memakainya)

##### A) Trigger/zone fields
- `breakout_trigger` — float — derived — `round_to_tick(max(prev_high, hhv20) + 1*tick_size, UP)`.
- `pullback_value_zone_low` — float — derived — `round_to_tick(ma20 - 0.20*atr14, DOWN)` (default; policy boleh override).
- `pullback_value_zone_high` — float — derived — `round_to_tick(ma20 + 0.30*atr14, UP)` (default; policy boleh override).

##### B) Risk/Reward fields
- `entry_price` — float — derived — tergantung `entry_type` (harus deterministik).
- `stop_loss_price` — float — derived — dari plan engine (20.3) atau policy.
- `tp1_price`, `tp2_price` — float — derived — dari plan engine/policy.
- `rr_tp1` — float — derived — `(tp1_price - entry_price) / (entry_price - stop_loss_price)` untuk posisi long.
  - Jika `entry_price <= stop_loss_price` → invalid (WATCH_ONLY + reason code).

##### C) Fee & spread viability
- `fee_buy` — float — *fee_config* — fee fraksi (contoh 0.0015).
- `fee_sell` — float — *fee_config* — fee fraksi.
- `avg_spread_pct` — float — *spread_proxy* — proxy spread (opsional).
- `expected_net_tp1_pct` — float — derived — `gross_tp1_pct - fee_buy - fee_sell - (avg_spread_pct ?? 0)`.

---

#### 3.6.4 Portfolio context fields (wajib bila “manage posisi berjalan” aktif)
- `has_position` — bool — *portfolio integration* — true jika ticker sedang dipegang.
- `avg_price` — float — *portfolio* — average buy price.
- `lots` — int — *portfolio* — lot yang sedang dipegang.
- `entry_trade_date` — date — *portfolio* — tanggal entry (trading day).
- `days_held` — int — derived — jumlah **trading days** sejak `entry_trade_date` sampai `trade_date`.
- `ret_since_entry_pct` — float — derived — `(close - avg_price) / avg_price` (jika avg_price null/0 → null).
- `tp1_not_hit` — bool — *portfolio/engine* — true jika TP1 belum pernah tersentuh.
- `sl_is_be_or_trailing` — bool — *engine* — true jika SL sudah BE atau trailing.

**R-based excursion (opsional tapi kalau dipakai harus presisi)**
- Definisi `R` (long): `R = entry_price - stop_loss_price` (harus > 0).
- `mfe_r` — float — derived — `(max_price_since_entry - entry_price) / R`.
- `mae_r` — float — derived — `(entry_price - min_price_since_entry) / R`.
- Jika R-metrics tidak tersedia tapi policy butuh time-stop berbasis R → policy harus punya fallback eksplisit (atau block/exit konservatif).


---

## 4) Pipeline pembuatan watchlist (konsep)

> Aturan SRP/performa global mengikuti `SRP_Performa.md`. Bagian ini hanya menjelaskan urutan proses dan kontrak input/output antar job.

### Step 0 – Ingestion OHLC
- Import OHLC EOD dari sumber(s).
- Normalisasi tanggal & ticker.
- Validasi data (range, null, duplikat).

### Step 1 – ComputeEOD (per hari / per rentang)
Menghasilkan `ticker_indicators_daily` + kolom turunan penting:
- MA/RSI/vol_ratio
- signal_code/label
- fitur candle
- ATR/gap/dv20 (jika diputuskan dihitung di compute-eod)

> Prinsip: **watchlist tidak menghitung indikator berat**. Watchlist membaca hasil compute job (compute-eod/market-context), lalu fokus ke seleksi, scoring, ranking, dan reason codes.

### Step 2 – ComputeMarketContext (per hari)
Menghasilkan:
- market regime (IHSG + breadth)
- hari dalam minggu (dow)
- special flags (dekat libur, setelah libur)

### Step 3 – WatchlistBuild (per hari)
Input:
- `ticker_indicators_daily` (trade_date)
- `ticker_ohlc_daily` (trade_date)
- `market_index_daily` (trade_date)
- `market_calendar` (trade_date)

Output:
- `watchlist_daily` (per hari) + `watchlist_candidates` (per ticker)

---

## 5) Candidate selection (filter awal)

### 5.1 Hard filters (buang kandidat yang tidak layak dipertimbangkan)
Contoh:
- Data tidak lengkap untuk indikator (ma/vol_sma/atr)
- Likuiditas terlalu rendah: `dv20 < threshold` (mis. bucket C) → bisa “watch only”
- Volatilitas ekstrem: `atr_pct > 0.10` → default drop (weekly swing), kecuali override manual
- Harga ekstrem / outlier yang terindikasi corporate action belum adjusted → skip

### 5.2 Soft filters (boleh lolos tapi confidence turun)
- `atr_pct` terlalu tinggi (saham liar)
- Candle EOD “long upper wick” + close jauh dari high (indikasi distribusi)
- RSI terlalu tinggi (overheated) → entry harus pullback/wait

---

## 6) Scoring model (deterministik & audit-able)

### 6.1 Komponen skor (contoh)
Total 100 poin:
- Trend quality (0–30)
  - MA alignment (MA20>MA50>MA200), slope proxy, close di atas MA
- Momentum/candle (0–20)
  - close_to_high, body_pct, breakout structure
- Volume & liquidity (0–20)
  - vol_ratio, dv20 bucket
- Risk quality (0–20)
  - atr_pct, gap risk, range_pct
- Market alignment (0–10)
  - IHSG regime, breadth

> Setiap komponen harus menghasilkan **debug reason code** (mis. di `debug.rank_reason_codes[]`) saat memberi atau mengurangi poin. `reason_codes[]` untuk UI harus tetap **policy-prefixed** (lihat Section 9).

### 6.2 Confidence mapping
- High: score ≥ 82 dan tidak kena red-flag risk
- Med: score 72–81 atau ada 1 red-flag minor
- Low: score < 72 atau red-flag risk dominan

---

## 7) Setup type classification (berbasis EOD)
Watchlist harus mengklasifikasi kandidat agar timing advice masuk akal.

Contoh mapping:
- **Breakout / Strong Burst**
  - close near high, vol_ratio tinggi, break resistance/HHV, trend mendukung
- **Pullback**
  - trend bagus tapi close pullback ke MA20/MA50/support
- **Continuation**
  - sinyal konsisten beberapa hari (signal_age_days), trend kuat, volatilitas wajar
- **Reversal**
  - downtrend pendek + reversal candle + volume naik
- **Base**
  - range sempit, volatilitas turun, volume mulai naik tapi belum breakout

Output:
- `setup_type`
- `setup_notes` (singkat)

---

## 8) Engine “Jam beli” (EOD → window + larangan jam + alasan)

### 8.1 Default window (paling sering)
- `entry_windows`: `["09:20-10:30", "13:35-14:30"]`
- `avoid_windows`: `["09:00-09:15", "11:45-12:00", "15:50-close"]`

> Ini baseline. Lalu watchlist menggeser window berdasarkan risk & setup.

### 8.2 Day-of-week adjustment (single source of truth)

Bagian ini **bukan tempat** untuk menyimpan angka/threshold policy (karena gampang drift).
Untuk keputusan DOW, window, max positions, dan size multiplier:

- `WEEKLY_SWING` → ikuti **Section 2.3.1 / entry_rules.day_of_week_rules**
- Policy lain → ikuti `entry_rules` masing-masing policy.

Di sini hanya prinsip umum (tanpa angka):
- **Mon**: rawan fake move → bias lebih hati-hati.
- **Tue**: entry day terbaik.
- **Wed/Thu**: selektif; fokus manage posisi berjalan.
- **Fri**: default **no new entry** kecuali policy mengizinkan secara eksplisit (bias exit/manage).

### 8.3 Risk-driven window shifting (berdasarkan metrik EOD)
Gunakan rules berikut (contoh):

**Rule: Gap risk tinggi**
- Kondisi: `gap_pct` historis besar atau `atr_pct` tinggi atau market risk-off
- Aksi:
  - tambah avoid: `["pre-open", "09:00-09:30"]`
  - entry_windows geser lebih siang: `["09:45-11:15", "13:45-14:30"]`
- Debug codes (untuk `debug.rank_reason_codes`): `GAP_RISK_HIGH`, `VOLATILITY_HIGH`

**Rule: Likuiditas rendah (dv20 kecil)**
- Aksi:
  - hindari open & mendekati close (spread/antrian)
  - entry_windows: `["10:00-11:30", "13:45-14:30"]`
- Debug code: `LIQ_LOW_MATCH_RISK`

**Rule: Breakout kuat**
- Aksi:
  - entry window tetap pagi setelah tenang: `["09:20-10:15"]`
  - checklist menekankan follow-through + guard anti-chasing:
    - Jika open/last price gap-up > `max_gap_up_pct` dari close kemarin → NO ENTRY (tunggu pullback)
    - Jika harga sudah jauh di atas trigger (> 0.5*ATR14) → NO CHASE
- Debug code: `BREAKOUT_SETUP`

**Rule: Pullback**
- Aksi:
  - entry window lebih fleksibel, tunggu stabil: `["09:35-11:00", "13:35-14:45"]`
- Debug code: `PULLBACK_SETUP`

**Rule: Reversal**
- Aksi:
  - hindari pagi awal; butuh konfirmasi: `["10:00-11:30", "13:45-14:30"]`
- Debug code: `REVERSAL_CONFIRM`

---

## 9) “Daftar saran & alasan” yang disimpan (wajib)

Watchlist harus menyimpan teks/JSON yang ringkas tapi kaya makna.
Tujuannya: kamu bisa lihat kembali kenapa watchlist menyarankan jam tertentu.

### 9.1 Reason Code Namespace Rule (kontrak)

Tujuan: output **tidak ambigu**. Reason code yang tampil di UI harus bisa langsung menjelaskan *kenapa* suatu keputusan terjadi dan **policy mana** yang mengeluarkannya.

#### 9.1.1 Dua namespace: UI vs debug
1) `reason_codes[]` (**UI / keputusan**)  
- **Wajib** policy-prefixed: `<PREFIX>_*`  
- Tidak boleh berisi kode generik tanpa prefix.

2) `debug.rank_reason_codes[]` (**audit/scoring**, opsional)  
- Boleh berisi kode generik seperti `TREND_STRONG`, `MA_ALIGN_BULL`, dll.  
- Field ini tidak wajib dipublish ke UI.

#### 9.1.2 Prefix resmi (wajib)
- `WS_` = WEEKLY_SWING  
- `DS_` = DIVIDEND_SWING  
- `IL_` = INTRADAY_LIGHT  
- `PT_` = POSITION_TRADE  
- `NT_` = NO_TRADE  
- `GL_` = Global gate / engine-level (contoh: `GL_EOD_NOT_READY`) — optional, tapi jika dipakai harus konsisten.

> **Rule hard:** jika ada `reason_codes[]` tanpa prefix resmi → contract test **FAIL**.

#### 9.1.3 Legacy mapping (generic lama → canonical)
Jika sebelumnya UI memakai kode generik, harus dimigrasikan (mapping deterministik).
Contoh mapping minimal (WEEKLY_SWING):

| legacy (jangan dipublish ke UI) | canonical UI code |
|---|---|
| `GAP_UP_BLOCK` | `WS_GAP_UP_BLOCK` |
| `CHASE_BLOCK_DISTANCE_TOO_FAR` | `WS_CHASE_BLOCK_DISTANCE_TOO_FAR` |
| `MIN_EDGE_FAIL` | `WS_MIN_EDGE_FAIL` |
| `TIME_STOP_TRIGGERED` | `WS_TIME_STOP_T2` *(atau T3 sesuai rule yang kena)* |
| `TIME_STOP_T2` | `WS_TIME_STOP_T2` |
| `TIME_STOP_T3` | `WS_TIME_STOP_T3` |
| `FRIDAY_EXIT_BIAS` | `WS_FRIDAY_EXIT_BIAS` |
| `WEEKEND_RISK_BLOCK` | `WS_WEEKEND_RISK_BLOCK` |
| `VOLATILITY_HIGH` | `WS_VOLATILITY_HIGH_WARN` |
| `FEE_IMPACT_HIGH` | `WS_FEE_EDGE_FAIL` |
| `NO_FOLLOW_THROUGH` | `WS_TIME_STOP_T2` *(atau code follow-through lain jika ditambah di policy)* |
| `SETUP_EXPIRED` | `WS_SIGNAL_STALE_WARN` *(atau drop code yang dipakai policy)* |

Untuk policy lain, prinsipnya sama:
- `DS_*` untuk DIVIDEND_SWING (mis. `DS_GAP_UP_BLOCK`, `DS_CHASE_BLOCK`, `DS_EXIT_ON_EXDATE`)
- `IL_*` untuk INTRADAY_LIGHT
- `PT_*` untuk POSITION_TRADE
- `NT_*` untuk NO_TRADE / global gating

#### 9.1.4 Migrasi aman (wajib)
- Engine boleh sementara mengeluarkan:
  - `reason_codes[]` = canonical prefixed
  - `debug.legacy_reason_codes[]` = legacy (opsional, untuk audit)
- UI **harus** membaca canonical codes. Legacy hanya untuk debugging sampai dihapus.


### 9.2 Saran jam & larangan jam (disimpan sebagai data)
- `entry_windows` (array)
- `avoid_windows` (array)
- `timing_summary` (1 kalimat, contoh: “Hindari open karena gap/ATR tinggi; entry terbaik setelah 09:45 ketika spread stabil.”)

### 9.3 Checklist sebelum buy (disimpan sebagai array)
Minimal 3 item per kandidat. Template item bisa reusable.

---

## 10) Top picks vs kandidat lain (3 rekomendasi vs sisanya)
Watchlist menampilkan:
- **Top Picks (mis. 3 ticker)**: yang skor tertinggi & lolos quality gate.
- **Secondary Candidates**: skor bagus tapi ada warning (low liq, risk tinggi, market risk-off, dll).
- **NO TRADE**: jika market regime buruk atau semua kandidat gagal quality gate.

Selain ranking, watchlist harus memberi saran portofolio harian:
- **BUY 1 ONLY** (100%) bila pick #1 jauh lebih kuat (gap skor besar)
- **BUY 2 SPLIT** (70/30 atau 60/40) bila top 2 sama kuat dan likuid
- **NO TRADE** bila risk dominan

---


## 11) Data yang mungkin “belum ada” tapi sebaiknya disediakan (supaya akurasi naik)
Bagian ini menjelaskan **apa yang perlu ditambah** di Market Data / Compute EOD sebelum kamu “naik kelas” strategi.

### 11.1 Wajib untuk akurasi EOD-only (WEEKLY_SWING default)
> Semua di bawah ini bisa dihitung dari OHLC canonical (murah, tapi efeknya besar).
1) `prev_close` + `gap_pct`
2) `atr14` + `atr_pct`
3) `dvalue = close*volume` + `dv20` + `liq_bucket`
4) candle metrics (body/wick/inside/engulfing flags)
5) market regime IHSG (ret_1d/ret_5d + MA20/MA50) + (opsional) breadth
6) `signal_first_seen_date` + `signal_age_days`

**Kalau poin 1–6 belum ada:**
- yang paling tepat: dihitung di **Compute EOD** (feature layer) lalu disimpan ke `ticker_indicators_daily` (atau tabel feature harian lain).
- watchlist hanya membaca, tidak menghitung ulang.

### 11.2 Tambahan untuk DIVIDEND_SWING (event-driven)
Butuh **calendar event**, bukan sekadar indikator:
- `dividend_calendar` minimal: `ticker_id`, `ex_date`, `record_date` (opsional), `pay_date` (opsional), `cash_dividend` (opsional), `yield_est` (opsional)
- flag corporate action adjusted (split/rights) agar yield tidak menipu

**Kalau belum ada:**
- ini bukan tugas Compute EOD. Ini tugas **Market Data (Corporate Actions / Events)**.

### 11.3 Tambahan untuk INTRADAY_LIGHT (tanpa full intraday)
Butuh 1 record per ticker per hari dari sesi awal:
- `opening_range_high`, `opening_range_low` (09:00–09:15 atau 09:00–09:30)
- `opening_range_volume`
- `gap_pct_real` (gap pada open aktual)
- opsional: `or_breakout_flag` (break above OR high?), `or_fail_flag` (break lalu balik?)

**Kalau belum ada:**
- ini bukan Market Data EOD. Buat modul kecil terpisah: **Intraday Snapshot (opening range)**.
- watchlist policy `INTRADAY_LIGHT` harus otomatis nonaktif kalau data ini tidak tersedia.

### 11.4 Tambahan untuk POSITION_TRADE (2–8 minggu)
Butuh metrik trend yang lebih stabil:
- slope proxy MA (mis. `ma20_slope`, `ma50_slope` dalam % per hari trading)
- volatility filter jangka menengah (ATR% rata-rata 20–50 hari)
- drawdown proxy (mis. distance from 20d high/low)

**Kalau belum ada:**
- tetap dihitung di **Compute EOD** (feature layer), karena turunannya dari OHLC.

### 11.5 Penyesuaian yang “wajib kalau mau akurat” di Market Data & Compute EOD
Ini bukan fitur mewah. Ini mencegah data “kelihatan jalan tapi salah”.

**A. Market Data (wajib):**
- CANONICAL gating: kalau coverage jelek / held, watchlist harus bisa `NO_TRADE`.
- corporate action awareness: minimal flag split/discontinuity (ideal: adjusted canonical).
- `trade_date` konsisten WIB + market_calendar.

**B. Compute EOD (wajib):**
- rolling window = **N trading days** (bukan kalender) untuk semua indikator.
- jika window tidak lengkap → indikator NULL + downgrade decision + log warning.
- tidak menghitung “strategi”; hanya feature/signal.


---

## 12) Skema tabel output watchlist (saran)

### 12.1 watchlist_daily
- `trade_date` (PK)
- `dow`
- `market_regime`
- `market_ret_1d`, `market_ret_5d` (opsional)
- `notes` (text pendek)
- `created_at`

### 12.2 watchlist_candidates
PK: `(trade_date, ticker_id)`
- `rank`
- `watchlist_score`
- `confidence`
- `setup_type`
- `entry_windows` (json)
- `avoid_windows` (json)
- `size_multiplier`
- `max_positions_today`
- `reason_codes` (json)
- `timing_summary` (text pendek)
- `pre_buy_checklist` (json)
- `created_at`

Index penting:
- `(trade_date, rank)`
- `(trade_date, watchlist_score desc)`
- `(trade_date, setup_type)`

---

## 13) Contoh output (1 kandidat)

**Ticker: ABCD**
- setup_type: Breakout
- score: 86 (High)
- entry_windows: ["09:20-10:15", "13:35-14:15"]
- avoid_windows: ["09:00-09:15", "15:50-close"]
- size_multiplier: 1.0 (Selasa)
- reason_codes (UI, policy-prefixed):
  - WS_SETUP_BREAKOUT
  - WS_TREND_ALIGN_OK
  - WS_VOLUME_OK
  - WS_LIQ_OK
  - WS_RR_OK
- debug.rank_reason_codes (audit/scoring, opsional):
  - TREND_STRONG
  - MA_ALIGN_BULL
  - VOL_RATIO_HIGH
  - BREAKOUT_CONF_BIAS
- timing_summary:
  - “Breakout kuat; hindari open untuk mengurangi spike risk. Entry terbaik setelah 09:20 saat spread stabil.”
- checklist:
  1) “Jangan buy jika gap-up terlalu jauh dari close kemarin”
  2) “Pastikan follow-through, bukan spike 1 menit”
  3) “Spread rapat, bid/ask padat”

---

## 14) Quality assurance (QA) & audit
 Quality assurance (QA) & audit

Bagian ini adalah “aturan kerja” supaya watchlist **stabil, bisa diaudit, dan gampang di-upgrade** tanpa merusak hasil yang sudah ada.

### 14.1 Contract test (JSON schema) – wajib
Watchlist adalah **API contract**. Setiap build harus lulus test yang memvalidasi:
- Root keys: `trade_date`, `groups`, `meta`, `recommendations`
- `meta.counts.total_count` konsisten dengan jumlah kandidat yang tampil
- Semua kode di `reason_codes[]` dan `rank_reason_codes[]` harus terdaftar di katalog (`meta.rank_reason_catalog`) jika katalog disertakan
- Tipe data stabil (angka tetap angka; array tetap array)

**Aturan kompatibilitas**
- Kalau ada rename field (breaking) → wajib dicatat di `MANIFEST.md` + bump build_id.
- Kalau ingin aman untuk UI lama → gunakan alias (field lama tetap ada sementara) sebelum benar-benar dihapus.

### 14.2 Invariants – harus true setiap waktu
Ini bukan “best practice”, ini **aturan hard** yang kalau gagal berarti output tidak valid.

**A. Global gating lock**
- Jika `recommendations.mode = NO_TRADE`:
  - semua ticker wajib `trade_disabled = true`
  - `entry_windows = []`, `avoid_windows = []`
  - `size_multiplier = 0.0`, `max_positions_today = 0`
  - `entry_style = "No-trade"`
  - `timing_summary` dan `pre_buy_checklist` boleh pakai default global

**B. Top picks quality**
- `groups.top_picks[]` tidak boleh punya `liq_bucket in ('C','U')`
- `passes_hard_filter` harus true
- `trade_plan.errors` harus kosong untuk ticker yang direkomendasikan BUY (kalau mode BUY)

**C. Data consistency**
- `signal_age_days` konsisten dengan `signal_first_seen_date` + kalender bursa
- `corp_action_suspected = true` → ticker **tidak boleh** direkomendasikan BUY
- Nilai `dv20` dan `liq_bucket` harus konsisten (dv20 menentukan bucket)

### 14.3 Reason code governance (biar tidak liar)
Tambah reason code baru **wajib** memenuhi:
- Punya definisi singkat (1 kalimat) + severity (`info|warn|block`)
- Deterministik (tidak bergantung “perasaan”)
- Sumber datanya jelas (kolom indikator/fitur apa)
- Masuk katalog (`meta.rank_reason_catalog` / label catalog) dan test “unknown code → fail”

**Rekomendasi:** pisahkan alasan menjadi:
- `reason_codes` = untuk UI (ringkas & actionable)
- `rank_reason_codes` = untuk audit/scoring (boleh verbose), bisa dipindah ke `debug.*`

### 14.4 Snapshot strategy (compute vs serve)
Untuk hasil maksimal dan stabil:
- Default endpoint **serve-from-snapshot** bila snapshot untuk (`trade_date`, `source`) sudah ada.
- Compute hanya jika:
  - snapshot belum ada, atau
  - `force=1`, atau
  - ada perubahan policy version yang memang ingin regenerate.

Ini membuat:
- output konsisten (hit endpoint berulang hasilnya sama),
- beban DB lebih ringan,
- evaluasi mingguan lebih gampang.

### 14.5 Calibration loop (supaya makin akurat, bukan teori)
Minimal tiap minggu/bulan, buat laporan:
- hit-rate TP1/TP2 (1D/3D/5D)
- MFE/MAE sederhana (maks profit vs maks drawdown setelah entry)
- frekuensi false breakout untuk setup tertentu

Dari sini update:
- bobot score (trend/momentum/volume/risk/market),
- threshold (vol_ratio, atr_pct, dv20 bucket),
- expiry rule (`signal_age_days`).

**Aturan:** perubahan threshold harus tercatat di dokumen (dan idealnya `policy_version`).

### 14.6 Post-mortem wajib (supaya WATCHLIST.md makin baru)
Simpan minimal untuk top picks/recommended:
- snapshot indikator + reason codes saat rekomendasi dibuat
- outcome ringkas (TP/SL/time-stop)
- catatan eksekusi: spread melebar? gap? follow-through?

Tujuannya: WATCHLIST.md tidak jadi “dokumen teori”, tapi terus di-upgrade berdasarkan data nyata.

---

## 15) Roadmap (opsional)
Tahap 1 (EOD-only):
- Implement semua metrik turunan + market regime
- Implement reason_codes + timing windows

Tahap 2 (semi-intraday ringan):
- Tambah opening range 15m (1 record per ticker per hari)
- Timing rule jadi lebih tajam (mis. “OR breakout valid/invalid”)

Tahap 3 (intraday penuh, bila dibutuhkan):
- Hanya untuk kandidat saja (tiering), bukan 900 ticker.

---

## 16) Checklist implementasi cepat
1) Pastikan kolom-kolom wajib ada (lihat bagian 3 & 11).
2) Putuskan: ATR/gap/dv20 dihitung di ComputeEOD atau job terpisah (disarankan di ComputeEOD).
3) Tambah tabel market context (IHSG minimal).
4) Bangun scoring + reason codes.
5) Bangun timing engine (window + avoid) berbasis setup_type + risk + dow + market regime.
6) Simpan output watchlist ke DB agar “daftar saran” bisa diarsipkan.

---

## 17) Catatan keras (biar tidak salah arah)
- Watchlist harus berani output: **NO TRADE**.
- Watchlist harus selalu mengeluarkan **alasan** yang bisa diuji (reason codes), bukan opini.
- Jam beli yang disaranin harus bisa dijelaskan dari:
  - setup_type (EOD),
  - risk metrics (ATR/gap/dv20/candle),
  - day-of-week,
  - market regime.

---

## 18) UI Output Spec (kandidat vs recommended pick)

UI kamu (sesuai mockup) sudah tepat: **semua kandidat tetap punya kartu data** yang konsisten, lalu **khusus recommended pick** ditambah strategi pembelian lengkap (allocation + trade plan + lots).

### 18.1 Field yang tampil untuk *setiap kandidat* (baseline card)

**OHLC**
- `open`, `high`, `low`, `close`

**Market**
- `rel_vol` (RelVol / vol_ratio)
- `pos_pct` (Pos% = posisi **close** dalam range hari itu, 0–100): `100*(close-low)/(high-low)` lalu clamp 0..100; jika `high==low` → `null`.
- `eod_low` (low EOD / level risiko reference)
- `price_ok` (boolean; lolos price filter)

**Plan (ringkas, untuk kandidat biasa)**
- `entry` (tipe entry ringkas: breakout/pullback/reversal/watch-only)
- `sl` (jika sudah bisa dihitung dari EOD; kalau tidak, tampil “TBD” + reason)
- `tp1`, `tp2` (opsional untuk kandidat biasa; minimal tampil “target zone”)
- `out` (exit rule ringkas / invalidation)
- `buy_steps` (untuk kandidat biasa: bisa “single entry” / “wait retest”)
- `lots` (untuk kandidat biasa: boleh kosong jika modal belum dimasukkan)
- `est_cost` (boleh kosong jika modal belum dimasukkan)

**Risk / Result**
- `rr` (RR ke TP1 atau RR utama)
- `risk_pct` (risk% dari modal—jika sizing aktif)
- `profit_tp2_net` (jika sizing aktif + fee diset)
- `rr_tp2` dan/atau `rr_tp2_net`

**Meta**
- `rank`
- `snapshot_at`
- `last_bar_at` (tanggal bar terakhir yang dipakai)

**Reason**
- ringkasan + reason codes (lihat Section 9)

> Catatan: untuk kandidat biasa, “Plan” boleh lebih ringkas (entry + invalidation + risk note).
> Tapi **field set-nya tetap sama** supaya UI konsisten.

### 18.2 Tambahan khusus untuk *recommended pick*
Recommended pick harus menampilkan **strategi eksekusi yang bisa dipakai**:
- Alokasi beli (BUY 1 / BUY 2 split / BUY 3 small / NO TRADE)
- Entry price (trigger/range), SL, TP1, TP2, BE, trailing, buy steps
- Lots + estimasi biaya berdasarkan modal user
- RR, risk%, profit net (opsional, tapi ideal)

---

## 19) Portfolio Allocation Engine (Top 3 → BUY 0/1/2 + %)

Bagian ini mengubah “Top 3 pick” menjadi **keputusan portofolio harian** yang realistis untuk weekly swing:
- beli 1 saja, atau beli 2 split, atau tidak beli sama sekali.
- outputnya juga mengatur “size multiplier” berdasar hari (Mon/Tue/Wed/Thu/Fri) dan kondisi market.

### 19.0 Prioritas posisi existing (weekly swing yang realistis)
Jika ada posisi open di portfolio, watchlist **harus** memprioritaskan manajemen posisi dulu, baru entry baru.
- Jika ada posisi yang kena rule `EXIT` / `WS_TIME_STOP_T2|WS_TIME_STOP_T3` / `WS_FRIDAY_EXIT_BIAS` → mode harian minimal `CARRY_ONLY` (no new entry) sampai selesai dieksekusi.
- Jika ada posisi yang valid untuk di-hold (trend lanjut, SL sudah naik) → boleh tetap `BUY_1/BUY_2`, tapi `max_positions_today` dipotong 1.
- Definisi `CARRY_ONLY`: watchlist **hanya** memberi aksi `HOLD/REDUCE/EXIT/TRAIL_SL` untuk posisi existing; `allocations[]` untuk NEW ENTRY harus kosong.

### 19.1 Output yang disimpan (per hari)
- `trade_plan_mode`: `NO_TRADE | BUY_1 | BUY_2_SPLIT | BUY_3_SMALL | CARRY_ONLY`
- `max_positions_today`
- `allocations`: array object `{ticker_id, ticker_code, alloc_pct, alloc_amount?}`
- `capital_total` (jika user memasukkan modal; kalau tidak ada, simpan null)
- `risk_per_trade_pct` (default config, mis. 0.5%–1.0%)

### 19.2 Rule deterministik untuk memilih BUY_1 / BUY_2 / NO_TRADE
**NO_TRADE**
- `market_regime == risk-off` (atau breadth jelek + index down) DAN/ATAU
- semua kandidat gagal quality gate (liq sangat rendah / ATR terlalu tinggi / gap risk ekstrem / data incomplete)

**BUY_1**
- `score1 - score2 >= gap_threshold` (mis. 8–10) ATAU
- pick #2/#3 punya red-flag (liq bucket C, volatility ekstrem, gap risk tinggi)
- atau modal kecil sehingga diversifikasi justru bikin eksekusi jelek

**BUY_2_SPLIT**
- top2 lolos quality gate, confidence minimal `Med`, dan gap skor kecil
- (opsional) beda sektor untuk menghindari korelasi tinggi

**BUY_3_SMALL** (jarang)
- semua top3 confidence High, likuiditas bagus, market risk-on

### 19.3 Aturan split % (langsung keluar angka)
- default: `70/30` jika score1 > score2 cukup jelas
- `60/40` jika skor sangat dekat
- `50/30/20` untuk BUY_3_SMALL

> Semua split harus tercatat di `allocations[]`.

---

## 20) Trade Plan Engine (Entry, SL, TP1, TP2, BE, Out) – Top picks & kandidat lain

Engine ini menghasilkan level-level plan **berbasis EOD**, bukan prediksi intraday.
Karena watchlist EOD-only, entry harus berupa:
- **trigger** (breakout) atau
- **range limit** (pullback) atau
- **confirm trigger** (reversal).

### 20.1 Output yang disimpan (per kandidat)
- `entry_type`: `BREAKOUT_TRIGGER | PULLBACK_LIMIT | REVERSAL_CONFIRM | WATCH_ONLY`
- `entry_trigger_price` atau `entry_limit_low/high`
- `stop_loss_price`
- `tp1_price`, `tp2_price`
- `be_price` (break-even rule; biasanya = entry setelah TP1)
- `out_rule` (invalid if / exit rule ringkas)
- `buy_steps` (mis. “60% on trigger, 40% on retest”)
- `rr_tp1`, `rr_tp2` (gross)
- (opsional) `rr_tp2_net`, `profit_tp2_net` jika fee aktif

### 20.2 Data tambahan yang wajib disediakan agar plan akurat
Selain OHLC + MA/RSI/vol_ratio, plan butuh:
- `prev_close`, `prev_high`, `prev_low`
- `atr14`, `atr_pct`
- `hhv20`, `llv20` (atau minimal highest/lowest N days)
- `tick_size` (fraksi harga sesuai price band; untuk “+1 tick” yang benar)
- (opsional) `support_level`, `resistance_level` dari swing detection
- `fee_buy`, `fee_sell` (opsional untuk net profit)

### 20.3 Formula plan per setup_type (contoh deterministik)
Gunakan tick rounding setiap kali menghasilkan harga.

**A) Breakout / Strong Burst**
- Entry: `trigger = max(prev_high, hhv20) + 1_tick`
- Buy steps: `60% on trigger`, `40% on retest (optional)`
- SL: `min(prev_low, trigger - 1.0*ATR)` (pilih yang paling “logis & ketat”)
- TP1: `entry + 1R`
- TP2: `entry + 2R` (atau target weekly +4%/+5% jika kamu mau mode itu)
- BE: setelah TP1 tercapai, `SL = entry` (atau `entry + 0.2R`)
- Out: invalid jika `gap_up > x*ATR` atau close jatuh kembali di bawah level breakout

**B) Pullback (uptrend)**
- Entry: `limit_range = [MA20 - 0.2ATR, MA20 + 0.3ATR]` (contoh; tune)
- SL: di bawah support/MA (atau `entry - 1ATR`)
- TP1: ke `prev_high` atau `entry + 1R`
- TP2: `entry + 2R`
- Out: batal jika breakdown support jelas (close < support/MA dengan range besar)

**C) Reversal**
- Entry: confirm `trigger = prev_high + 1_tick` (setelah reversal candle EOD)
- SL: `swing_low` atau `llvN` (N=5/10)
- TP1/TP2: konservatif: `1R` dan `2R`
- Out: jika gagal follow-through (kembali close di bawah area reversal)

**D) Base / Sideways**
- Default: `WATCH_ONLY` sampai breakout trigger valid
- Entry/SL/TP mengikuti breakout rule saat trigger terjadi

### 20.4 Guards (single source of truth, tidak boleh duplikasi angka)

Guard membuat plan **boleh batal** walau setup EOD bagus. Tapi angka/threshold guard **harus** berasal dari policy, bukan dari section generik ini.

**Kontrak**
- Untuk setiap `policy_code`, guard diambil dari blok eksekusi policy:
  - `entry_rules` (gap / anti-chasing / min-edge)
  - `min_trade_viability` (viability modal kecil + fee/spread)
  - `exit_rules` (time-stop / max holding / trailing)

**Referensi**
- `WEEKLY_SWING` → Section **2.3.1**
  - `entry_rules.anti_chasing_and_gap_guards`
  - `min_trade_viability`
  - `exit_rules` (time_stop, friday_exit_bias, weekend_rule)
- `DIVIDEND_SWING` → Section **2.4.1**
- `INTRADAY_LIGHT` → Section **2.5.1**
- `POSITION_TRADE` → Section **2.6.1**
- `NO_TRADE` → Section **2.7.1**

**Template guard (umum, tanpa angka)**
- Gap guard: kalau harga indikatif terlalu jauh dari `prev_close` → disable entry hari itu.
- No-chase guard: kalau harga sudah terlalu jauh dari trigger/zone (ATR-distance) → downgrade/watch-only.
- Min-edge guard: kalau RR/edge tidak cukup (termasuk fee/spread kalau tersedia) → watch-only.
- Time-stop: kalau setelah N trading days tidak ada follow-through → reduce/exit (sesuai policy).


---

## 21) Position Sizing Engine (Modal → Lots)

Ini yang membuat watchlist bisa bilang “beli berapa lots” saat user memasukkan modal.

### 21.1 Input
- `capital_total` (modal user)
- `alloc_pct` per ticker (hasil Section 19)
- `entry_price`, `stop_loss_price`
- `risk_per_trade_pct` (default config)
- `lot_size = 100` (IDX)
- (opsional) `fee_buy`, `fee_sell`

### 21.2 Output per kandidat (khususnya recommended pick)
- `alloc_amount`
- `lots_recommended`
- `est_cost` (≈ entry * lots * 100 + fee_buy)
- `max_loss_if_sl` (≈ (entry - sl) * lots * 100 + fee)
- `risk_pct` (max_loss_if_sl / capital_total)

### 21.3 Formula sizing (deterministik)
- `risk_budget = capital_total * risk_per_trade_pct`
- `risk_per_share = entry - sl`
- `shares_by_risk = floor(risk_budget / risk_per_share)`
- `shares_by_alloc = floor((capital_total * alloc_pct) / entry)`
- `shares_final = min(shares_by_risk, shares_by_alloc)`
- `lots = floor(shares_final / 100)`

Rules:
- jika `lots == 0` → ticker otomatis menjadi `WATCH_ONLY` (atau alokasi dialihkan)
- jika `risk_per_share <= 0` → plan invalid (data/level salah) → jangan trade
- jika `risk_pct` melewati batas config → turunkan lots atau ubah entry (tunggu pullback)

---

## 22) Integration Notes (supaya cepat & SRP tetap rapi)
- **ComputeEOD**: hitung indikator + feature layer (MA/RSI/vol_ratio, ATR, hhv/llv, wick/body, dv20, dsb). **Bukan** entry/SL/TP/plan.
- **MarketContext job**: IHSG regime + breadth + kalender.
- **WatchlistBuild**: scoring + ranking + setup_type + timing windows + reason codes.
- **TradePlanBuild** (bisa bagian dari watchlist build): entry/SL/TP/BE/out + rr.
- **PositionSizing**: hanya jalan kalau user memasukkan `capital_total` (atau ada default dari profile).

Semua output disimpan di DB agar UI bisa menampilkan kartu kandidat seperti mockup, dan recommended pick punya strategi eksekusi lengkap.