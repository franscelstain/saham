# Audit Baseline

## Audit objective
Audit market-data bertujuan memastikan bahwa paket dokumentasi, guidance, dan evidence market-data:
- tetap berada di domain market-data
- memiliki source of truth yang jelas
- tidak menimbulkan ambiguity implementasi
- tidak membuat klaim runtime yang lebih tinggi dari bukti yang ada
- cukup kuat dipakai sebagai baseline desain, guidance, atau bukti runtime sesuai layernya

## What is market-data in this repository
Market-data di repo ini adalah domain penyedia data untuk consumer downstream. Fokus utamanya adalah:
- source acquisition
- validation
- canonicalization
- persistence
- publication
- correction / replay
- auditability / reproducibility
- consumer readability guarantees

## In-scope objects
Yang boleh dinilai oleh audit ini:
- system overview docs
- domain contracts
- schema support docs
- operational runbooks
- testing guidance dan proof contracts
- illustrative examples
- archived actual evidence

## Out-of-scope objects
Yang bukan objek audit market-data:
- stock selection policy
- watchlist scoring / ranking / grouping
- entry / exit strategy
- portfolio allocation logic
- broker / order routing
- consumer UI recommendation behavior

## Source-of-truth precedence
Urutan authority default:
1. `LOCKED` contract / invariant docs
2. non-LOCKED contract docs yang jelas owner-nya
3. schema / procedure contracts yang terikat langsung pada contract
4. ops/testing documents yang menerjemahkan contract
5. examples sebagai illustrative material
6. evidence sebagai archived actual proof, bukan owner of behavior
7. audit docs sebagai alat penilaian, bukan source of truth behavior

## Baseline folders
Audit ini menganggap folder berikut sebagai baseline utama:
- `system/`
- `book/`
- `db/`
- `ops/`
- `tests/`

Companion / support folders:
- `examples/`
- `evidence/`
- `audit/`

## Core principles
- anti-drift
- anti-ambiguity
- traceability
- strong boundary discipline
- explicit claim control
- no fake Layer C claims
