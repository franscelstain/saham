# 01 — Weekly Swing Watchlist (EOD) — Overview

## Purpose

Dokumen ini adalah overview strategy Weekly Swing. Dokumen ini merangkum ruang lingkup strategy, jalur baca, dan batas ownership, tetapi tidak menggantikan dokumen normatif yang menjadi owner aturan detail.

## What This Document Is Not

Dokumen ini bukan:
- owner algoritma PLAN,
- owner behavior CONFIRM,
- owner shape runtime output,
- owner paramset contract,
- atau owner acceptance tests.

Jika pembaca mencari aturan implementasi yang mengikat, pembaca wajib berpindah ke dokumen owner yang disebutkan pada overview ini.

## Scope

Weekly Swing mencakup:

- kontrak eksekusi PLAN dan CONFIRM,
- model data strategy,
- kontrak paramset dan validator,
- PLAN algorithm,
- dynamic selection yang deterministik,
- CONFIRM overlay,
- persistence artifacts yang mendukung strategy,
- contract-test anchors,
- serta dokumen pendukung untuk contoh, fixture, dan referensi.

## Core Reading Order

Untuk implementasi Weekly Swing, jalur baca inti adalah:

1. `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`
2. `03_WS_DATA_MODEL_MARIADB.md`
3. `04_WS_PARAMSET_JSON_CONTRACT.md`
4. `05_WS_PARAMETER_REGISTRY_COMPLETE.md`
5. `06_WS_PARAMSET_VALIDATOR_SPEC.md`
6. `07_WS_REASON_CODES_AND_HASH.md`
7. `08_WS_PLAN_ALGORITHM.md`
8. `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
9. `10_WS_CONFIRM_OVERLAY.md`
10. `11_WS_INTRADAY_SNAPSHOT_TABLES.md`
11. `13_WS_CONTRACT_TEST_CHECKLIST.md`
12. `21_WS_IMPLEMENTATION_BLUEPRINT.md`

Dokumen bernomor pada folder ini adalah source of truth utama di level strategy.

## Ownership Map

- `02` = lifecycle PLAN / CONFIRM dan immutability
- `03` = shape runtime / persistence consequence
- `04` = shape paramset Weekly Swing
- `05` = registry parameter
- `06` = validator paramset
- `07` = reason codes + hash contract
- `08` = urutan algoritma PLAN dan precedence branch
- `09` = determinism selection
- `10` = behavior CONFIRM overlay
- `11` = persistence semantics snapshot
- `13` = acceptance minima
- `21` = build order implementasi

## Supporting Folders

Folder pendukung pada strategy ini memiliki peran sebagai berikut:

- `_refs/` = referensi bantu baca, glossary, matrix, worked example
- `examples/` = contoh output yang tunduk pada kontrak normatif
- `fixtures/` = golden test assets
- `db/` = artefak persistence dan implementasi SQL/schema

Folder pendukung tersebut tidak menjadi owner aturan wajib strategy.

## Relationship to Shared Policy

Weekly Swing menggunakan baseline shared yang relevan dari `docs/watchlist/policies/_shared/`. Namun, semua aturan yang hanya berlaku untuk Weekly Swing tetap dimiliki oleh file normatif bernomor pada folder ini.

## Non-Authority Statement

Jika overview ini tampak berbeda dari dokumen normatif Weekly Swing yang lebih rinci, maka dokumen normatif yang menjadi owner topik selalu menang.

## Ownership Map (Quick)

- `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md` = lifecycle PLAN / CONFIRM
- `03_WS_DATA_MODEL_MARIADB.md` = runtime output shape dan persistence fields
- `04_WS_PARAMSET_JSON_CONTRACT.md` = shape paramset strategy
- `06_WS_PARAMSET_VALIDATOR_SPEC.md` = validator rule strategy-level
- `08_WS_PLAN_ALGORITHM.md` = PLAN algorithm owner
- `09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md` = deterministic ranking / grouping
- `10_WS_CONFIRM_OVERLAY.md` = CONFIRM overlay owner
- `11_WS_INTRADAY_SNAPSHOT_TABLES.md` = semantics snapshot watchlist-owned
- `13_WS_CONTRACT_TEST_CHECKLIST.md` = acceptance anchor
- `21_WS_IMPLEMENTATION_BLUEPRINT.md` = build order dan review gate

## Reading Paths by Role

### Engineer implementasi
Baca urut: `02` → `03` → `04` → `06` → `08` → `09` → `10` → `11` → `13` → `21`.

### Reviewer kontrak / QA
Baca urut: `03` → `06` → `07` → `13` → `14` → `15` → `17` → `20`.

### Pembaca orientasi cepat
Baca dokumen ini, lalu pindah ke `21_WS_IMPLEMENTATION_BLUEPRINT.md`, lalu kembali ke owner file sesuai area yang sedang dikerjakan.

## Files That Must Not Be Treated as Owners

Dokumen berikut membantu pembacaan, tetapi tidak boleh dipakai sebagai sumber kontrak final:
- `_refs/*`
- `examples/*`
- `fixtures/*`
- file SQL pada folder `db/`

Jika pembaca menemukan rule yang hanya tampak hidup pada file-file di atas, rule itu belum aman dijadikan pegangan implementasi sampai dipastikan hidup pada owner normatifnya.

