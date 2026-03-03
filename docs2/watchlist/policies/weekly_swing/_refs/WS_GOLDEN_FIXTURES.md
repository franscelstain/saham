# WS Golden Fixtures (LOCKED)

Fixture dipakai untuk memastikan E2E behavior stabil. Semua output harus deterministic.

## Format file fixture (LOCKED)
- fixtures/ws/fixture_A_input.json
- fixtures/ws/fixture_A_paramset.json
- fixtures/ws/fixture_A_expected_plan.json
- fixtures/ws/fixture_A_snapshot_confirm.json
- fixtures/ws/fixture_A_expected_confirm.json

Catatan: folder `fixtures/` adalah **artifact testing** dan berada di luar paket `docs.zip`. Dokumen ini hanya mendefinisikan kontrak struktur fixture-nya.

Catatan: aturan yang sama berlaku untuk fixture B dan C.

## Common input schema (LOCKED)
*_input.json harus menyediakan minimal:
- `asof_eod_date`: YYYY-MM-DD
- `trade_date`: YYYY-MM-DD
- `tickers[]`:
  - `ticker`
  - `close`
  - `high`
  - `low`
  - `volume`
  - indikator yang dipakai WS (minimum required untuk fixture): `roc20`, `atr14_pct`, `hh20`, `dv20_idr` (lihat `../08_WS_PLAN_ALGORITHM.md` dan `../09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`). 
  - Jika fixture menambahkan indikator lain, indikator tersebut wajib disebut eksplisit (nama kolom + definisi singkat) di fixture ini.
  - flag data readiness bila diperlukan (mis. `required_ok`, `coverage_flags`)

*_snapshot_confirm.json minimal:
- `checked_at`: timestamp
- `snapshot_ts`: timestamp
- `tickers[]`:
  - `ticker`
  - `bid`
  - `ask`
  - `last` (optional)
  - `entry_ref_runtime` bila digunakan (kalau tidak, omit)

## Fixture A: Normal Day (LOCKED)
Tujuan: menghasilkan TOP/SECONDARY/WATCH sesuai scoring dan cutoff.
- Data lengkap, coverage >= min_coverage_ratio
- Tidak ada outlier yang trigger block

**Expected:**
- expected_plan.json berisi minimal 1 top pick dan 1 secondary (atau sesuai target/cutoff paramset)
- expected_confirm.json berisi label CONFIRMED/NEUTRAL/CAUTION sesuai rule confirm

## Fixture B: EOD Incomplete (LOCKED)
Tujuan: memaksa FAILED sesuai rule data readiness.
- coverage < min_coverage_ratio

**Expected:**
- PLAN tidak menghasilkan picks dan menghasilkan status FAILED sesuai `02` canonical
- Fail code harus mencantumkan data readiness failure

## Fixture C: Outlier Day (LOCKED)
Tujuan: memastikan outlier_ruleset bekerja.
- minimal 1 ticker memiliki return/range yang melebihi threshold outlier_ruleset

**Expected:**
- ticker outlier tidak boleh masuk eligible set
- reason code outlier harus muncul pada ticker tersebut (BLOCK)
- sistem tidak crash dan tetap deterministic