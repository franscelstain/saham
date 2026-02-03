# Policy: WEEKLY_SWING (Portfolio)

## Intent
Mengelola posisi swing dengan horizon mingguan, exit dipaksa jika timebox habis.

## Entry Validation (validateBuy)
- Aturan minimal: TBD
  - slippage/gap rule
  - liquidity rule
  - max open positions (opsional)
- Input: `plan_snapshot_json` + market context.

## Risk Management
- SL awal: dari plan snapshot (watchlist).
- Trailing rule: TBD
- Tighten SL rule: TBD

## Take Profit & Exit Rules
- TP: dari plan snapshot (watchlist).
- Exit tambahan: jika timebox expired (forced exit).
- Partial sell: TBD (allowed / not allowed).

## Timebox
- Default: 1 minggu trading (TBD detail: exit di hari apa, jam berapa).
- Event:
  - `TIMEBOX_EXPIRED`
  - `EXIT_FILLED`

## Add / Reduce Rules
- ADD: TBD (allowed only if plan contains add steps).
- REDUCE: TBD

## Re-entry & Cooldown
- Cooldown setelah close: TBD (mis. 1–2 sesi).
- Re-entry harus plan baru (`plan_id` baru).

## EOD Risk Events (eodRiskEvents)
- Update trailing SL (jika ada).
- Check timebox.
- Generate rekomendasi exit (event) bila syarat terpenuhi.

## Exit Advisory Rules
Aturan exit WEEKLY_SWING fokus pada disiplin mingguan.

**Priority (ringkas):**
1) `STOP_HIT` → `SELL_NOW`
2) `TIMEBOX_EXPIRED` → `FORCED_EXIT` (`SELL_NOW`)
3) `TAKE_PROFIT_HIT` → default `SELL_NOW`, atau `SELL_PARTIAL + TRAIL_STOP_UPDATE` jika mode ladder aktif
4) `NEGATIVE_EV` (mendekati timebox + profit kecil/flat) → `SELL_NOW` atau `SELL_PARTIAL` (config)
5) else `HOLD`

**TP Ladder (opsional, harus eksplisit):**
- Saat `TAKE_PROFIT_HIT`:
  - Jual `partial_pct` (mis. 50–70%).
  - Set trailing stop untuk sisa posisi:
    - `stop = max(current_stop, entry_price)` (profit lock minimal), lalu
    - `stop = max(stop, high_watermark - trail_pct)` (TBD).
  - Tetap `TIMEBOX_EXPIRED` memaksa exit sisa posisi.

**Timebox:**
- Default: posisi tidak boleh melewati akhir minggu trading (TBD hari/jam cutoff di implementasi).

## Metrics (Policy-specific)
- `hold_days` (wajib)
- `timebox_expired` (boolean)
- `exit_reason_code` ∈ {TP, SL, TIMEBOX, MANUAL}

## Example Timeline (TBD)
- Hari 1: ENTRY_FILLED
- Hari N: STOP_UPDATED
- Hari terakhir: TIMEBOX_EXPIRED → EXIT_FILLED → POSITION_CLOSED

## Risk Budget & Sizing Notes
- Disarankan `max_heat_per_position_pct` sedang (TBD), karena weekly swing bisa bergerak cepat.
- `TP Ladder` (jika aktif) wajib mengunci profit dengan trailing stop untuk sisa posisi.
- Jika `PORTFOLIO_HEAT_LIMIT` tercapai, weekly swing kandidat pertama untuk `SELL_PARTIAL` bila sudah profit kecil/flat.

## Regime & Concentration Notes
- `RISK_OFF`: weekly swing cenderung dikurangi lebih dulu jika profit kecil/flat dan mendekati timebox.
- Konsentrasi sektor: hindari terlalu banyak posisi weekly swing di sektor/theme yang sama dalam satu minggu.

## Timebox Semantics (TBD but required)
Timebox **MUST** ditentukan dalam format timestamp `timebox_end_at` (WIB).

Placeholder config keys:
- `weekly_swing.timebox_end_at` (contoh: Jumat 15:50 WIB)

Rule:
- IF `now >= timebox_end_at` THEN `decision = SELL_NOW` with `reason_codes += TIMEBOX_EXPIRED`.
