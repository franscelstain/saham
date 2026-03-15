# 10 — Weekly Swing CONFIRM Overlay

## Purpose

Dokumen ini adalah owner normatif untuk behavior CONFIRM overlay Weekly Swing. Dokumen ini mengunci bagaimana hasil PLAN yang sudah immutable dibaca bersama snapshot intraday / confirm input untuk menghasilkan label dan metadata CONFIRM tanpa mengubah PLAN.

## Scope

Dokumen ini mengunci:

- urutan evaluasi CONFIRM overlay,
- binding antara item PLAN dan snapshot CONFIRM,
- precedence label CONFIRM,
- strictness terhadap field contract vs field non-contract,
- branch behavior `DELAY`, `CAUTION`, `NEUTRAL`, `CONFIRMED`,
- serta ekspektasi minimal output per item CONFIRM.

Dokumen ini tidak mendefinisikan ulang kontrak upstream `market_data`, dan tidak mengubah algoritma PLAN.

## Non-Goals

Dokumen ini tidak boleh dipakai untuk:

- mengubah `group_semantic` PLAN,
- mengubah `plan_hash`,
- mengubah hasil selection PLAN,
- memperkenalkan input order-book sebagai kontrak CONFIRM,
- atau memperluas shape output di luar owner contract Weekly Swing.

## Ownership Map

- dokumen ini: urutan evaluasi overlay, precedence label CONFIRM, binding item-to-snapshot, field strictness, dan output item CONFIRM minimum
- `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`: lifecycle PLAN/CONFIRM dan immutability
- `03_WS_DATA_MODEL_MARIADB.md`: shape runtime output
- `11_WS_INTRADAY_SNAPSHOT_TABLES.md`: semantics persistence snapshot intraday
- `13_WS_CONTRACT_TEST_CHECKLIST.md`: acceptance minima

## Immutable PLAN Rule (LOCKED)

- CONFIRM selalu membaca PLAN yang sudah selesai dan immutable.
- CONFIRM tidak boleh mengubah `group_semantic`, ordering PLAN, atau `plan_hash`.
- Hasil CONFIRM adalah overlay terpisah di atas PLAN, bukan PLAN baru.
- Jika implementasi CONFIRM menyebabkan mutation pada PLAN, implementasi tersebut melanggar kontrak Weekly Swing.

## Canonical CONFIRM Overlay Pipeline (LOCKED)

Urutan berikut wajib dan tidak boleh dibalik:

1. bind immutable PLAN result
2. resolve snapshot availability
3. evaluate snapshot freshness and minimum required fields
4. evaluate overlay conditions per item
5. resolve final CONFIRM label using precedence
6. assemble CONFIRM runtime output

## Step 1 — Bind Immutable PLAN Result

**Purpose**  
Menetapkan basis item yang boleh dievaluasi oleh CONFIRM.

**Rule (LOCKED)**  
- Hanya item yang berasal dari PLAN immutable yang boleh menjadi basis CONFIRM.
- Binding menggunakan identity item PLAN yang authoritative.
- Jika PLAN basis tidak valid / tidak ditemukan, CONFIRM tidak boleh diam-diam membuat item sendiri.

**Outputs**  
PLAN basis yang immutable.

## Step 2 — Resolve Snapshot Availability

**Purpose**  
Menentukan apakah snapshot CONFIRM tersedia untuk run tersebut.

**Rule (LOCKED)**  
- Snapshot availability dievaluasi di level run dan item.
- Jika snapshot header atau binding item tidak tersedia, label item terkait wajib mengikuti precedence `DELAY`.
- Tidak ada fallback ke source non-contract di luar snapshot / input CONFIRM yang sah.

**Outputs**  
Snapshot binding state per item.

## Step 3 — Evaluate Snapshot Freshness and Minimum Required Fields

**Purpose**  
Memastikan snapshot layak dipakai untuk overlay.

**Minimum Required Fields**
- `ticker_code`
- `last_price`
- `captured_at` atau kombinasi field yang secara normatif menghasilkan freshness resolution

**Rule (LOCKED)**  
- Freshness memakai semantics yang konsisten dengan `11_WS_INTRADAY_SNAPSHOT_TABLES.md`.
- Snapshot stale wajib menghasilkan `DELAY`, bukan `NEUTRAL`.
- Missing field wajib mengikuti branch `DELAY` atau strictness failure yang diuji acceptance.
- Order-book ladder bukan field wajib dan tidak boleh menjadi syarat kelulusan CONFIRM.

**Outputs**  
Freshness and minimum-field outcome per item.

## Step 4 — Evaluate Overlay Conditions per Item

**Purpose**  
Menilai kondisi overlay pada item PLAN berdasarkan snapshot valid.

**Rule (LOCKED)**  
- Overlay hanya memakai field CONFIRM yang kontraktual.
- Kondisi positif dapat menghasilkan `CONFIRMED`.
- Kondisi warning dapat menghasilkan `CAUTION`.
- Ketiadaan sinyal overlay yang bersifat decisive menghasilkan `NEUTRAL`, selama item tidak jatuh ke `DELAY`.
- Label overlay tidak boleh memperkenalkan group PLAN baru.

**Outputs**  
Candidate label set per item.

## Step 5 — Resolve Final CONFIRM Label Using Precedence

**Purpose**  
Menentukan label CONFIRM akhir secara non-ambiguous.

**Rule (LOCKED)**  
Precedence label wajib:

1. `DELAY`
2. `CAUTION`
3. `CONFIRMED`
4. `NEUTRAL`

Penjelasan:
- `DELAY` menutupi semua label di bawahnya karena masalah availability / freshness / minimum required fields.
- `CAUTION` menutupi `CONFIRMED` jika warning condition strategy menyatakan item tidak aman dinaikkan sebagai confirm-positive.
- `CONFIRMED` hanya boleh muncul bila item lolos delay/warning dan memenuhi kondisi confirm-positive.
- `NEUTRAL` adalah default akhir jika item valid tetapi tidak memenuhi positive/warning branch.

**Outputs**  
Satu label akhir per item.

## Exact Precedence Matrix (LOCKED)

| Snapshot available | Snapshot fresh | Required fields complete | Warning condition | Positive confirm condition | Final label |
|---|---|---|---|---|---|
| no | - | - | - | - | `DELAY` |
| yes | no | - | - | - | `DELAY` |
| yes | yes | no | - | - | `DELAY` |
| yes | yes | yes | yes | yes/no | `CAUTION` |
| yes | yes | yes | no | yes | `CONFIRMED` |
| yes | yes | yes | no | no | `NEUTRAL` |

## Input-to-Output Resolution Notes (LOCKED)

- Missing snapshot header => seluruh item yang bergantung pada snapshot itu jatuh ke `DELAY`.
- Snapshot header ada tetapi item ticker tidak ketemu => item itu `DELAY`.
- Snapshot ada tetapi stale => `DELAY`, walaupun price movement terlihat bagus.
- Snapshot valid dan warning aktif => `CAUTION`, walaupun kondisi positive juga aktif.
- Snapshot valid, tanpa warning, positive aktif => `CONFIRMED`.
- Snapshot valid, tanpa warning, tanpa positive => `NEUTRAL`.

## Binding and Ignoring Rules (LOCKED)

### Contractual fields that may affect decision
- `ticker_code`
- `last_price`
- freshness inputs yang normatif
- field kontraktual CONFIRM lain yang memang sudah di-owner-kan oleh Weekly Swing

### Non-contract fields that must be ignored
- `bid1_price`
- `ask1_price`
- `spread`
- `orderbook_json`
- ladder / depth / broker queue fields lain

Aturan:
- Field non-contract boleh hadir pada payload, fixture, atau source teknis.
- Kehadiran field non-contract tidak boleh mengubah label akhir.
- Implementasi tidak boleh diam-diam memakai field non-contract sebagai shortcut `CONFIRMED` atau `CAUTION`.

## Minimal Item Output Expectation (LOCKED)

Setiap item CONFIRM minimal harus dapat ditelusuri ke:
- item PLAN basis yang immutable,
- satu final label CONFIRM,
- reason / note overlay yang konsisten dengan branch label,
- metadata snapshot minimum yang memang kontraktual pada runtime output.

Tidak wajib semua item menyimpan seluruh raw snapshot field pada output, tetapi keputusan overlay harus tetap audit-able.

## Step 6 — CONFIRM Runtime Output Assembly

**Purpose**  
Menyusun hasil CONFIRM terpisah dari PLAN.

**Rule (LOCKED)**  
- Shape output wajib mengikuti `03_WS_DATA_MODEL_MARIADB.md`.
- Hasil CONFIRM hanya menambah output CONFIRM terpisah.
- Persistence semantics snapshot tetap mengikuti `11_WS_INTRADAY_SNAPSHOT_TABLES.md`.

**Outputs**  
Runtime CONFIRM canonical: `meta`, `items`, `summary`.

## Strictness Boundary (LOCKED)

- Unknown top-level field pada payload/runtime CONFIRM dianggap schema drift.
- Field input non-contract seperti `bid1_price`, `ask1_price`, `spread`, atau `orderbook_json` boleh hadir pada input/fixture, tetapi harus diabaikan penuh dan tidak boleh mengubah keputusan overlay.
- Implementasi tidak boleh memakai field non-contract sebagai shortcut label resolution.

## Relationship to Snapshot Tables

Jika CONFIRM menggunakan persistence snapshot milik watchlist, shape tabel dan semantics field wajib dibaca bersama `11_WS_INTRADAY_SNAPSHOT_TABLES.md`. Dokumen ini menetapkan behavior overlay; dokumen 11 menetapkan persistence semantics.

## Relationship to Acceptance

Acceptance item yang menguji strictness, immutability, runtime shape, dan payload behavior wajib mengikuti `13_WS_CONTRACT_TEST_CHECKLIST.md`.

## Final Rule

Tidak ada behavior CONFIRM yang boleh dianggap resmi hanya karena muncul di example, fixture, atau implementasi teknis apabila behavior tersebut tidak dapat ditelusuri ke dokumen ini, `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`, `03_WS_DATA_MODEL_MARIADB.md`, atau `11_WS_INTRADAY_SNAPSHOT_TABLES.md`.
