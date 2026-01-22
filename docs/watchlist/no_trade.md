# Policy: NO_TRADE

Dokumen ini adalah **single source of truth** untuk policy **NO_TRADE** (global gate/override).
Ketika aktif, policy ini **menonaktifkan NEW ENTRY** dan (jika ada posisi) mengubah mode menjadi **CARRY_ONLY** untuk posisi yang sedang berjalan.

Kontrak lintas-policy (Data Dictionary, schema output, invariants output, window format, rounding/fee model, governance reason codes) ada di `watchlist.md`.

---

## 1) Tujuan

Proteksi modal. Policy ini **bukan** “tidak ada output”, tapi output deterministik yang memaksa sistem *tidak membuka posisi baru*.

## 2) Policy execution block (wajib, deterministik)

```yaml
policy_code: NO_TRADE

# 1) Data dependency
data_dependency:
  required:
    - market_context: [market_regime, dow]
    - data_freshness_gate: [eod_canonical_ready, missing_trading_dates]
  optional:
    - breadth_context: [breadth_new_low_20d, breadth_adv_decl_ratio]  # jika tersedia
    - portfolio_context: [has_position]   # untuk menentukan apakah mode = CARRY_ONLY

# 2) Hard triggers (global gate)
hard_triggers:
  - id: GL_EOD_NOT_READY
    expr: "eod_canonical_ready == false"
    action: NO_TRADE
    add_reason: [GL_EOD_NOT_READY]

  - id: GL_MARKET_RISK_OFF
    expr: "market_regime == risk-off"
    action: NO_TRADE
    add_reason: [GL_MARKET_RISK_OFF]

  # Breadth crash hanya dievaluasi jika breadth tersedia (tidak boleh implicit NULL compare)
  - id: GL_BREADTH_CRASH
    expr: "breadth_new_low_20d != null and breadth_adv_decl_ratio != null and breadth_new_low_20d >= 120 and breadth_adv_decl_ratio <= 0.40"
    action: NO_TRADE
    add_reason: [GL_BREADTH_CRASH]

# 3) Soft filters
# Dalam NO_TRADE tidak ada scoring. Semua kandidat (jika tetap dihitung) harus berstatus watch-only.
soft_filters: []

# 4) Setup allowlist
# Tidak relevan untuk NEW ENTRY karena allow_new_entry=false, tapi tetap eksplisit agar tidak ada interpretasi liar.
setup_allowlist:
  recommended: []
  secondary_only: []
  forbidden: [Breakout, Pullback, Continuation, Reversal, Base]

# 5) Entry rules (NEW ENTRY dimatikan)
entry_rules:
  allow_new_entry: false
  entry_windows: []
  avoid_windows: ["09:00-close"]     # format window mengikuti kontrak di watchlist.md
  trade_disabled: true
  size_multiplier: 0.0
  max_positions_today: 0

# 6) Exit rules (untuk posisi existing)
exit_rules:
  if_has_position:
    mode: CARRY_ONLY
    allowed_actions: [HOLD, REDUCE, EXIT, TRAIL_SL]
    add_reason: [NT_CARRY_ONLY_MANAGEMENT]

# 7) Sizing defaults
sizing_defaults:
  risk_per_trade_pct_default: 0.0
  max_positions_total_default: 0

# 8) Reason code governance
# GL_* adalah reason code global (lintas-policy). NT_* khusus policy ini.
reason_codes:
  global:
    - GL_EOD_NOT_READY
    - GL_MARKET_RISK_OFF
    - GL_BREADTH_CRASH
  policy:
    prefix: NT_
    codes:
      - NT_CARRY_ONLY_MANAGEMENT
```

## 3) Invariant output (wajib)

Invariant output mengikuti kontrak di `watchlist.md` (lihat Section 8: Invariants), dengan penegasan berikut:

- Jika trigger NO_TRADE aktif:
  - `recommendations.mode` harus `NO_TRADE` jika **tidak** ada posisi, atau `CARRY_ONLY` jika `has_position = true`.
  - `recommendations.allocations = []`
  - `groups.top_picks = []`
  - Kandidat boleh tetap dipublish sebagai monitoring (`groups.watch_only`) **tetapi** setiap kandidat wajib:
    - `timing.trade_disabled = true`
    - `levels.entry_style = "No-trade"` (atau style yang disepakati di kontrak)
    - `sizing.size_multiplier = 0.0`
