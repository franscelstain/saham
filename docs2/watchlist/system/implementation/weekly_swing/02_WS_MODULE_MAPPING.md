# 02 — WS Module Mapping

## Purpose

Dokumen ini memetakan baseline Weekly Swing ke modul implementasi aplikasi watchlist.

Dokumen ini bersifat **implementation translation only**.  
Dokumen ini **tidak** boleh dipakai untuk membuat rule bisnis baru atau memperluas domain di luar baseline freeze.

## Scope Lock

Scope aktif:
- watchlist only
- weekly_swing only
- bukan portfolio
- bukan execution
- bukan market-data internals

## Governance Rule

Jika terjadi konflik:
1. `docs/watchlist/system/policy.md` menang
2. owner docs di `docs/watchlist/system/policies/weekly_swing/*.md` menang
3. dokumen ini wajib tunduk

## Recommended Module Set

### Shared
- `PolicyResolver`
- `RuntimeArtifactRepository`
- `ReasonCodeResolver`
- `ManualInputValidator`
- `TradeDateResolver`
- `ArtifactHashResolver`
- `SchemaContractValidator`

### PLAN
- `WsPlanInputProvider`
- `WsPlanEngine`
- `WsPlanAssembler`
- `WsPlanSerializer`
- `WsPlanPublisher`

### RECOMMENDATION
- `WsRecommendationEngine`
- `WsRecommendationAssembler`
- `WsRecommendationSerializer`
- `WsRecommendationPublisher`

### CONFIRM
- `WsConfirmBinder`
- `WsConfirmOverlayEngine`
- `WsConfirmAssembler`
- `WsConfirmSerializer`
- `WsConfirmPublisher`

### Consumer / Delivery
- `WsWatchlistReadService`
- `WsWatchlistApiPresenter`
- `WsWatchlistCompositeViewBuilder`

## PLAN intake anchor for module translation
`WsPlanInputProvider` tidak boleh bebas menentukan source upstream sendiri. Modul ini harus mengikat bacaannya ke intake producer-facing yang sah dari `market-data`, terutama kontrak consumer-readable / publication-aware yang sudah ditunjuk owner producer.

Anchor minimum yang wajib dibaca dan dijadikan referensi implementasi adalah:
- `docs/market_data/book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `docs/market_data/book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `docs/market_data/book/Downstream_Data_Readiness_Guarantee_LOCKED.md`

Arti praktisnya:
- adapter boleh berbeda secara teknis
- tetapi meaning intake harus tunggal
- dan tidak boleh diganti dengan raw internals, session snapshot internals, atau technical switch artifacts sebagai source of truth baru

## Artifact-to-Module Mapping

| Artifact | Producer module(s) | Consumer module(s) | May mutate source artifact? | Persistence required? | Notes |
|---|---|---|---:|---:|---|
| PLAN | `WsPlanInputProvider`, `WsPlanEngine`, `WsPlanAssembler`, `WsPlanSerializer`, `WsPlanPublisher` | recommendation, confirm, composite read | No | Yes | source artifact utama |
| RECOMMENDATION | `WsRecommendationEngine`, `WsRecommendationAssembler`, `WsRecommendationSerializer`, `WsRecommendationPublisher` | composite read, API read | No | Yes | derived only from PLAN |
| CONFIRM | `WsConfirmBinder`, `WsConfirmOverlayEngine`, `WsConfirmAssembler`, `WsConfirmSerializer`, `WsConfirmPublisher` | composite read, API read | No | Yes | must not alter recommendation; persistence of CONFIRM is required for history, consumer reads, and audit alignment with persistence guidance |

## Per-Module Contract Boundary

### PLAN Module
- reads from:
  - producer-facing, publication-aware upstream intake contract dari `market-data`
  - resolved PLAN intake adapter yang tunduk pada kontrak tersebut
- writes to:
  - PLAN artifact
- must not touch:
  - recommendation output
  - confirm output
  - execution/order state
  - holdings/portfolio state

### RECOMMENDATION Module
- reads from:
  - immutable PLAN artifact
  - capital input yang sah bila mode capital-aware aktif
- writes to:
  - RECOMMENDATION artifact
- must not touch:
  - confirm mutation
  - recommendation source di luar PLAN
  - execution/order state
  - holdings/portfolio state

### CONFIRM Module
- reads from:
  - candidate PLAN binding
  - snapshot/manual input yang sah sesuai contract
- writes to:
  - CONFIRM artifact
- must not touch:
  - recommendation membership
  - recommendation rank
  - recommendation score
  - recommendation grouping
  - execution/order state
  - holdings/portfolio state

### Composite / Consumer Module
- reads from:
  - PLAN
  - RECOMMENDATION
  - CONFIRM
- writes to:
  - read-model / view-model only
- must not touch:
  - source artifact mutation
  - execution/order state
  - holdings/portfolio state

## Trigger Matrix

| Module area | Trigger | Minimum input | Minimum output | Failure mode |
|---|---|---|---|---|
| PLAN | build trade-date run | valid policy + valid PLAN inputs | PLAN artifact | contract fail / validation fail |
| RECOMMENDATION | PLAN published | immutable PLAN artifact | RECOMMENDATION artifact | source PLAN missing / contract fail |
| CONFIRM | confirm request or confirm read generation | candidate PLAN binding + valid confirm input | CONFIRM artifact/output | invalid candidate / invalid snapshot / contract fail |
| Composite read | consumer read | PLAN and optionally RECOMMENDATION/CONFIRM | composite view | missing source artifact / inconsistent source refs |


## Suggested Build Order (Translation Layer)

### Phase 1 — Lock Core Contracts
- baca `docs/watchlist/system/policy.md`
- baca owner docs Weekly Swing yang relevan
- kunci invariants `PLAN -> RECOMMENDATION -> CONFIRM`
- kunci acceptance minimum dari `13_WS_CONTRACT_TEST_CHECKLIST.md`

### Phase 2 — Build PLAN Foundation
1. bind input yang sah untuk `PLAN`
2. build candidate pool
3. compute PLAN score
4. resolve ranking dan group semantics
5. assemble `PLAN` runtime output
6. freeze `PLAN` sebagai immutable artifact

### Phase 3 — Build RECOMMENDATION Layer
1. bind immutable `PLAN` artifact
2. form recommendation source universe from `PLAN` only
3. compute recommendation logic
4. resolve dynamic recommendation count
5. resolve capital-free / capital-aware mode sesuai policy
6. assemble `RECOMMENDATION` runtime output
7. persist/publish `RECOMMENDATION` tanpa menunggu `CONFIRM`

### Phase 4 — Build CONFIRM Overlay
1. bind valid `PLAN` candidate
2. validate confirm input / snapshot yang sah
3. evaluate confirm per item
4. assemble `CONFIRM` runtime output
5. persist/publish `CONFIRM` tanpa memutasi `RECOMMENDATION`

### Phase 5 — Build Composite Read Layer
Gabungkan `PLAN`, `RECOMMENDATION`, dan `CONFIRM` hanya di read/view layer tanpa mencampur source semantics.

## Mapping to Source of Truth

- `01_WS_OVERVIEW.md` = business orientation
- `02_WS_CANONICAL_RUNTIME_FLOW.md` = runtime precedence
- `03_WS_DATA_MODEL_MARIADB.md` = runtime data shape baseline
- `08_WS_PLAN_ALGORITHM.md` = PLAN owner behavior
- `10_WS_CONFIRM_OVERLAY.md` = CONFIRM owner behavior
- `21_WS_IMPLEMENTATION_BLUEPRINT.md` = build order
- `22–25` = RECOMMENDATION owner behavior
- `13_WS_CONTRACT_TEST_CHECKLIST.md` = acceptance minimum

Setiap module implementasi harus bisa ditelusuri balik ke owner doc yang relevan.

## Forbidden Coupling (LOCKED)

1. modul confirm membaca recommendation untuk eligibility candidate
2. modul recommendation membaca confirm untuk source scoring atau source membership
3. modul recommendation membentuk ticker dari luar candidate PLAN
4. modul watchlist langsung membaca market-data internals di luar input contract yang sudah tersedia
5. modul watchlist menulis holdings/portfolio state
6. modul watchlist menulis order/execution/broker state
7. confirm ditulis sebagai mutasi atas row recommendation sehingga recommendation terlihat berubah

## Persistence Alignment Note (LOCKED)

Status persistence untuk `CONFIRM` pada baseline ini adalah **Yes**.

Artinya:
- `CONFIRM` diperlakukan sebagai artifact yang dapat direplay
- `CONFIRM` tersedia untuk consumer reads
- `CONFIRM` tersedia untuk audit trail
- implementasi transient-only untuk `CONFIRM` tidak dianggap setara tanpa audit dan perubahan guidance yang eksplisit

Dokumen ini wajib sinkron dengan `05_WS_PERSISTENCE_GUIDANCE.md`.

## Final Rule

Module mapping yang sah adalah:
- build PLAN dahulu
- derive RECOMMENDATION hanya dari PLAN
- derive CONFIRM dari candidate PLAN binding + confirm inputs yang sah
- gabungkan hanya di layer read/view tanpa mengubah source semantics

## Canonical Layer Mapping

Dokumen ini harus dibaca bersama `docs/system_audit/SYSTEM_TRANSLATION_BASELINE.md` dan vocabulary di `docs/api_architecture/`.

| Module | Canonical layer | Allowed input | Forbidden responsibility | Expected output form |
|---|---|---|---|---|
| `WsPlanInputProvider` | producer-facing read adapter / intake repository | publication-aware consumer-facing upstream output | scoring PLAN, recommendation logic, confirm logic, response shaping | upstream intake DTO / normalized result object |
| `WsPlanEngine` | domain compute | clean PLAN input DTO | query upstream source langsung, persistence write, transport formatting | PLAN decision/result object |
| `WsPlanAssembler` | application-side assembler / artifact shaper | PLAN result object + resolved runtime metadata | rule scoring baru, upstream query | PLAN artifact payload |
| `WsRecommendationEngine` | domain compute | immutable PLAN-derived compute input | membaca CONFIRM, response shaping, persistence write | recommendation result object |
| `WsRecommendationAssembler` | application-side assembler / artifact shaper | recommendation result object + metadata | mengubah source PLAN meaning | recommendation artifact payload |
| `WsConfirmBinder` | application orchestration helper / binder | valid PLAN candidate binding + valid confirm input | rule confirm final, response transport final | confirm compute input object |
| `WsConfirmOverlayEngine` | domain compute | clean confirm compute input | membaca upstream producer langsung, memutasi recommendation | confirm result object |
| `WsConfirmAssembler` | application-side assembler / artifact shaper | confirm result object + metadata | rule confirm baru | confirm artifact payload |
| `RuntimeArtifactRepository` | persistence adapter / repository | artifact payload yang sudah selesai diputuskan | eligibility/scoring/policy decision | persisted artifact / artifact read result |
| `WsWatchlistReadService` | application service / orchestration | request intent yang sudah lolos boundary | query mentah di transport, policy baru | read/composite result object |
| `WsWatchlistApiPresenter` | transport-facing presenter | read/composite result object | policy, upstream intake, persistence write | response DTO |

## What Each Module May Read / Decide / Return

### Read
- `WsPlanInputProvider` hanya membaca source upstream producer-facing yang sah.
- `RuntimeArtifactRepository` membaca/menulis artifact watchlist milik consumer.
- `WsWatchlistReadService` boleh membaca artifact melalui adapter/repository yang sah, bukan langsung ke internals producer.

### Decide
- `WsPlanEngine`, `WsRecommendationEngine`, dan `WsConfirmOverlayEngine` adalah rumah keputusan internal weekly_swing.
- Application service, binder, assembler, dan presenter tidak boleh menjadi rumah keputusan policy baru.

### Return
- read adapter mengembalikan intake DTO / normalized result object;
- domain compute mengembalikan decision/result object;
- assembler mengembalikan artifact payload;
- presenter mengembalikan response DTO.

