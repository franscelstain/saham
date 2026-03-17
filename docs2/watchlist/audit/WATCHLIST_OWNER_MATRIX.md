# Watchlist Owner Matrix

## Purpose

Dokumen ini memetakan hirarki owner, kelas authority, dan batas deferensi untuk domain `watchlist` yang aktif pada baseline freeze ini.

Scope aktif dokumen ini:
- domain: `watchlist` only
- policy aktif: `weekly_swing` only
- audit layer yang dibahas: `A` dan `B`
- bukan portfolio
- bukan execution
- bukan market-data internals

Dokumen ini **tidak** membuat rule bisnis baru. Dokumen ini hanya menjelaskan file mana yang berhak menjadi owner dan file mana yang wajib tunduk.

## Governance Precedence (LOCKED)

Urutan authority yang wajib dipakai saat terjadi konflik:

1. `docs/watchlist/system/policy.md`  
   = governance root / boundary root tertinggi.

2. `docs/watchlist/system/README.md`  
   = root orientation / navigasi system docs, tunduk pada governance root.

3. `docs/watchlist/system/policies/weekly_swing/*.md`  
   = normative policy owner untuk Weekly Swing.

4. `docs/watchlist/system/implementation/weekly_swing/*.md`  
   = implementation translation only, tidak boleh membuat atau mengubah rule bisnis normatif.

5. `docs/watchlist/audit/*.md`  
   = audit guardrail only, tidak boleh menjadi source of truth bisnis.

6. `docs/watchlist/system/policies/weekly_swing/_refs/*`  
   `docs/watchlist/system/policies/weekly_swing/examples/*`  
   `docs/watchlist/system/policies/weekly_swing/fixtures/*`  
   `docs/watchlist/system/policies/weekly_swing/db/*`  
   = supporting artifacts only, wajib tunduk pada owner docs di atas.

## Authority Matrix

| Area | Authority class | May define business rule? | Must defer to | Used for audit layer |
|---|---|---:|---|---|
| `docs/watchlist/system/policy.md` | Governance root | Yes | None | A |
| `docs/watchlist/system/README.md` | Root orientation | No | `system/policy.md` | A |
| `docs/watchlist/system/policies/weekly_swing/*.md` | Normative policy owner | Yes | `system/policy.md` | A |
| `docs/watchlist/system/implementation/weekly_swing/*.md` | Implementation translation | No | governance root + normative policy owner docs | B |
| `docs/watchlist/audit/*.md` | Audit guardrail | No | governance root + normative policy owner docs | A/B |
| `_refs/*` | Support reference | No | owner docs above | A/B support |
| `examples/*` | Support example | No | owner docs above | A/B support |
| `fixtures/*` | Support acceptance evidence | No | owner docs above | A/B support |
| `db/*` | Support schema/persistence reference | No | owner docs above | A/B support |

## Current Active Owner Set

### Governance Root
- `docs/watchlist/system/policy.md`

### Root Orientation
- `docs/watchlist/system/README.md`

### Normative Policy Owner — Weekly Swing
- `docs/watchlist/system/policies/weekly_swing/README.md`
- `docs/watchlist/system/policies/weekly_swing/01_WS_OVERVIEW.md`
- `docs/watchlist/system/policies/weekly_swing/02_WS_CANONICAL_RUNTIME_FLOW.md`
- `docs/watchlist/system/policies/weekly_swing/03_WS_DATA_MODEL_MARIADB.md`
- `docs/watchlist/system/policies/weekly_swing/04_WS_PARAMSET_JSON_CONTRACT.md`
- `docs/watchlist/system/policies/weekly_swing/05_WS_PARAMETER_REGISTRY_COMPLETE.md`
- `docs/watchlist/system/policies/weekly_swing/06_WS_PARAMSET_VALIDATOR_SPEC.md`
- `docs/watchlist/system/policies/weekly_swing/07_WS_REASON_CODES_AND_HASH.md`
- `docs/watchlist/system/policies/weekly_swing/08_WS_PLAN_ALGORITHM.md`
- `docs/watchlist/system/policies/weekly_swing/09_WS_DYNAMIC_SELECTION_DETERMINISTIC.md`
- `docs/watchlist/system/policies/weekly_swing/10_WS_CONFIRM_OVERLAY.md`
- `docs/watchlist/system/policies/weekly_swing/11_WS_INTRADAY_SNAPSHOT_TABLES.md`
- `docs/watchlist/system/policies/weekly_swing/13_WS_CONTRACT_TEST_CHECKLIST.md`
- `docs/watchlist/system/policies/weekly_swing/21_WS_IMPLEMENTATION_BLUEPRINT.md`
- `docs/watchlist/system/policies/weekly_swing/22_WS_RECOMMENDATION_OVERVIEW.md`
- `docs/watchlist/system/policies/weekly_swing/23_WS_RECOMMENDATION_INPUT_OUTPUT_CONTRACT.md`
- `docs/watchlist/system/policies/weekly_swing/24_WS_RECOMMENDATION_ALGORITHM.md`
- `docs/watchlist/system/policies/weekly_swing/25_WS_RECOMMENDATION_REASON_CODES_AND_TESTS.md`

### Implementation Translation Owner Set
- `docs/watchlist/system/implementation/README.md`
- `docs/watchlist/system/implementation/weekly_swing/01_WS_IMPLEMENTATION_SCOPE_AND_BOUNDARY.md`
- `docs/watchlist/system/implementation/weekly_swing/02_WS_MODULE_MAPPING.md`
- `docs/watchlist/system/implementation/weekly_swing/03_WS_RUNTIME_ARTIFACT_FLOW.md`
- `docs/watchlist/system/implementation/weekly_swing/04_WS_API_GUIDANCE.md`
- `docs/watchlist/system/implementation/weekly_swing/05_WS_PERSISTENCE_GUIDANCE.md`
- `docs/watchlist/system/implementation/weekly_swing/05A_WS_CANONICAL_FIELD_MATRIX.md`
- `docs/watchlist/system/implementation/weekly_swing/06_WS_TEST_IMPLEMENTATION_GUIDANCE.md`
- `docs/watchlist/system/implementation/weekly_swing/07_WS_DELIVERY_CHECKLIST.md`

### Audit Guardrail Set
- `docs/watchlist/audit/README.md`
- `docs/watchlist/audit/WATCHLIST_AUDIT_FOUNDATION.md`
- `docs/watchlist/audit/WATCHLIST_SCOPE_LOCK.md`
- `docs/watchlist/audit/WATCHLIST_OWNER_MATRIX.md`
- `docs/watchlist/audit/WATCHLIST_AUDIT_CHECKLIST_FINAL.md`
- `docs/watchlist/audit/WATCHLIST_AUDIT_SHORT.md`
- `docs/watchlist/audit/WATCHLIST_AUDIT_PROMPT_STANDARD.md`
- `docs/watchlist/audit/WATCHLIST_CHANGE_IMPACT_MATRIX.md`

## Support Areas to Cross-Check

Support area wajib sinkron dengan owner docs, tetapi tidak boleh mengubah makna rule:
- `docs/watchlist/system/policies/weekly_swing/_refs/*`
- `docs/watchlist/system/policies/weekly_swing/examples/*`
- `docs/watchlist/system/policies/weekly_swing/fixtures/*`
- `docs/watchlist/system/policies/weekly_swing/db/*`

## Hard Rules

1. Audit docs tidak boleh menjadi owner rule bisnis.
2. Implementation docs tidak boleh memperluas domain di luar baseline freeze.
3. Support artifacts tidak boleh override owner docs.
4. Examples tidak boleh memperkenalkan rule baru.
5. Fixtures tidak boleh mengubah contract.
6. DB/support docs tidak boleh menggeser authority rule dari owner policy docs.
7. Jika terjadi konflik, ikuti `docs/watchlist/system/policy.md`, lalu file owner normatif Weekly Swing yang relevan.

## Audit Layer Activation Rule

### Layer A
Aktif bila ZIP berisi system docs normatif dan boundary docs.

### Layer B
Aktif bila ZIP berisi implementation guidance / translation docs.

### Layer C
**Hanya aktif** bila ZIP memuat salah satu berikut:
- code aplikasi nyata,
- service/controller/repository runtime nyata,
- API payload/runtime evidence nyata,
- persistence runtime nyata,
- bukti implementasi app nyata.

Jika ZIP hanya berisi dokumen/support artifacts, audit **tidak boleh** melebar ke lapisan C.
