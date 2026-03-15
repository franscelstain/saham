# 21 — Weekly Swing Implementation Blueprint

## Purpose

Dokumen ini memberi urutan bangun praktis untuk implementasi Weekly Swing tanpa menggeser ownership dari dokumen normatif lain.

## Rule of Use

Dokumen ini adalah blueprint implementasi. Ia tidak menggantikan owner kontrak. Jika ada konflik antara blueprint ini dan dokumen owner normatif, dokumen owner normatif selalu menang.

## Suggested Module Map

- paramset loader / repository
- paramset validator
- PLAN pipeline service
- deterministic selection module
- PLAN runtime assembler
- snapshot repository / reader
- CONFIRM overlay service
- acceptance test pack
- backtest / promote operational module

Nama modul bebas, tetapi tanggung jawabnya tidak boleh menggeser owner dokumen normatif.

## Build Order (LOCKED)

### Phase 1 — Lock contracts first
Baca dan kunci dulu:
1. `02_WS_EXECUTION_CANONICAL_PLAN_CONFIRM.md`
2. `03_WS_DATA_MODEL_MARIADB.md`
3. `04_WS_PARAMSET_JSON_CONTRACT.md`
4. `06_WS_PARAMSET_VALIDATOR_SPEC.md`
5. `07_WS_REASON_CODES_AND_HASH.md`

**Deliverable minimum**
- tim paham shape runtime
- tim paham paramset valid
- tim paham hash / reason-code contract
- belum ada coding behavior dulu

**Review gate before moving on**
- owner kontrak dan owner implementasi dipetakan jelas
- tim sepakat examples / fixtures / SQL bukan source of truth behavior
- tidak ada field runtime baru yang diam-diam dianggap resmi

### Phase 2 — Build PLAN foundation
Implementasi:
1. paramset loader
2. paramset validator
3. candidate binding
4. eligibility/readiness gate
5. guard evaluation
6. score computation shell

**Owner utama**
- `04`, `05`, `06`, `08`

**Acceptance minimum**
- paramset valid / invalid cases lolos
- run bisa membedakan `FAILED` vs `NO_TRADE`
- duplicate candidate / broken identity tidak disenyapkan

**Recommended code outputs**
- validator unit tests
- PLAN preflight tests
- candidate binding tests

### Phase 3 — Build deterministic selection
Implementasi:
1. qualified pools
2. quantile cutoff
3. target dinamis
4. final ordering / tie-breaker
5. final group mapping

**Owner utama**
- `08`, `09`

**Acceptance minimum**
- ties deterministik
- `TOP_PICKS`, `SECONDARY`, `WATCH_ONLY`, `AVOID` sesuai precedence
- `NO_TRADE` branch tervalidasi

**Recommended code outputs**
- selection ordering tests
- tie-breaker tests
- per-group mapping tests

### Phase 4 — Assemble PLAN runtime output
Implementasi:
1. `meta`, `items`, `summary`
2. reason assignment
3. `plan_hash`
4. persistence PLAN

**Owner utama**
- `02`, `03`, `07`, `08`

**Acceptance minimum**
- runtime shape sesuai
- `plan_hash` stabil
- examples hanya dipakai sebagai ilustrasi, bukan owner

**Recommended code outputs**
- DTO / serializer tests
- persistence parity tests
- golden output tests untuk PLAN canonical

### Phase 5 — Build CONFIRM overlay
Implementasi:
1. snapshot binding
2. freshness resolution
3. field minimum validation
4. drift evaluation
5. label resolution
6. persistence CONFIRM

**Owner utama**
- `02`, `03`, `10`, `11`

**Acceptance minimum**
- `DELAY` branch untuk missing / stale
- strictness unknown top-level field
- orderbook field diabaikan penuh
- label precedence `DELAY > CAUTION > CONFIRMED > NEUTRAL` tervalidasi

**Recommended code outputs**
- snapshot binding tests
- freshness tests
- label precedence tests
- strictness tests

### Phase 6 — Protect immutability
Implementasi:
1. read-only PLAN during CONFIRM
2. write-only CONFIRM persistence
3. audit pair hash

**Owner utama**
- `02`, `10`, `13`

**Acceptance minimum**
- `plan_hash_before == plan_hash_after`
- PLAN ordering tidak berubah setelah CONFIRM
- CONFIRM tidak mengubah `group_semantic` PLAN

**Recommended code outputs**
- immutability regression tests
- audit comparison tests

### Phase 7 — Backtest and promote
Implementasi:
1. backtest schema / eval pipeline
2. coverage matrix checks
3. OOS gate
4. promote procedure

**Owner utama**
- `12`, `14`, `15`, `16`, `17`, `18`, `20`

**Acceptance minimum**
- artifact backtest lengkap
- equivalence dan coverage gates lolos
- promote hanya jalan pada paramset yang sah

## Minimal File-to-Component Mapping

| Component concern | Primary owner docs |
|---|---|
| paramset schema / validator | `04`, `05`, `06` |
| PLAN service / pipeline | `08`, `09` |
| PLAN DTO / persistence | `03` |
| CONFIRM service | `10`, `11` |
| acceptance tests | `13` |
| promote / operational procedure | `20` |

## Merge Readiness Checklist (LOCKED)

Sebelum merge implementasi utama Weekly Swing, minimal harus lolos:

- owner doc untuk perubahan sudah jelas dan benar
- tidak ada behavior baru yang hanya hidup di code / SQL / fixture
- PLAN membedakan `FAILED` vs `NO_TRADE` dengan benar
- deterministic selection lulus tie-breaker dan ordering tests
- CONFIRM lulus precedence tests dan strictness tests
- immutability PLAN setelah CONFIRM terbukti
- runtime output shape cocok dengan owner contract
- backtest / promote path tidak merusak contract hidup

## Anti-Patterns

Jangan lakukan ini:
- mulai coding dari examples / fixtures / SQL
- menaruh rule baru hanya di test fixture
- membuat formula / label baru tanpa owner normatif
- menganggap README / overview cukup untuk coding behavior
- mencampur owner PLAN dan CONFIRM dalam satu shortcut service tanpa jejak owner yang jelas

## Final Rule

Blueprint ini dipakai untuk urutan kerja dan checkpoint implementasi. Owner kontrak tetap berada di dokumen normatif topiknya masing-masing.
