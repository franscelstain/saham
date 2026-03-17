# 15 - WS Universe & Data-Quality Equivalence Contract (LOCKED)

## Purpose (LOCKED)

Dokumen ini mengunci kesetaraan universe dan guardrail outcome antara production PLAN dan backtest WS; dokumen ini tidak mendefinisikan ulang algoritma scoring.

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

Daftar guardrail di bawah ini harus dibaca sebagai minimum equivalence set. Jika production menambah guardrail normatif baru, kontrak equivalence ini wajib ikut diperbarui.

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

Owner vocabulary reason code tetap pada file 07; dokumen ini hanya mengunci equivalence outcome dan prioritasnya.


### Rule: canonical fail reason
Jika satu ticker gagal lebih dari satu guardrail, reason utama dipilih berdasarkan prioritas berikut dan **harus memakai kode WS_* yang sama** di backtest maupun production.

Priority order (highest first):
1) `WS_DATA_MISSING` — data-quality / required fields tidak lengkap atau invalid
2) `WS_LIQ_FAIL` — liquidity guard gagal (`dv20_idr < min_dv20_idr`)
3) `WS_ATR_HIGH` — volatility upper guard gagal (`atr14_pct > max_atr14_pct`)
4) `WS_VOLR_FAIL` — volume participation guard gagal (`vol_ratio < min_vol_ratio`)

Backtest dan production wajib memakai prioritas yang sama agar audit konsisten.

## Production snapshot contract for equivalence proof (LOCKED)

Shape proof snapshot production untuk audit equivalence dimiliki oleh dokumen ini. Artefak pada folder `db/` hanya merealisasikan shape ini sebagai schema / implementation artifact.

### Required fields
Setiap row snapshot production wajib menyediakan:

| field | required | rules |
|---|---:|---|
| `asof_eod_date` | YES | tanggal EOD basis PLAN |
| `ticker_code` | YES | identitas ticker canonical untuk audit join |
| `policy_code` | YES | untuk Weekly Swing nilainya `WS` |
| `policy_version` | YES | versi contract/rule yang dipakai |
| `eligible_plan` | YES | `TRUE` hanya jika data-quality dan guardrail lolos |
| `canonical_fail_reason_code` | IF `eligible_plan = FALSE` | reason utama mengikuti priority order dokumen ini |
| `dv20_idr` | YES | snapshot metric liquidity |
| `atr14_pct` | YES | snapshot metric volatility |
| `vol_ratio` | YES | snapshot metric volume participation |
| `missing_fields` | YES | daftar field wajib yang missing / invalid; boleh array string atau representasi CSV stabil |
| `generated_at` | YES | waktu snapshot proof dibuat |
| `plan_hash` | YES | harus berasal dari `meta.plan_hash` PLAN canonical |

### Field rules
- `missing_fields` wajib selalu hadir, walau kosong.
- Jika `missing_fields` non-empty, maka `eligible_plan = FALSE` dan `canonical_fail_reason_code = WS_DATA_MISSING`.
- `plan_hash` tidak boleh dibuat dari payload custom lain; nilainya wajib mengikuti kontrak PLAN canonical.
- Satu row snapshot mewakili tepat satu `(asof_eod_date, ticker_code)`.

### Acceptance
Proof snapshot equivalence dianggap sah hanya jika:
- required fields tersedia,
- semantics `eligible_plan` dan `canonical_fail_reason_code` mengikuti dokumen ini,
- dan shape snapshot bisa dipakai langsung untuk join audit dengan `watchlist_bt_universe_ws`.

### Mapping rule (LOCKED)
Untuk guardrails yang dicakup dokumen ini, **tidak ada alias nama**. Mapping 1:1 canonical adalah:

- `MISSING_DATA`          => `WS_DATA_MISSING`
- `LIQUIDITY_FAIL`        => `WS_LIQ_FAIL`
- `VOLATILITY_FAIL`       => `WS_ATR_HIGH`
- `VOLUME_RATIO_FAIL`     => `WS_VOLR_FAIL`

Aturan tambahan (LOCKED):
- Code alias seperti `WS_GUARD_LIQUIDITY_FAIL` **tidak boleh** dipakai.
- Jika di masa depan production/backtest menambah guardrail baru, dokumen ini **wajib** diperbarui dengan mapping WS_* yang eksplisit sebelum artefak dianggap setara.

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
- Jika field yang wajib = NULL atau invalid → fail dengan reason `WS_DATA_MISSING`.
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

Dokumen ini mengunci audit proof shape, bukan nama file export ad-hoc.


Backtest side:
- `watchlist_bt_universe_ws` sebagai source of truth audit

Production side:
- PLAN universe snapshot output (wajib ada mode debug/export) berisi:
  - eligible_plan boolean
  - canonical_fail_reason_code
  - metrics snapshot (dv20_idr, atr14_pct, vol_ratio, missing_fields)

- Format snapshot WAJIB mengikuti schema resmi:
  [`db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md`](db/PLAN_UNIVERSE_SNAPSHOT_SCHEMA.md)

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