# Policy: POSITION_TRADE (Portfolio)

## Intent
Memegang posisi lebih lama (multi-week / multi-month) dengan manajemen risiko lebih longgar dan fokus tren.

## Entry Validation (validateBuy)
- Lebih toleran pada gap/slippage dibanding weekly swing (TBD).
- Konfirmasi trend dari plan snapshot (portfolio tidak recompute) — hanya validasi constraint.

## Risk Management
- SL awal dari plan, trailing lebih lambat.
- Update SL berdasarkan milestone (TBD).

## Take Profit & Exit Rules
- TP bisa bertahap atau “let winners run” (TBD).
- Exit reason: TP/SL/TIMEBOX/MANUAL.

## Timebox
- Lebih panjang (TBD).

## Add / Reduce Rules
- ADD bisa diizinkan (pyramiding) jika plan mengandung langkahnya.
- Partial sell lebih mungkin diizinkan (TBD).

## Re-entry & Cooldown
- Cooldown setelah close: TBD.

## EOD Risk Events
- Trailing SL update.
- Timebox check.
- Risk event terkait breakdown (TBD, tapi jangan recompute sinyal watchlist).

## Exit Advisory Rules
Aturan exit POSITION_TRADE lebih longgar dan fokus proteksi tren.

**Priority (ringkas):**
1) `STOP_HIT` → `SELL_NOW`
2) `TIMEBOX_EXPIRED` (jika policy memakai timebox) → `FORCED_EXIT` (`SELL_NOW`)
3) `TAKE_PROFIT_HIT` → bisa `SELL_PARTIAL` (scale-out) + `TRAIL_STOP_UPDATE`
4) `NEGATIVE_EV` (berdasar scorecard + posisi stagnan) → `REDUCE_RISK` / `SELL_PARTIAL`
5) else `HOLD`

**Scale-out (opsional):**
- Saat TP milestone tercapai: jual sebagian, sisanya trailing stop lambat.

## Metrics
- `hold_days`
- `max_drawdown_pct`
- `exit_reason_code`

## Risk Budget & Sizing Notes
- Sizing bisa lebih besar dibanding weekly swing, tapi total heat tetap dibatasi.
- Scale-out + trailing stop lambat disarankan untuk mengurangi drawdown sambil membiarkan upside.

## Regime & Concentration Notes
- `RISK_OFF`: position trade boleh dipertahankan jika masih sesuai trailing stop, tetapi add/pyramiding harus dimatikan.
- Konsentrasi: position trade sering membentuk bias sektor; gunakan cap market value per sektor.

## Level Semantics (Anti Salah Tafsir)
Istilah seperti "breakdown" **MUST** diikat ke level eksplisit di plan snapshot, misalnya:
- `guard_level_price` atau gunakan `stop_price` sebagai level invalidasi.

Rule:
- IF `price_basis <= guard_level_price` THEN `decision = SELL_NOW` with `reason_codes += THESIS_INVALID` (atau `STOP_HIT` jika sama dengan stop).
