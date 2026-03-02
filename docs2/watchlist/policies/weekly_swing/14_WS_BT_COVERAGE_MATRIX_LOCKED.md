# 14 - WS Backtest Coverage Matrix (LOCKED)

## Purpose (LOCKED)
Dokumen ini adalah bukti formal bahwa parameter ber-origin **BT**:
1) benar-benar tersimpan pada artefak backtest yang resmi,
2) benar-benar dipakai dalam langkah pemilihan (grouping/pick),
3) memiliki audit-proof yang dapat diverifikasi ulang.

Jika parameter origin=BT tidak punya mapping lengkap di matrix ini,
maka origin BT dianggap **tidak valid** (harus downgrade ke MAN/DET sampai coverage valid).

## Prerequisites
### Weekly Swing
- 13_WS_CONTRACT_TEST_CHECKLIST.md

---

## BT parameters in registry (LOCKED)
Berdasarkan `05_WS_PARAMETER_REGISTRY_COMPLETE.md`, parameter origin=BT untuk WS adalah:
- `grouping.top_min_score_q`
- `grouping.secondary_min_score_q`

Matrix ini dilarang memuat BT parameter lain di luar dua key tersebut
kecuali registry diupdate.

---

## Required artifacts (LOCKED)
Agar BT dapat dibuktikan (coverage + audit), backtest WS wajib memiliki artefak berikut:

1) `watchlist_bt_param_grid`
   - menyimpan nilai parameter yang di-grid-search (termasuk quantile cutoff)

2) `watchlist_bt_cutoffs_ws` (per tanggal, per param_id)
   - menyimpan cutoff score yang benar-benar dipakai hari itu
   - diperlukan agar audit tidak bergantung pada “recompute” yang rawan drift

3) `watchlist_bt_picks_ws`
   - menyimpan hasil picks + bucket_code + score_total

Jika artefak (2) dan/atau kolom bucket_code belum ada, BT coverage belum bisa dianggap proven.

---

## Gating rules for BT origin (LOCKED)
Sebuah parameter boleh origin=BT hanya jika:
- [COV-1] key ada di registry dan origin=BT
- [COV-2] nilai param tersebut tersimpan di artefak resmi grid (`watchlist_bt_param_grid`)
- [COV-3] param dipakai langsung pada step PICK/GROUPING
- [COV-4] cutoff score yang dipakai tersimpan (`watchlist_bt_cutoffs_ws`)
- [COV-5] picks menyimpan bucket_code sehingga bisa diverifikasi terhadap cutoff

---

## Coverage Matrix (LOCKED)

| registry_key | grid_column | used_in_step | rule_ref | audit_proof |
|---|---|---|---|---|
| `grouping.top_min_score_q` | `top_min_score_q` | PICK/GROUPING | `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md` (Quantile grouping TOP) | 1) `watchlist_bt_param_grid.top_min_score_q` (nilai q) 2) `watchlist_bt_cutoffs_ws.top_cutoff_score` (nilai cutoff) 3) `watchlist_bt_picks_ws.bucket_code='TOP_PICKS'` memastikan `score_total >= top_cutoff_score` |
| `grouping.secondary_min_score_q` | `secondary_min_score_q` | PICK/GROUPING | `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md` (Quantile grouping SECONDARY) | 1) `watchlist_bt_param_grid.secondary_min_score_q` (nilai q) 2) `watchlist_bt_cutoffs_ws.secondary_cutoff_score` (nilai cutoff) 3) `watchlist_bt_picks_ws.bucket_code='SECONDARY'` memastikan `score_total >= secondary_cutoff_score` (dan < TOP cutoff bila mode eksklusif) |

---

## Verification queries (LOCKED)

### V1. Ensure BT params exist in grid
Untuk setiap BT param di registry:
- wajib ada kolom grid_column di `watchlist_bt_param_grid`.

### V2. Ensure cutoff scores are persisted
Untuk setiap `(param_id, asof_eod_date)` yang menghasilkan picks:
- wajib ada 1 row di `watchlist_bt_cutoffs_ws`.

### V3. Ensure picks satisfy cutoffs
Untuk setiap pick:
- jika bucket TOP_PICKS: `score_total >= top_cutoff_score`
- jika bucket SECONDARY: `score_total >= secondary_cutoff_score`

## Next
### Weekly Swing
- 15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md