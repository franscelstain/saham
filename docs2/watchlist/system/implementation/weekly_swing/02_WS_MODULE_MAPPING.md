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

## Artifact-to-Module Mapping

| Artifact | Producer module(s) | Consumer module(s) | May mutate source artifact? | Persistence required? | Notes |
|---|---|---|---:|---:|---|
| PLAN | `WsPlanInputProvider`, `WsPlanEngine`, `WsPlanAssembler`, `WsPlanSerializer`, `WsPlanPublisher` | recommendation, confirm, composite read | No | Yes | source artifact utama |
| RECOMMENDATION | `WsRecommendationEngine`, `WsRecommendationAssembler`, `WsRecommendationSerializer`, `WsRecommendationPublisher` | composite read, API read | No | Yes | derived only from PLAN |
| CONFIRM | `WsConfirmBinder`, `WsConfirmOverlayEngine`, `WsConfirmAssembler`, `WsConfirmSerializer`, `WsConfirmPublisher` | composite read, API read | No | Yes/Optional by implementation choice | must not alter recommendation |

## Per-Module Contract Boundary

### PLAN Module
- reads from:
  - resolved input contract yang sah untuk PLAN
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

## Final Rule

Module mapping yang sah adalah:
- build PLAN dahulu
- derive RECOMMENDATION hanya dari PLAN
- derive CONFIRM dari candidate PLAN binding + confirm inputs yang sah
- gabungkan hanya di layer read/view tanpa mengubah source semantics
