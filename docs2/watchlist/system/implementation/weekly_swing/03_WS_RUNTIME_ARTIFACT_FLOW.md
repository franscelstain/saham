# 03 — WS Runtime Artifact Flow

## Purpose

Dokumen ini menjelaskan aliran artifact runtime yang harus dihasilkan aplikasi watchlist.

## Canonical Flow

### Step 1 — Build PLAN
Input upstream producer-facing yang sah dari `market-data` dibaca dan divalidasi. Intake ini harus publication-aware dan tunduk pada kontrak consumer-readable/downstream-readable milik producer.
Anchor minimum untuk Step 1 adalah:
- `docs/market_data/book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `docs/market_data/book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `docs/market_data/book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
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


## Invalid intake shortcuts
Step 1 tidak boleh diimplementasikan sebagai salah satu shortcut berikut:
- membaca raw bars atau raw indicators lalu menganggapnya otomatis setara dengan intake consumer-facing
- membaca session snapshot internals sebagai source utama PLAN
- membaca technical switching artifacts sebagai pengganti current publication semantics
- membuat istilah baru `input EOD sah` tanpa anchor ke kontrak producer-facing `market-data`

## Layer Mapping by Runtime Step

| Runtime step | Canonical layer | Primary object boundary | Read/Persist mode | Forbidden drift |
|---|---|---|---|---|
| Step 1 — Upstream Intake Read | producer-facing read adapter / intake repository | upstream intake DTO / normalized result object | read-only upstream | application service/domain compute membaca raw internals producer langsung |
| Step 2 — PLAN Build | domain compute + artifact assembler | PLAN compute input DTO -> PLAN result object -> PLAN artifact payload | compute, lalu persistence via repository | service menghitung scoring sendiri, presenter membentuk PLAN |
| Step 3 — RECOMMENDATION Build | domain compute + artifact assembler | recommendation input DTO/result object | compute, lalu persistence via repository | recommendation membaca CONFIRM atau source di luar PLAN |
| Step 4 — CONFIRM Build | orchestration binder + domain compute + artifact assembler | confirm input DTO/result object | compute, lalu persistence via repository | confirm memutasi recommendation membership/rank |
| Step 5 — Composite View / Response Exposure | application orchestration + presenter/transport DTO shaping | composite read result -> response DTO | read-only consumer exposure | transport mengambil policy decision atau persistence write |

## DTO / Result Object Expectations

- Step 1 wajib menghasilkan object intake yang cukup bersih untuk dipakai layer berikutnya, bukan raw rows yang dibiarkan hidup sebagai kontrak aktif.
- Step 2–4 wajib memisahkan compute input, compute result, dan artifact payload.
- Step 5 wajib memakai response DTO/presenter output yang berbeda dari persistence payload.

## Forbidden Layer Drift

1. request mentah langsung dipakai sebagai compute input PLAN / RECOMMENDATION / CONFIRM;
2. result query upstream langsung dipakai sekaligus sebagai artifact payload dan response DTO;
3. domain compute menulis persistence atau membentuk response transport final;
4. repository/persistence adapter menjalankan scoring, ranking, atau confirm decision.

