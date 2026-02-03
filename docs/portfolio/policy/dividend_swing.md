# Policy: DIVIDEND_SWING (Portfolio)

## Intent
Memegang posisi untuk memanfaatkan event dividen (pre/post), dengan aturan exit yang bisa berbeda dari weekly swing.

## Entry Validation (validateBuy)
- Harus sesuai window event dividen (TBD).
- Gap rule khusus ex-date: TBD.

## Risk Management
- SL awal: dari plan snapshot.
- Trailing: TBD.
- Volatility guard sekitar ex-date: TBD.

## Take Profit & Exit Rules
- Exit bisa:
  - sebelum ex-date (capture run-up),
  - setelah ex-date (mean reversion),
  - forced exit by timebox.
- Rule final: TBD (pilih satu skema, jangan abu-abu).

## Timebox
- Ditentukan oleh plan (mis. “H-? sampai H+?”).
- Forced exit: TBD.

## Add / Reduce Rules
- ADD: TBD
- REDUCE: TBD

## Re-entry & Cooldown
- Cooldown: TBD

## EOD Risk Events
- Check proximity to ex-date.
- Tighten SL rules near event.
- Forced exit rule.

## Exit Advisory Rules
Aturan exit DIVIDEND_SWING tergantung mode (PRE/POST). Portfolio tidak menebak; ia mengikuti mode di `plan_snapshot_json`.

**Priority (ringkas):**
1) `STOP_HIT` → `SELL_NOW`
2) `DIVIDEND_WINDOW_END` / `TIMEBOX_EXPIRED` → `FORCED_EXIT` (`SELL_NOW`)
3) `TAKE_PROFIT_HIT` → `SELL_NOW` atau `SELL_PARTIAL` (policy-defined)
4) `NEGATIVE_EV` dekat ex-date (risk meningkat) → `REDUCE_RISK` / `SELL_PARTIAL`
5) else `HOLD`

**Window rules (TBD):**
- PRE: exit sebelum ex-date atau saat window berakhir.
- POST: exit setelah ex-date + syarat minimal recovery (TBD).

## Metrics
- `dividend_mode` ∈ {PRE, POST, HYBRID} (TBD)
- `days_to_ex_date_at_entry`
- `exit_reason_code`

## Risk Budget & Sizing Notes
- Risk meningkat dekat ex-date; disarankan sizing lebih konservatif saat entry mendekati event.
- Jika volatilitas meningkat (TBD proxy), portfolio boleh memberi `REDUCE_RISK` meskipun belum kena SL.

## Regime & Concentration Notes
- `RISK_OFF`: hindari membuka posisi baru menjelang event; posisi existing diprioritaskan untuk reduce jika volatilitas meningkat.
- Konsentrasi: dividend play biasanya mengelompok; wajib ada cap per theme agar tidak “all-in satu cerita”.

## Dividend Window Semantics (TBD but required)
Plan snapshot **MUST** membawa:
- `dividend_mode` ∈ {PRE, POST}
- `dividend_window_end_at` (WIB)

Rule:
- IF `now >= dividend_window_end_at` THEN `decision = SELL_NOW` with `reason_codes += DIVIDEND_WINDOW_END`.
