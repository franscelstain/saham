# 10 — CONFIRM Overlay (Intraday) — Weekly Swing

## Purpose
Menetapkan CONFIRM sebagai pengecekan keyakinan runtime yang tidak boleh mengubah PLAN.

## Prerequisites
### Weekly Swing
09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md

## Inputs
- plan_run aktif untuk plan_trade_date T
- runtime price (last_price) per ticker (minimal)
- bid/ask opsional (manual input)
- checked_at, snapshot_age_sec

## Process
### Universe confirm
hanya ticker yang PLAN display_bucket=SHOW (fokus).

### Staleness rule
- Jika snapshot_age_sec > confirm_overlay.snapshot_max_age_sec:
  - label = DELAY
  - reason WS_STALE
  - tetap disimpan (audit)

### Compute checks
1) drift_from_entry_pct:
   - dibandingkan entry_ref/entry band
   - jika drift > confirm_overlay.max_drift_from_entry_pct => CAUTION (WS_DRIFT_FAR)
2) entry band check:
   - jika last_price di luar band => CAUTION (WS_OUT_BAND)
3) spread check (jika bid/ask ada):
   - spread_pct = (ask-bid)/mid*100
   - jika > confirm_overlay.spread_max_pct => CAUTION (WS_SPR_WIDE)
   - jika bid/ask tidak ada => INFO (WS_SPR_NA)

### Label

### Mapping label ↔ label code (LOCKED)

- CONFIRMED = `WS_LBL_OK`
- NEUTRAL   = `WS_LBL_NEU`
- CAUTION   = `WS_LBL_WARN`
- DELAY     = `WS_LBL_DELAY`

### Label decision rule (LOCKED)

Tentukan label berdasarkan severity tertinggi dari reason CONFIRM:
1) Jika ada reason severity **BLOCK** ⇒ label **DELAY**.
2) Else jika ada reason severity **WARN** ⇒ label **CAUTION**.
3) Else jika hanya ada reason severity **INFO** (contoh: `WS_SPR_NA`) ⇒ label **NEUTRAL**.
4) Else (tidak ada reason) ⇒ label **CONFIRMED**.

- DELAY: stale atau missing runtime price
- CAUTION: ada warning drift/band/spread
- CONFIRMED: tidak ada warning dan runtime ada
- NEUTRAL: runtime ada tapi evaluasi terbatas (mis tanpa bid/ask) dan tidak warning

## Outputs
- confirm_check + confirm_items tersimpan terpisah.
- Tidak ada update ke plan tables.

## Failure modes
- Tidak ada plan aktif => CONFIRM tidak dijalankan jika tidak ada PLAN aktif (plan snapshot belum dibuat).

## Reference
- `_refs/WS_WORKED_EXAMPLE_E2E.md`
- `_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md`
- `_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`

## Next
### Weekly Swing
- 11_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md
