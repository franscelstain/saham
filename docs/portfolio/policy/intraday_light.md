# Policy: INTRADAY_LIGHT (Portfolio)

## Intent
Trade intraday ringan: risiko cepat dikontrol, posisi idealnya tidak menginap (atau menginap hanya dengan syarat ketat).

## Entry Validation (validateBuy)
- Session window: TBD (mis. only 09:00–10:30).
- Gap/slippage rule ketat: TBD.
- Liquidity minimum: TBD.

## Risk Management
- SL ketat, bisa fixed % atau ATR-based (TBD).
- Trailing cepat: TBD.

## Take Profit & Exit Rules
- TP lebih kecil, timeboxed intraday.
- Forced exit end-of-day: wajib (TBD jam cutoff).
- Overnight hold: default **tidak boleh** (atau boleh via config).

## Timebox
- Same-day, forced exit.

## Add / Reduce Rules
- ADD: biasanya tidak (TBD).
- Partial sell: TBD.

## Re-entry & Cooldown
- Cooldown intraday: TBD (mis. no re-entry same day).

## EOD Risk Events
- Jika masih OPEN mendekati close: generate `TIMEBOX_EXPIRED` + rekomendasi exit / auto-exit.

## Exit Advisory Rules
Aturan exit INTRADAY_LIGHT wajib menghormati cutoff sesi.

**Priority (ringkas):**
1) `STOP_HIT` → `SELL_NOW`
2) `EOD_CUTOFF` → `FORCED_EXIT` (`SELL_NOW`)
3) `TAKE_PROFIT_HIT` → `SELL_NOW` (umumnya tidak ladder)
4) `PORTFOLIO_HEAT_LIMIT` → `SELL_PARTIAL` (jika masih ada waktu) atau `SELL_NOW` (dekat cutoff)
5) else `HOLD`

**Overnight:**
- Default: `OVERNIGHT_NOT_ALLOWED` → posisi harus ditutup sebelum cutoff (kecuali config mengizinkan).

## Metrics
- `hold_minutes`
- `overnight` (boolean)
- `exit_reason_code` ∈ {TP, SL, TIMEBOX, MANUAL}

## Risk Budget & Sizing Notes
- Sizing paling konservatif: `max_heat_per_position_pct` kecil (TBD) karena timeframe pendek dan slippage relatif tinggi.
- `EOD_CUTOFF` adalah hard rule; jangan menahan risiko overnight kecuali config mengizinkan.

## Regime & Concentration Notes
- `NEUTRAL/RISK_OFF`: intraday menjadi paling sensitif; sizing diperkecil dan cutoff ditegakkan lebih ketat.
- Konsentrasi: batasi jumlah posisi intraday simultan untuk menghindari overtrading dan slippage.

## Cutoff Semantics (TBD but required)
Cutoff **MUST** ditentukan dalam format timestamp `session_cutoff_at` (WIB).

Placeholder config keys:
- `intraday_light.session_cutoff_at` (contoh: 15:45 WIB)
- `intraday_light.near_cutoff_minutes` (contoh: 10)

Rule:
- IF `now >= session_cutoff_at` THEN `decision = SELL_NOW` with `reason_codes += EOD_CUTOFF`.
