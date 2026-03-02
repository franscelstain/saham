# WS Backtest Artifact Manifest (LOCKED)

## Purpose (LOCKED)
Dokumen ini adalah daftar resmi artefak (tabel/file) yang dianggap EXIST dan DIPAKAI
oleh backtest & calibration.

Jika sebuah dokumen lain menyebut artefak yang tidak ada di manifest ini,
maka salah satu wajib terjadi:
- artefak itu ditambahkan ke manifest + schema, atau
- penyebutan itu dipindahkan ke Deprecation Ledger (non-scope) dan dinyatakan tidak dipakai.

Dokumen ini mencegah drift akibat referensi artefak “hantu”.

## Prerequisites
### Weekly Swing
17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md

---

## A) Official WS backtest tables (LOCKED)
1) watchlist_bt_param_grid
2) watchlist_bt_eval
3) watchlist_bt_picks_ws
4) watchlist_bt_universe_ws
5) watchlist_bt_cutoffs_ws
6) watchlist_bt_oos_eval_ws

Catatan audit (wajib):
- watchlist_bt_picks_ws wajib menyimpan bucket_code untuk membuktikan hasil grouping terhadap cutoff score.

Catatan (LOCKED):
- watchlist_bt_oos_eval_ws wajib ada untuk promote paramset menjadi ACTIVE (lihat dok 17).

---

## B) Official production/PLAN artifacts required for proof (LOCKED)
1) PLAN universe snapshot export (format: db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md)

---

## C) Official docs that govern these artifacts (LOCKED)
- 12_WS_BACKTEST_SCHEMA_AND_CALIBRATION.md
- 14_WS_BT_COVERAGE_MATRIX_LOCKED.md
- 15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md
- 16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md
- 17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md

## Next
### Weekly Swing
- 19_WS_DEPRECATED_OR_NONSCOPE_ARTIFACTS_LEDGER.md