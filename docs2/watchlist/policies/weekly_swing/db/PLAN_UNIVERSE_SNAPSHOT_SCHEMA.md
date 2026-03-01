# PLAN_UNIVERSE_SNAPSHOT_SCHEMA (LOCKED)

## Goal (LOCKED)
Mendefinisikan format snapshot universe production (PLAN) agar bisa dibandingkan 1:1 dengan
audit universe backtest (`watchlist_bt_universe_ws`) untuk membuktikan kesetaraan guardrails dan data-quality.

Unit pembanding: (asof_eod_date, ticker_code)

Snapshot ini wajib bisa diekspor (mis. JSON file) dari production PLAN untuk tanggal EOD yang sama.

---

## Required fields (LOCKED)

| field | type | required | rules |
|---|---|---:|---|
| asof_eod_date | date | YES | tanggal EOD basis PLAN |
| ticker_code | string | YES | kode ticker |
| policy_code | string | YES | selalu `WS` |
| policy_version | string | YES | versi rule/guard yang dipakai (anti-drift) |
| eligible_plan | boolean | YES | TRUE jika lolos semua guardrail + data-quality |
| canonical_fail_reason_code | string | IF eligible_plan=FALSE | reason utama (lihat prioritas) |
| dv20_idr | number | YES | nilai metric guard liquidity (snapshot value) |
| atr14_pct | number | YES | nilai metric guard volatility (snapshot value) |
| vol_ratio | number | YES | nilai metric guard volume ratio (snapshot value) |
| missing_fields | array<string> or csv-string | YES | daftar field wajib yang missing/NULL/invalid |
| generated_at | timestamp | YES | waktu snapshot dibuat |
| plan_hash | string | YES | hash input + paramset untuk bukti immutability |

---

## Canonical reason priority (LOCKED)
Jika sebuah ticker gagal lebih dari satu guardrail, reason utama dipilih berdasarkan urutan berikut:

1) MISSING_DATA  
2) LIQUIDITY_FAIL  
3) VOLATILITY_FAIL  
4) VOLUME_RATIO_FAIL  

Catatan:
- Jika production memakai naming berbeda, wajib ada mapping 1:1 di dokumen equivalence contract.

---

## Field rules (LOCKED)

### eligible_plan
- TRUE hanya jika: tidak missing data wajib + semua guardrail lulus.

### canonical_fail_reason_code
- Wajib terisi jika eligible_plan=FALSE.
- Harus sesuai priority order.

### missing_fields
- Wajib selalu ada (boleh empty).
- Jika non-empty → eligible_plan harus FALSE dan canonical_fail_reason_code harus MISSING_DATA.

---

## Backtest mapping (LOCKED)
Mapping pembuktian kesetaraan terhadap `watchlist_bt_universe_ws`:

- (asof_eod_date, ticker_code) harus match sebagai key
- eligible_plan <-> required_ok
- canonical_fail_reason_code <-> reason_code

Jika backtest memakai reason_code berbeda, wajib definisikan mapping di:
`14_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`

END.