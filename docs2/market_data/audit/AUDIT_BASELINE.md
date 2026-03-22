# Audit Baseline

## Audit objective
Audit market-data memastikan bahwa paket dokumentasi, guidance, artifact, dan evidence:
- tetap berada di domain market-data
- memakai source of truth yang jelas dan tidak saling menabrak
- cukup tegas untuk dipakai sebagai baseline desain, baseline implementasi, atau baseline pembuktian sesuai layernya
- tidak membuat klaim runtime yang lebih tinggi dari bukti yang tersedia
- tetap traceable dari system overview sampai ke contract, schema, ops, tests, dan archived evidence

## What market-data means in this repository
Market-data di repo ini adalah domain upstream yang bertugas menghasilkan dataset dan artifact yang bisa dibaca consumer downstream secara aman.

Domain ini mencakup:
- source acquisition
- validation and normalization
- canonical EOD bars
- deterministic EOD indicators
- eligibility/readiness semantics
- publication and current-pointer behavior
- correction, replay, reseal, and reproducibility behavior
- audit and proof artifacts
- optional supplemental session snapshots
- registries that constrain indicator/config/reason-code behavior
- replay/backtest-grade proof material that validates data quality or correction behavior

Domain ini tidak mencakup:
- stock selection policy
- watchlist scoring, grouping, ranking, atau recommendation logic
- entry / exit strategy
- portfolio allocation or risk budgeting
- broker / order routing
- consumer UI recommendation behavior

## In-scope materials
Audit ini boleh menilai seluruh area berikut jika ada di paket:
- `system/` high-level system baseline companion
- `book/` normative domain contracts
- `db/` schema and persistence support contracts
- `ops/` operational contracts, runbooks, and operator guidance
- `tests/` proof specification, fixture contracts, and admission criteria
- `registry/` normative registries and constrained baseline values used by the platform
- `indicators/` deterministic formula and computation specifications
- `session_snapshot/` optional but normative snapshot contracts when snapshot capability is part of the package
- `backtest/` replay/backtest proof contracts and replay result support docs when used to validate market-data quality or correction behavior
- `examples/` illustrative material
- `evidence/` archived actual evidence if populated with real artifacts
- `audit/` audit framework itself

## Out-of-scope materials
Yang bukan objek audit market-data:
- downstream watchlist decision policy
- ranking / picks / confirm signal logic
- portfolio construction
- broker or execution engine behavior
- consumer-specific recommendation rendering
- business decision rules that sit outside upstream data publication and proof

## Source-of-truth precedence
Urutan authority default:
1. `LOCKED` contract / invariant docs
2. non-LOCKED domain contracts that clearly own behavior
3. schema / SQL / procedure contracts that directly implement or enforce owner contracts
4. normative registries and constrained specification docs tied to owner behavior
5. ops and tests documents that translate or verify owner contracts
6. system overview docs that summarize but do not override owner behavior
7. examples as illustrative material only
8. archived evidence as proof of what happened, not owner of future behavior
9. audit docs as evaluation framework, never as owner of runtime behavior

## Baseline folder roles used by this audit
### Normative core
- `book/`
- `db/`
- `registry/`
- `indicators/`
- `session_snapshot/` when snapshot capability is included in scope

### Normative support and proof-critical baseline
- `ops/`
- `tests/`
- `backtest/` when replay/backtest proof is used as quality or correction validation

### Companion and evaluation layers
- `system/`
- `examples/`
- `evidence/`
- `audit/`

## Boundary discipline rule
Audit harus gagal bila paket mulai memindahkan pusat gravitasi domain dari data-production ke downstream trading or recommendation behavior.

## Claim control rule
- Paket boleh kuat di Layer A tanpa kuat di Layer C.
- Paket boleh kuat di Layer A+B walau evidence runtime belum cukup.
- Paket tidak boleh mengklaim runtime-proven bila hanya punya template, example, atau admission criteria.

## Anti-drift rule
Audit wajib memeriksa keselarasan minimal antara:
- `docs/market_data/README.md`
- `system/`
- `book/`
- `db/`
- `ops/`
- `tests/`
- folder normatif tambahan seperti `registry/`, `indicators/`, `session_snapshot/`, dan `backtest/` bila aktif di paket

## Repo-shaped reading anchor
Sebelum memakai system summary atau audit summary, pembaca harus mengakui jalur baca resmi paket:
1. `book/Terminology_and_Scope.md`
2. `book/Domain_Boundary_Invariants_LOCKED.md`
3. `book/INDEX.md`
4. `docs/market_data/README.md`

Audit docs boleh menambah orientasi, tetapi tidak boleh menggantikan anchor baca tersebut.


## Active audit-report state rule
Audit report dalam paket aktif harus mengikuti model **current-state only**.

Aturannya:
- report final-state aktif harus bernama tetap: `audit/reports/AUDIT_FINAL_STATE.md`
- report final-state aktif tidak boleh memakai suffix revisi atau ronde audit
- paket aktif tidak boleh bergantung pada folder history atau rangkaian report lama untuk memahami verdict saat ini
- remediation report di paket aktif hanya boleh ada bila memang masih ada temuan PARTIAL / FAIL yang belum selesai
- setelah temuan ditutup, remediation report aktif harus dihapus agar state audit kembali tunggal dan tidak mengawang
