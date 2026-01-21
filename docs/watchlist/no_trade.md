# Policy: NO_TRADE

Dokumen ini adalah **single source of truth** untuk policy NO_TRADE (global gate/override).
Ketika aktif, policy ini **menonaktifkan NEW ENTRY** dan mengubah mode menjadi **manage-only** untuk posisi yang sedang berjalan.

Dependensi lintas policy (Data Dictionary, schema output, namespace reason codes) ada di `WATCHLIST.md`.

---

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
