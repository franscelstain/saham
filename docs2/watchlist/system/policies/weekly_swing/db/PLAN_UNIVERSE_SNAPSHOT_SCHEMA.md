# PLAN_UNIVERSE_SNAPSHOT_SCHEMA (LOCKED)

## Purpose
Mendefinisikan shape resmi export snapshot universe production untuk PLAN Weekly Swing agar bisa dibandingkan 1:1 terhadap universe backtest resmi dan dipakai sebagai bukti equivalence lintas sistem.

## Scope
Dokumen ini hanya mengatur **schema export proof** untuk snapshot universe PLAN.
Dokumen ini tidak mendefinisikan query export final, tidak mengunci nama file fisik export, dan tidak mengganti kontrak PLAN runtime utama.

## Inputs
- hasil universe production PLAN pada satu `asof_eod_date`,
- kontrak guard/data-quality Weekly Swing,
- kebutuhan equivalence terhadap `watchlist_bt_universe_ws`.

## Outputs
- field wajib export snapshot,
- aturan reason priority,
- aturan validasi field,
- rule mapping ke universe backtest.

## Key Unit
Unit pembanding canonical adalah:
- `(asof_eod_date, ticker_code)`

Satu row export mewakili satu ticker pada satu tanggal EOD.

## Official Required Fields (LOCKED)

| field | type | required | rules |
|---|---|---:|---|
| asof_eod_date | date | YES | tanggal EOD basis PLAN |
| ticker_code | string | YES | kode ticker |
| policy_code | string | YES | selalu `WS` |
| policy_version | string | YES | versi contract/rule yang dipakai |
| eligible_plan | boolean | YES | TRUE jika lolos semua guard + data-quality |
| canonical_fail_reason_code | string | IF eligible_plan=FALSE | reason utama menurut priority order |
| dv20_idr | number | YES | metric liquidity snapshot |
| atr14_pct | number | YES | metric volatility snapshot |
| vol_ratio | number | YES | metric volume ratio snapshot |
| missing_fields | array<string> or csv-string | YES | daftar field wajib yang missing/null/invalid |
| generated_at | timestamp | YES | waktu snapshot dibuat |
| plan_hash | string | YES | `meta.plan_hash` dari output PLAN canonical |

## Canonical Reason Priority (LOCKED)
Jika sebuah ticker gagal lebih dari satu kondisi, reason utama dipilih berdasarkan urutan berikut:
1. `WS_DATA_MISSING`
2. `WS_LIQ_FAIL`
3. `WS_ATR_HIGH`
4. `WS_VOLR_FAIL`

Jika implementasi production memakai naming internal yang berbeda, wajib ada mapping 1:1 pada dokumen:
- [`../15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](../15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md)

## Field Rules (LOCKED)

### `eligible_plan`
- `TRUE` hanya jika semua data wajib tersedia dan semua guardrail lolos.
- `FALSE` jika ada missing fields atau guard fail.

### `canonical_fail_reason_code`
- wajib terisi jika `eligible_plan = FALSE`
- harus mengikuti priority order di atas
- harus stabil untuk input yang sama

### `missing_fields`
- wajib selalu hadir, walau kosong
- jika non-empty, maka:
  - `eligible_plan` harus `FALSE`
  - `canonical_fail_reason_code` harus `WS_DATA_MISSING`

### `plan_hash`
- wajib berasal dari definisi canonical `meta.plan_hash`
- tidak boleh dihasilkan dari payload custom lain
- tujuannya adalah bukti bahwa export snapshot masih terkait ke PLAN canonical yang sebenarnya

## Backtest Equivalence Mapping (LOCKED)
Mapping minimum terhadap `watchlist_bt_universe_ws` adalah:
- `(asof_eod_date, ticker_code)` <-> key pembanding
- `eligible_plan` <-> `eligible_ok`
- `canonical_fail_reason_code` <-> `reason_code`

Jika ada naming difference di backtest layer, mapping eksplisit wajib ditulis di dokumen equivalence contract.

## Acceptance Rule (LOCKED)
Export snapshot PLAN dianggap sah hanya jika:
- semua required fields ada,
- satu row mewakili satu `(asof_eod_date, ticker_code)`,
- semantics reason sesuai priority order,
- `plan_hash` berasal dari PLAN canonical,
- dan shape export bisa dipakai langsung untuk audit equivalence.

## Reference
- [`../15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md`](../15_WS_UNIVERSE_EQUIVALENCE_CONTRACT_LOCKED.md)
- [`../02_WS_CANONICAL_RUNTIME_FLOW.md`](../02_WS_CANONICAL_RUNTIME_FLOW.md)
- [`../03_WS_DATA_MODEL_MARIADB.md`](../03_WS_DATA_MODEL_MARIADB.md)

## Boundary Note

Snapshot PLAN universe tidak identik dengan recommendation set. Recommendation adalah turunan berikutnya yang dibentuk dari PLAN snapshot.
