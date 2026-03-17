# Watchlist Layer Activation Rule

Dokumen ini mengunci cara menentukan lapisan audit watchlist agar paket dokumen tidak salah dibaca.

## Layer A — System Docs
Aktif bila paket berisi source-of-truth system docs watchlist, policy docs, contract docs, owner docs, atau DB/schema support docs yang dipakai sebagai baseline desain.

## Layer B — Implementation Guidance
Aktif bila paket berisi implementation guidance, module mapping, runtime artifact flow translation, API guidance, persistence guidance, testing guidance, delivery checklist, atau implementation audit docs.

## Layer C — Real Implementation / App Runtime
Aktif **hanya** bila ada bukti nyata yang bisa ditelusuri seperti:
- code aplikasi nyata
- service/controller/use-case nyata
- payload API/runtime nyata dari aplikasi
- schema persistence runtime nyata yang dipakai app
- test/code evidence yang langsung menilai aplikasi nyata

## Not Enough to Activate Layer C
Hal-hal berikut **tidak cukup** untuk mengaktifkan Layer C sendirian:
- markdown docs
- examples
- fixtures
- sample payload
- SQL support
- DDL/schema markdown
- seed file
- contract checklist
- audit baseline docs

## Reading Rule
- Jika hanya Layer A aktif, audit hanya ke system docs.
- Jika Layer A dan B aktif, audit system docs dulu lalu implementation guidance.
- Jika bukti Layer C belum ada, jangan melebar ke audit code/app nyata.
- Jika Layer C ada, tetap mulai dari baseline A/B lebih dulu agar audit implementasi nyata punya owner map yang jelas.


## Scoring Consequence
- Jika Layer C tidak aktif, item audit yang khusus code/app/runtime nyata harus dinilai `N/A`, bukan `PARTIAL`.
- Ketiadaan artefak Layer C pada ZIP dokumen A+B tidak boleh dianggap defect.
