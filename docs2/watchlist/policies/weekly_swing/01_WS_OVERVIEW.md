# 01 — Weekly Swing Watchlist (EOD) — Book

## Purpose
Mendefinisikan policy **Weekly Swing (WS)** berbasis EOD dengan target kualitas 95–100%.
Policy ini menghasilkan:
- **PLAN** dari data EOD hari ini untuk rekomendasi besok.
- **CONFIRM** sebagai overlay runtime yang **tidak boleh** memodifikasi PLAN.

## Policy identifiers (LOCKED)
- `policy_code` (DB/persistence): `WS`
- `policy` (UI/meta schema): `WEEKLY_SWING`
- Aturan: `policy_code` dan `policy` tidak boleh saling menggantikan; keduanya punya domain berbeda (DB vs UI).

## Prerequisites
### Shared Global
- Baca kontrak global paramset di `../_shared/` (dokumen kontrak paramset global).

### Reference
- `_refs/WS_FAILURE_BEHAVIOR_MATRIX.md`
- `_refs/WS_GLOSSARY_LOCKED.md`
- `_refs/WS_GOLDEN_MANUAL_INPUT_TEMPLATE.md`
- `_refs/WS_WORKED_EXAMPLE_E2E.md`
- `_refs/WS_RUNTIME_OUTPUT_SCHEMA.md`
- `_refs/WS_RUNTIME_OUTPUT_EXAMPLES.md`
- `_refs/WS_OOS_EVIDENCE_GOLDEN.md`

## Governance & Audit (Index)
- `_refs/WS_PARAMETER_COVERAGE_MATRIX.md` — bukti 1 halaman bahwa runtime params punya coverage di contract/registry/validator serta algoritma eksekusi WS (`08_WS_PLAN_ALGORITHM.md`, `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`).

## Inputs
- EOD OHLCV (asof_eod_date)
- EOD indicators (asof_eod_date)
- Paramset WS (ACTIVE)

## Reading Rules (WS folder)

## Konsep kunci (WS)
- PLAN snapshot immutable untuk `plan_trade_date` (next trading day).
- CONFIRM overlay intraday (bisa berubah 5–10 menit), hanya memberi label dan alasan tambahan.

## Isi dokumen (urut)
02 → 20 berurutan:
- 02: Eksekusi canonical (PLAN + CONFIRM) — urutan eksekusi
- 03: Schema & data model WS (MariaDB 10.4) 
- 04: Kontrak params_json WS (termasuk key deterministik)
- 05: Parameter registry WS (exhaustive)
- 06: Validator WS (tambahan di atas global)
- 07: Reason codes WS + hash field list WS
- 08: Algoritma PLAN WS
- 09: Selection dinamis deterministik (SHOW/HIDE)
- 10: CONFIRM overlay WS
- 11: Tabel Input Manual Intraday Snapshot (CONFIRM)
- 12: Backtest schema & calibration WS
- 13: Contract tests WS (tambahan di atas global)
- 14: BT Coverage Matrix (LOCKED)
- 15: Universe & Data-Quality Equivalence Contract (LOCKED)
- 16: Evaluation Metrics Sufficiency (LOCKED)
- 17: Walk-forward / Out-of-Sample Proof (LOCKED)
- 18: Backtest Artifact Manifest (LOCKED)
- 19: Deprecated / Non-scope Artifacts Ledger
- 20: Canonical procedures WS (paramset promotion & active pick)
- Catatan: artefak SQL (seed/promote/backtest DDL) berada di folder `db/` pada level Weekly Swing.

## Artefak SQL

- Backtest universe (wajib): `watchlist_bt_universe_ws` dibuat di `db/BACKTEST_SCHEMA_DDL.sql` (bukan dokumen bernomor).
Artefak SQL untuk Weekly Swing berada di folder `db/` dan **bukan** bagian dari urutan dokumen `01_..20_` (MD).

File yang tersedia saat ini:
- `db/BACKTEST_SCHEMA_DDL.sql`
- `db/PROMOTE_PARAMSET.sql`
- `db/REASON_CODES_SEED.sql`
- `db/PARAMSET_WS_ACTIVE_EXAMPLE.json`

## Next
### Weekly Swing
- 02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md
