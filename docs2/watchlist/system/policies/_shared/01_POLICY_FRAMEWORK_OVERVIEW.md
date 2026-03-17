# 01 — Policy Framework Overview (Shared)

## Purpose

Dokumen ini menjelaskan kerangka policy watchlist yang berlaku lintas strategy. Tujuannya adalah memberi peta ownership dan relasi antar dokumen shared, bukan menjadi owner aturan implementasi yang rinci.

## What This Document Is Not

Dokumen ini bukan owner untuk:
- shape runtime strategy,
- algorithm behavior strategy,
- validator strategy-specific,
- output labels strategy,
- atau acceptance strategy-specific.

## Scope

Area shared mencakup baseline lintas strategy berikut:

- contract model paramset yang berlaku global,
- validator baseline global,
- contract test baseline global,
- canonical runtime artifact flow watchlist global,
- schema parity rules,
- failure-code contract yang bersifat global,
- serta guidance hubungan antara dokumen shared dan dokumen strategy-specific.

## Shared Ownership Map

- `02_PARAMSET_CONTRACT_GLOBAL.md` = contract paramset global
- `03_VALIDATOR_SPEC_GLOBAL.md` = validator baseline global
- `04_CONTRACT_TESTS_GLOBAL.md` = acceptance baseline global
- `05_EXECUTION_CANONICAL_GLOBAL.md` = lifecycle global watchlist runtime artifacts
- `06_SCHEMA_PARITY_RULES.md` = source-of-truth schema / DDL / seed
- `07_CONTRACT_FAILURE_CODES_LOCKED.md` = failure-code global

## Relationship to Strategy Policies

Strategy-specific policies wajib mewarisi baseline shared yang relevan, tetapi strategy-specific policies tetap menjadi owner untuk aturan, perilaku, atau kontrak yang hanya berlaku pada strategy tersebut.

Shared layer boleh mengunci lifecycle global pada level abstraksi berikut:
- policy boleh memiliki `PLAN`,
- policy boleh memiliki layer runtime turunan dari PLAN seperti `RECOMMENDATION`,
- policy boleh memiliki overlay runtime seperti `CONFIRM`,
- tetapi semantics, label, field, ranking, dan aturan transisi detail tetap dimiliki oleh owner strategy masing-masing.

## Reading Guidance

Overview ini dipakai untuk memetakan dokumen shared sebelum pembaca masuk ke strategy-specific policies. Untuk kontrak detail, pembaca harus berpindah ke dokumen owner yang ditunjuk oleh area shared yang sesuai.

## Non-Authority Statement

Dokumen ini tidak boleh dipakai sebagai dasar tunggal untuk menetapkan aturan implementasi. Jika terdapat perbedaan antara overview ini dan dokumen owner normatif, dokumen owner normatif selalu menang.

## When To Read Shared vs Strategy-Specific

Gunakan dokumen shared bila pembaca membutuhkan:
- baseline contract lintas strategy,
- baseline validator behavior lintas strategy,
- lifecycle runtime artifact watchlist global,
- schema parity rules,
- atau failure-code baseline global.

Gunakan dokumen strategy-specific bila pembaca membutuhkan:
- algorithm behavior,
- runtime labels,
- output fields strategy,
- scoring / ranking / grouping,
- acceptance strategy,
- snapshot semantics yang hanya berlaku untuk strategy tertentu,
- dan detail hubungan `PLAN`, `RECOMMENDATION`, `CONFIRM` yang policy-specific.

## Need-to-Owner Matrix

| Kebutuhan | Owner utama |
|---|---|
| Bentuk paramset global | `02_PARAMSET_CONTRACT_GLOBAL.md` |
| Validator baseline global | `03_VALIDATOR_SPEC_GLOBAL.md` |
| Acceptance baseline global | `04_CONTRACT_TESTS_GLOBAL.md` |
| Lifecycle runtime artifact watchlist global | `05_EXECUTION_CANONICAL_GLOBAL.md` |
| Sumber kebenaran schema / DDL / seed | `06_SCHEMA_PARITY_RULES.md` |
| Failure code global | `07_CONTRACT_FAILURE_CODES_LOCKED.md` |
| Behavior strategy tertentu | folder strategy owner |

## Anti-Misread Rule

Dokumen shared tidak boleh dipakai untuk menyimpulkan behavior detail strategy jika topik itu sudah mempunyai owner strategy-specific. Shared layer hanya menyediakan baseline; strategy owner yang mengunci perilaku final pada area strategy-specific.

## Naming Guard

File shared yang masih memakai istilah `execution canonical` hanya memakai nama warisan referensi.
Secara normatif, istilah itu **harus** dibaca sebagai **canonical runtime artifact flow watchlist**, bukan broker execution, order execution, atau execution engine.
Audit dan implementasi **must not** memakai istilah tersebut untuk menarik scope ke domain execution.
