# 15 - WS Universe & Data-Quality Equivalence Contract (LOCKED)

## Purpose (LOCKED)
Backtest WS dianggap valid hanya jika universe selection dan data-quality guardrails pada backtest
SETARA (equivalent) dengan production PLAN untuk tanggal EOD yang sama.

“Setara” artinya:
- hasil pass/fail guardrail per ticker sama,
- alasan (reason_code) yang menjelaskan fail juga sama (atau mapping yang setara),
- aturan missing data sama,
- urutan eksekusi guardrail sama (agar reason prioritas konsisten).

Dokumen ini bersifat LOCKED dan menjadi kontrak anti-drift.

## Prerequisites
### Weekly Swing
14_WS_BT_COVERAGE_MATRIX_LOCKED.md

---

## Definitions (LOCKED)

### A) Universe Production (PLAN)
Universe production (PLAN) = himpunan ticker yang:
1) lolos pre-filter umum (aktif, eligible market, dsb),
2) memiliki data minimum untuk indikator yang dibutuhkan,
3) lolos semua guardrail WS.

### B) Universe Backtest
Universe backtest = output audit `watchlist_bt_universe_ws` untuk asof_eod_date yang sama.

### C) Equivalence
Untuk setiap `(asof_eod_date, ticker)`:

- `eligible_ok` (backtest) harus sama dengan eligible_plan (production).
- `required_ok` (backtest) hanya merepresentasikan data-quality (field required tersedia).
- `eligible_ok` (backtest) = required_ok AND guard_ok (data-quality + guardrails).
- `reason_code` (backtest) harus sama dengan reason utama production (PLAN) ketika fail.
- Jika production menghasilkan multiple reasons, backtest wajib mengikuti prioritas reason yang sama.

---

## Guardrails included (LOCKED)
Guardrails WS yang wajib setara:

1) Liquidity guard:
- metric: `dv20_idr`
- rule: dv20_idr >= min_dv20_idr

2) Volatility guard:
- metric: `atr14_pct`
- rule: atr14_pct <= max_atr14_pct

3) Volume participation guard:
- metric: `vol_ratio`
- rule: vol_ratio >= min_vol_ratio

4) Missing/invalid data guard (data-quality):
- indikator wajib tersedia (bukan NULL) sesuai kebutuhan scoring
- aturan default / fallback harus identik dengan production

Jika ada guardrail baru di production, dokumen ini wajib diupdate bersama backtest.

---

## Reason code equivalence (LOCKED)

### Rule: canonical fail reason
Jika satu ticker gagal lebih dari satu guardrail, reason utama dipilih berdasarkan prioritas:

Priority order (highest first):
1) MISSING_DATA (data-quality)
2) LIQUIDITY_FAIL
3) VOLATILITY_FAIL
4) VOLUME_RATIO_FAIL

Backtest dan production wajib memakai prioritas yang sama agar audit konsisten.

### Mapping rule
Jika production reason code berbeda nama dari backtest, wajib ada mapping 1:1 di dokumen ini, dan test harus memakai mapping tersebut.

---

## Missing data rules (LOCKED)

### Required fields
Backtest dan production wajib menggunakan daftar field minimal yang sama.
Contoh (sesuaikan dengan scoring WS yang aktif):
- close
- ma20, ma50, ma200 (jika dipakai)
- rsi14 (jika dipakai)
- roc20 (jika dipakai)
- atr14_pct
- dv20_idr
- vol_ratio

### Handling rule
- Jika field yang wajib = NULL atau invalid → fail dengan reason `MISSING_DATA`.
- Tidak boleh “diam-diam default 0” di backtest tapi “fail” di production (atau sebaliknya).
- Jika production menggunakan fallback tertentu, fallback itu harus ditulis eksplisit di sini.

---

## Equivalence verification (LOCKED)

### Test set (minimum)
Untuk setiap run backtest harian yang dipakai kalibrasi:
- pilih minimal 3 tanggal sample acak per bulan selama range backtest,
- untuk tiap tanggal sample, ambil:
  - 50 ticker yang lolos
  - 50 ticker yang gagal (gabungan dari semua reason)

### What must match
Untuk setiap sample ticker:
- pass/fail match
- canonical reason match (dengan mapping jika ada)

Acceptance:
- mismatch rate = 0% untuk pass/fail
- mismatch rate = 0% untuk canonical reason

---

## Audit queries (LOCKED)

Backtest side:
- `watchlist_bt_universe_ws` sebagai source of truth audit

Production side:
- PLAN universe snapshot output (wajib ada mode debug/export) berisi:
  - eligible_plan boolean
  - canonical_fail_reason_code
  - metrics snapshot (dv20_idr, atr14_pct, vol_ratio, missing_fields)

- Format snapshot WAJIB mengikuti schema resmi:
  `db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`

### Canonical audit queries (LOCKED)
Tujuan: membuktikan equivalence dan memudahkan root-cause saat mismatch.

Rule (LOCKED): Ticker identity mapping for audit join
Backtest universe dan production snapshot wajib bisa di-join dengan identitas ticker yang sama.

- Jika backtest memakai `ticker_id` (INT), maka audit join WAJIB memakai tabel mapping (contoh: `tickers`) untuk mendapatkan `ticker_code`.
- Jika backtest menyimpan `ticker_code` langsung, maka audit join memakai `ticker_code` tanpa mapping.

Canonical requirement (LOCKED):
Audit join harus menggunakan `ticker_code` sebagai identitas akhir, bukan asumsi `ticker_id == ticker_code`.

#### Q1. Mismatch pass/fail + reason (canonical)
> Input production snapshot diekspor/tersedia sebagai table/view sementara bernama `plan_universe_snapshot`
> dengan kolom minimal sesuai `PLAN_UNIVERSE_SNAPSHOT_SCHEMA`.

```sql
SELECT
  bt.asof_eod_date,
  bt.ticker_id,
  bt.eligible_ok      AS bt_eligible,
  ps.eligible_plan    AS prod_eligible,
  bt.reason_code      AS bt_reason,
  ps.canonical_fail_reason_code AS prod_reason,
  bt.dv20_idr, bt.atr14_pct, bt.vol_ratio, bt.missing_fields
FROM watchlist_bt_universe_ws bt
JOIN tickers t
  ON t.ticker_id = bt.ticker_id
JOIN plan_universe_snapshot ps
  ON ps.asof_eod_date = bt.asof_eod_date
 AND ps.ticker_code   = t.ticker_code
WHERE
  (bt.eligible_ok <> ps.eligible_plan)
  OR (
    bt.eligible_ok = 0
    AND COALESCE(bt.reason_code,'') <> COALESCE(ps.canonical_fail_reason_code,'')
  )
ORDER BY bt.asof_eod_date DESC
LIMIT 200;
```

Jika production belum punya export, wajib ditambahkan karena kontrak ini menuntut bukti.

## Next
### Weekly Swing
- 16_WS_EVAL_METRICS_SUFFICIENCY_LOCKED.md