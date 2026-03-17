# 03 — WS Runtime Artifact Flow

## Purpose

Dokumen ini menjelaskan aliran artifact runtime yang harus dihasilkan aplikasi watchlist.

## Canonical Flow

### Step 1 — Build PLAN
Input EOD yang sah dibaca dan divalidasi.
Output `PLAN` dimaterialize sebagai artifact immutable untuk `trade_date`.

### Step 2 — Build RECOMMENDATION
Artifact `PLAN` dibaca ulang.
Output `RECOMMENDATION` dibentuk tanpa membaca `CONFIRM`.

### Step 3 — Build CONFIRM
Permintaan confirm mengikat ticker ke candidate `PLAN` yang sah.
Output `CONFIRM` dibentuk tanpa memutasi `PLAN` atau `RECOMMENDATION`.

### Step 4 — Build Composite View
Consumer view dapat menggabungkan:
- `PLAN`
- `RECOMMENDATION`
- `CONFIRM`

Composite view tidak boleh mengubah semantics artifact asal.

## Runtime Keys

Kunci minimum artifact-level yang harus konsisten pada artifact runtime yang sah:
- `strategy_code`
- `trade_date`
- `policy_code`
- `param_set_id`
- `policy_version`
- `schema_version`

Kunci minimum item-level yang harus konsisten bila artifact membawa scope ticker tunggal atau item candidate yang spesifik:
- `ticker`

Aturan interpretasi:
- `PLAN` dan `RECOMMENDATION` wajib membawa runtime keys artifact-level pada header artifact/read model.
- `CONFIRM` wajib membawa runtime keys artifact-level **dan** `ticker` karena artifact ini memang ticker-scoped.
- Untuk `PLAN`, `ticker` boleh hidup pada item candidate, bukan wajib sebagai header artifact.
- Untuk `RECOMMENDATION`, `ticker` boleh hidup pada `selected_items`, bukan wajib sebagai header artifact.

## Allowed States

- `PLAN only`
- `PLAN + RECOMMENDATION`
- `PLAN + CONFIRM (candidate still PLAN-rooted)`
- `PLAN + RECOMMENDATION + CONFIRM`

## Invalid States

- `RECOMMENDATION without PLAN`
- `CONFIRM without PLAN candidate`


## Terminology Guard

- `param_set_id` = identifier instance paramset aktif yang benar-benar dipakai artifact runtime.
- `policy_version` = versi policy Weekly Swing yang mengatur behavior artifact.
- `schema_version` = versi kontrak schema paramset.
- Istilah `paramset_version` tidak boleh dipakai lagi sebagai shorthand karena ambigu; ia dulu bisa dibaca sebagai versi instance paramset, versi policy, atau versi schema.


## Minimum Implementation Outputs

Implementasi yang sah minimal menghasilkan:
- `PLAN` artifact
- `RECOMMENDATION` artifact
- `CONFIRM` artifact
- source references antar artifact yang relevan
- reason-code / hash integrity yang relevan
- bukti test untuk core rules

## Traceability Pointer

Traceability detail implementasi dibaca bersama `02_WS_MODULE_MAPPING.md`, terutama untuk pemetaan:
- owner policy doc -> module area
- runtime artifact -> serializer / publisher / repository
- contract acceptance -> implementation test suite
