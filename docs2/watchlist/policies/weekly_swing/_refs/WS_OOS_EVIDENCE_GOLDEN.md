# WS OOS Evidence — Golden Proof Pack (NON-LADDER) (LOCKED)

## Purpose (LOCKED)
Dokumen ini adalah **bukti konkret** bahwa hasil kalibrasi WS lulus out-of-sample (OOS),
bukan sekadar aturan/prosedur.

Dokumen ini wajib bisa direplay:
- menunjuk `param_id` yang dipromote
- menunjuk window IS/OOS
- menunjuk tabel hasil (`watchlist_bt_oos_eval_ws`)
- menunjuk ringkasan metrik OOS yang dipakai untuk keputusan promote

## Required Inputs (LOCKED)
- `param_id_best_is`
- `from_date`, `to_date`
- `is_window`: (from_date .. is_end_date)
- `oos_window`: (oos_start_date .. to_date)
- `run_id` / `eval_id` (jika ada)

## Required Artifacts (LOCKED)
A) DB tables (lihat manifest #18):
- `watchlist_bt_eval` (IS results)
- `watchlist_bt_oos_eval_ws` (OOS results)

B) Minimal export (pilih salah satu, LOCKED):
- CSV export `watchlist_bt_oos_eval_ws` untuk `param_id_best_is` + window OOS, atau
- Screenshot hasil query + SQL query yang dipakai

## Canonical Query (LOCKED)
```sql
SELECT *
FROM watchlist_bt_oos_eval_ws
WHERE param_id = :param_id_best_is
  AND from_date = :oos_start_date
  AND to_date   = :to_date;
```

## OOS Summary (GOLDEN)
Isi ringkasan ini untuk 1 kandidat promote:

- `param_id_best_is`:
- OOS window:
- `picks_count_oos`:
- `avg_ret_net_top_oos`:
- `win_rate_top_oos`:
- `max_dd_top_oos` (jika ada):
- Verdict: **PASS / FAIL**

Kriteria PASS harus mengikuti:
- `../16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md`
- `../17_WS_WALK_FORWARD_OOS_PROOF_LOCKED.md`

## Evidence Attachment (LOCKED)
- [ ] Export CSV atau screenshot sudah tersimpan
- [ ] SQL query yang dipakai dicantumkan
- [ ] Paramset yang dipromote direferensikan (`param_id`)

## Scope
Dipakai untuk guard promote dan audit kualitas riset.

## Inputs
- Evidence OOS yang dikumpulkan dari proses riset/backtest.

## Outputs
- Patokan evidence OOS yang dianggap memadai.
