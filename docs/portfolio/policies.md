# Portfolio Policies (Index)

Dokumen ini adalah **peta** policy untuk fitur Portfolio.

## Kontrak lintas policy (wajib untuk semua)
- Portfolio menyimpan `plan_snapshot_json` immutable saat OPEN.
- Setiap posisi wajib punya `policy_code`, `strategy_code`, `plan_id`.
- Ingest trade harus idempotent (`trade_hash` / `external_ref`).
- Semua keputusan lifecycle menghasilkan `PositionEvent`.
- Semua policy mengikuti **Exit Advisory Framework** di `portfolio.md`, dan hanya boleh menyimpang jika tertulis eksplisit di dokumen policy.

## Daftar Policy

| Policy Code | Status | Dokumen | Implementasi (Class) |
|---|---|---|---|
| WEEKLY_SWING | Active | policy/weekly_swing.md | TBD |
| DIVIDEND_SWING | Active | policy/dividend_swing.md | TBD |
| INTRADAY_LIGHT | Active | policy/intraday_light.md | TBD |
| POSITION_TRADE | Active | policy/position_trade.md | TBD |

## Catatan
- Watchlist menampilkan output per policy.
- Portfolio menampilkan **semua posisi aktif** lintas policy, tetapi aturan lifecycle dipilih dari `policy_code` milik posisi.

## V4 Additions
- Regime Layer (RISK_ON/NEUTRAL/RISK_OFF)
- Concentration & Correlation Controls
- Post-Trade Compliance & Journaling
- Performance Attribution & Review Workflow

## Mapping (Source of Truth)

Setiap policy **MUST** punya mapping konsisten:
- `policy_code`
- `doc_path`
- `config_key`
- `class_name`

Policy doc **MUST NOT** menambah enum baru (Decision/Reason) tanpa update `portfolio.md`.
