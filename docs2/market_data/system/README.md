# Market Data System README

## Purpose
Folder `system/` adalah pintu masuk ringkasan sistem market-data. Folder ini membantu pembaca memahami bentuk repo, boundary, ownership, dan alur baca tanpa menggantikan owner contracts.

## Important limitation
`system/` bukan titik awal authority. Titik awal authority tetap berada pada:
1. `docs/market_data/README.md`
2. `book/Terminology_and_Scope.md`
3. `book/Domain_Boundary_Invariants_LOCKED.md`
4. `book/INDEX.md`

Bila ada konflik antara `system/` dan owner contracts, owner contracts selalu menang.

## What belongs here
- overview sistem tingkat atas
- boundary sistem
- context and dependencies
- data product map
- runtime flow tingkat atas
- ownership map lintas folder
- artifact map
- read order yang mengikuti bentuk repo nyata

## What does not belong here
- full domain contracts
- detailed schema ownership yang seharusnya hidup di `db/`
- detailed formula specification yang seharusnya hidup di `indicators/`
- full registries yang seharusnya hidup di `registry/`
- detailed runbook steps dari `ops/`
- fixture/test vectors dari `tests/`
- consumer strategy behavior

## Repo-aware scope
Saat membaca folder ini, pembaca harus tahu bahwa baseline market-data repo ini tidak berhenti pada `book/`, `db/`, `ops/`, dan `tests/` saja. Paket ini juga memiliki area normatif atau proof-critical lain seperti:
- `registry/`
- `indicators/`
- `session_snapshot/`
- `backtest/`

## Read order inside system folder
1. `SYSTEM_OVERVIEW.md`
2. `SYSTEM_BOUNDARY.md`
3. `SYSTEM_CONTEXT_AND_DEPENDENCIES.md`
4. `SYSTEM_DATA_PRODUCT_MAP.md`
5. `SYSTEM_RUNTIME_FLOW.md`
6. `SYSTEM_PUBLICATION_AND_CORRECTION_OVERVIEW.md`
7. `SYSTEM_OWNERSHIP_MAP.md`
8. `SYSTEM_ARTIFACT_MAP.md`
9. `SYSTEM_READ_ORDER.md`
