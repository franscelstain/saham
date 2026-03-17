# 07 — WS Delivery Checklist

## Purpose

Checklist ini dipakai saat menerjemahkan Weekly Swing dari system docs ke aplikasi watchlist.

Checklist ini adalah **delivery gate**, bukan checklist informatif biasa.  
Build harus ditolak bila hard-stop items gagal.

## Scope Lock

- watchlist only
- weekly_swing only
- bukan portfolio
- bukan execution
- bukan market-data internals

## Gate 1 — Scope Gate

- [ ] baseline `weekly_swing` sudah difreeze
- [ ] audit baseline watchlist digunakan sebagai guardrail
- [ ] owner docs sudah dipahami
- [ ] tidak ada perluasan domain ke portfolio
- [ ] tidak ada perluasan domain ke execution
- [ ] tidak ada perluasan domain ke market-data internals
- [ ] implementation guidance tetap tunduk pada owner docs
- [ ] support artifacts tidak diperlakukan sebagai owner rule

## Gate 2 — Contract Gate

- [ ] `PLAN` dibangun terlebih dahulu dan dipublish/freeze sebagai artifact
- [ ] `RECOMMENDATION` hanya berasal dari `PLAN`
- [ ] `RECOMMENDATION` tidak membaca `CONFIRM`
- [ ] `RECOMMENDATION` dapat tersedia tanpa `CONFIRM`
- [ ] `RECOMMENDATION` dapat kosong
- [ ] `CONFIRM` hanya berlaku untuk candidate `PLAN` yang sah
- [ ] non-recommended candidate tetap dapat di-confirm bila eligible
- [ ] `CONFIRM` tidak memutasi recommendation membership/rank/score/label
- [ ] consumer/composite view tidak mengubah source semantics
- [ ] path/reference tidak drift dari baseline freeze

## Gate 3 — API / Persistence Gate

### Read / API
- [ ] endpoint watchlist hanya bersifat read/suggestion
- [ ] tidak ada endpoint buy/sell di domain ini
- [ ] confirm input manual tervalidasi
- [ ] unknown top-level field selalu ditolak pada confirm request boundary
- [ ] field canonical minimum API dipakai persis seperti di `04_WS_API_GUIDANCE.md`
- [ ] recommendation endpoint tidak memasukkan ticker di luar PLAN candidate set
- [ ] composite endpoint memisahkan plan/recommendation/confirm secara eksplisit

### Persistence
- [ ] artifact watchlist dipisah: PLAN / RECOMMENDATION / CONFIRM
- [ ] `RECOMMENDATION` merefer ke source `PLAN`
- [ ] `CONFIRM` merefer ke source `PLAN`
- [ ] tidak ada persistence holdings/portfolio di domain ini
- [ ] tidak ada persistence execution/order/broker di domain ini
- [ ] tidak ada back-mutation business fields setelah publish
- [ ] field canonical minimum persistence dipakai persis seperti di `05_WS_PERSISTENCE_GUIDANCE.md` / `05A_WS_CANONICAL_FIELD_MATRIX.md`

## Gate 4 — Audit Evidence Gate

- [ ] daftar endpoint/fungsi yang diimplementasikan tersedia
- [ ] contoh payload valid tersedia
- [ ] contoh payload invalid tersedia
- [ ] hasil deterministic tests tersedia
- [ ] hasil contract tests tersedia
- [ ] hasil empty recommendation tests tersedia
- [ ] hasil non-recommended candidate confirm tests tersedia
- [ ] hasil no-mutation tests tersedia
- [ ] bukti persistence separation tersedia
- [ ] system docs dan implementation guidance sinkron

## Hard Stop Rejection Criteria

Delivery **must be rejected** bila salah satu kondisi berikut terjadi:
1. implementation memakai data di luar PLAN untuk membangun RECOMMENDATION
2. confirm mengubah ranking/group/membership/score recommendation
3. artifact watchlist bercampur dengan execution/order/broker state
4. artifact watchlist bercampur dengan portfolio/holding state
5. ticker non-candidate PLAN dapat di-confirm
6. support artifacts override owner docs
7. contract fields drift tanpa update owner docs
8. composite view mengaburkan source semantics
9. baseline freeze digeser diam-diam saat implementasi

## Final Rule

Checklist delivery dianggap lolos hanya bila:
- scope tetap utuh
- contract tetap utuh
- API/persistence separation tetap utuh
- audit evidence minimum tersedia


## Merge Readiness Focus

- [ ] `PLAN` artifact sudah immutable sebelum `RECOMMENDATION` dibentuk
- [ ] `RECOMMENDATION` hanya membaca `PLAN` immutable
- [ ] `RECOMMENDATION` dapat tersedia tanpa `CONFIRM`
- [ ] `RECOMMENDATION` dapat kosong
- [ ] `CONFIRM` hanya berlaku untuk candidate `PLAN` yang sah
- [ ] candidate non-recommended dapat di-confirm
- [ ] recommendation kosong tidak memblokir `CONFIRM` pada candidate `PLAN`
- [ ] `CONFIRM` tidak mengubah recommendation membership/rank/score/label
- [ ] tidak ada code path yang membaca `CONFIRM` untuk membentuk `RECOMMENDATION`
- [ ] tidak ada code path yang memakai recommendation sebagai syarat eligibility `CONFIRM` pada candidate `PLAN`
- [ ] tidak ada leakage ke portfolio
- [ ] tidak ada leakage ke execution
