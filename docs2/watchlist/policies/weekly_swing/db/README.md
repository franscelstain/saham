# DB Artifacts — Weekly Swing (WS)

> **Status:** LOCKED (Normative)
> **Doc Role:** Weekly Swing DB layer index


## Purpose
Entry point artefak database dan seed untuk policy Weekly Swing.

## Scope
Menjelaskan file SQL/DB yang menjadi dasar persistence, seed, dan promote paramset WS.

## Inputs
- Engineer DB, reviewer, dan auditor implementasi WS.

## Outputs
- Peta baca artefak DB Weekly Swing.

Folder ini berisi artefak SQL dan schema pendukung yang **khusus** untuk policy Weekly Swing.
Folder ini **bukan** sumber aturan bisnis utama; aturan normatif tetap ada di dokumen bernomor `01`–`20` di folder `weekly_swing/`.

## Start here
Urutan baca yang benar:
1. `../01_WS_OVERVIEW.md`
2. `../03_WS_DATA_MODEL_MARIADB.md`
3. `../12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md`
4. Artefak di folder ini ([`./`](./README.md))

## Source of truth
- **Kontrak policy / perilaku / output**: dokumen bernomor di folder utama `weekly_swing/`
- **DDL/SQL operasional WS**: file di folder ini ([`./`](./README.md))
- Jika ada konflik, **dokumen bernomor** menang; SQL wajib disesuaikan.

## Isi folder
- [`BACKTEST_SCHEMA_DDL.sql`](BACKTEST_SCHEMA_DDL.sql) — DDL tabel backtest WS.
- [`PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`](PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md) — schema snapshot universe PLAN untuk bukti equivalence vs universe backtest.
- [`REASON_CODES_SEED.sql`](REASON_CODES_SEED.sql) — seed reason codes policy WS ke tabel global `watchlist_reason_codes`.
- [`PROMOTE_PARAMSET.sql`](PROMOTE_PARAMSET.sql) — util/prosedur promosi paramset WS dengan gate minimum.
- [`PARAMSET_WS_ACTIVE_EXAMPLE.json`](PARAMSET_WS_ACTIVE_EXAMPLE.json) — contoh payload `params_json` ACTIVE untuk bootstrap/dev.

## Aturan penggunaan
- File di folder ini dipakai **setelah** kontrak policy dipahami.
- Dilarang menjadikan SQL di sini sebagai sumber interpretasi bisnis kalau belum cocok dengan dokumen bernomor.
- Setiap perubahan breaking pada folder ini wajib diikuti update dokumen policy terkait, validator, dan contract tests.

## Minimum operasional yang harus lolos
Sebelum WS dianggap siap jalan:
- DDL backtest berhasil dibuat tanpa drift terhadap dokumen.
- reason codes seed sinkron dengan dictionary yang dipakai runtime.
- snapshot universe PLAN bisa diekspor sesuai [`PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`](PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md).
- promote paramset hanya mengaktifkan paramset yang lolos gate minimum yang sudah dikunci.
