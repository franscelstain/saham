# Watchlist Audit Checklist Final

Gunakan checklist ini untuk audit keras ZIP folder [`../`](../).

Status yang dipakai:
- `PASS`
- `PARTIAL`
- `FAIL`
- `N/A`

## A. Scope Guard

- [ ] watchlist tetap hanya saran
- [ ] active policy tetap `weekly_swing`
- [ ] tidak melebar ke portfolio
- [ ] tidak melebar ke execution
- [ ] tidak melebar ke market-data internals
- [ ] desain tetap realistis untuk input provider gratis / manual

## B. System Owner Files

- [ ] `01` overview sinkron dengan scope aktif
- [ ] `02` canonical flow sinkron
- [ ] `03` runtime/data model sinkron
- [ ] `08` plan algorithm tidak mengambil alih recommendation
- [ ] `09` group semantics tidak disamakan dengan recommendation
- [ ] `10` confirm overlay sinkron
- [ ] `13` contract test checklist sinkron
- [ ] `21` implementation bridge note sinkron dan tidak mengambil alih owner rule
- [ ] `22–25` recommendation docs lengkap dan sinkron

## C. Core Rules

- [ ] recommendation berasal dari PLAN only
- [ ] recommendation dapat tersedia tanpa confirm
- [ ] recommendation dapat kosong walau `TOP_PICKS/SECONDARY` ada
- [ ] confirm eligibility berasal dari candidate PLAN
- [ ] non-recommended candidate tetap bisa confirm
- [ ] confirm tidak mengubah recommendation
- [ ] recommended + confirmed hanya berarti confirm menguatkan

## D. Support Docs

- [ ] `_refs` sinkron dengan owner docs
- [ ] `examples` sinkron dengan owner docs
- [ ] `fixtures` sinkron dengan owner docs
- [ ] `db` support tidak mengambil alih owner rule
- [ ] `07` reason codes/hash sinkron dengan recommendation dan confirm

## E. Layer Separation Guard

- [ ] checklist ini dipakai hanya untuk audit Layer A (system docs)
- [ ] penilaian build/module/API/persistence/test translation dipindahkan ke `implementation/WATCHLIST_IMPLEMENTATION_CHECKLIST_FINAL.md`
- [ ] examples/fixtures/sql/schema docs tidak disalahbaca sebagai Layer C evidence

## F. Verdict Gate

Dokumen tidak boleh dinilai matang jika salah satu kondisi berikut masih terjadi:
- ada FAIL pada owner docs inti;
- ada conflict boundary antara PLAN / RECOMMENDATION / CONFIRM;
- scope bocor ke portfolio, execution, atau market-data internals.
