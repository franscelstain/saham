# DB Artifacts — Weekly Swing (WS)

Folder ini berisi artefak SQL khusus policy Weekly Swing.
Dokumen bernomor di folder utama (dokumen bernomor 01–13 di folder `weekly_swing/`) menjelaskan kapan dan bagaimana artefak ini dipakai.

- `BACKTEST_SCHEMA_DDL.sql` — DDL tabel backtest WS.
- `REASON_CODES_SEED.sql` — seed reason codes policy WS (insert ke tabel global `watchlist_reason_codes`).
- `PROMOTE_PARAMSET.sql` — prosedur/skrip promote paramset (operator artifact).
- `PARAMSET_WS_ACTIVE_EXAMPLE.json` — contoh payload params_json ACTIVE untuk bootstrap/dev.
