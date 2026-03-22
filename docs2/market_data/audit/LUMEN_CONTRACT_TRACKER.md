# LUMEN_CONTRACT_TRACKER

| Contract Item | Owner Doc | Architecture Guide | Kewajiban Utama | File Code Pemenuh | Test Pemenuh | Evidence/Output Support | Status | Catatan |
|---|---|---|---|---|---|---|---|---|
| Root ownership rules extracted | `docs/README.md` | `docs/api_architecture/**` | Root source-of-truth, ownership, dan build order dipahami dan diterapkan | - | - | - | MISSING | Belum dikerjakan |
| System assembly baseline extracted | `docs/system_audit/SYSTEM_ASSEMBLY_BASELINE.md` | `docs/api_architecture/**` | Urutan assembly lintas area dipahami dan dipatuhi | - | - | - | MISSING | Belum dikerjakan |
| System readiness baseline extracted | `docs/system_audit/SYSTEM_READINESS_AUDIT_BASELINE.md` | `docs/api_architecture/**` | Readiness/build-start baseline dipahami dan diterapkan | - | - | - | MISSING | Belum dikerjakan |
| Cross-domain input baseline respected | `docs/system_audit/SYSTEM_CROSS_DOMAIN_INPUT_BASELINE.md` | `docs/api_architecture/**` | Tidak ada shortcut liar terhadap upstream producer meaning | - | - | - | MISSING | Belum dikerjakan |
| Translation baseline extracted | `docs/system_audit/SYSTEM_TRANSLATION_BASELINE.md` | `docs/api_architecture/**` | Mapping siapa baca apa, siapa orkestrasi, siapa compute, siapa persistence, siapa transport jelas | - | - | - | MISSING | Belum dikerjakan |
| Market-data domain boundary extracted | `docs/market_data/book/Domain_Boundary_Invariants_LOCKED.md` | `docs/api_architecture/**` | Boundary domain market-data diekstrak dan dijaga | - | - | - | MISSING | Belum dikerjakan |
| EOD bars contract mapped | `docs/market_data/book/**`, `docs/market_data/db/**` | `docs/api_architecture/**` | Entitas, storage, dan flow EOD bars dipetakan ke code | - | - | - | MISSING | Belum dikerjakan |
| EOD indicators contract mapped | `docs/market_data/book/**`, `docs/market_data/indicators/**` | `docs/api_architecture/**` | Formula/baseline/flow indicators dipetakan ke code | - | - | - | MISSING | Belum dikerjakan |
| Eligibility/session snapshot contract mapped | `docs/market_data/session_snapshot/**` | `docs/api_architecture/**` | Snapshot scope dan boundary dipetakan ke code | - | - | - | MISSING | Belum dikerjakan |
| Publication contract mapped | `docs/market_data/book/**`, `docs/market_data/db/**`, `docs/market_data/ops/**` | `docs/api_architecture/**` | Publication flow dipetakan ke code | - | - | - | MISSING | Belum dikerjakan |
| Current publication pointer contract mapped | `docs/market_data/book/**`, `docs/market_data/db/**` | `docs/api_architecture/**` | Pointer/current publication dipetakan ke code | - | - | - | MISSING | Belum dikerjakan |
| Correction/reseal contract mapped | `docs/market_data/book/**`, `docs/market_data/ops/**`, `docs/market_data/db/**` | `docs/api_architecture/**` | Correction dan reseal flow dipetakan ke code | - | - | - | MISSING | Belum dikerjakan |
| Registry and reason codes mapped | `docs/market_data/registry/**` | `docs/api_architecture/**` | Registry, reason codes, normalization dipetakan ke code | - | - | - | MISSING | Belum dikerjakan |
| Ops flow mapped | `docs/market_data/ops/**` | `docs/api_architecture/**` | Command/job/scheduler/failure handling dipetakan ke code | - | - | - | MISSING | Belum dikerjakan |
| Tests requirements mapped | `docs/market_data/tests/**` | `docs/api_architecture/**` | Test matrix, fixtures, acceptance criteria dipetakan ke code/tests | - | - | - | MISSING | Belum dikerjakan |
| Replay/backtest verification mapped | `docs/market_data/backtest/**` | `docs/api_architecture/**` | Replay/data-quality verification dipetakan ke code | - | - | - | MISSING | Belum dikerjakan |
| Evidence/output shape support mapped | `docs/market_data/evidence/**`, `docs/market_data/ops/**`, `docs/market_data/tests/**` | `docs/api_architecture/**` | Output/evidence shape yang diwajibkan didukung implementasi | - | - | - | MISSING | Belum dikerjakan |
| Domain drift guard enforced | `docs/market_data/book/**`, `docs/market_data/audit/**` | `docs/README.md`, `docs/system_audit/**`, `docs/api_architecture/**` | Implementasi tetap market-data only dan tidak drift | - | - | - | MISSING | Belum dikerjakan |
| Architecture compliance enforced | `docs/api_architecture/**` | `docs/api_architecture/**` | Struktur code dan layering patuh pedoman arsitektur | - | - | - | MISSING | Belum dikerjakan |
| Final done gate passed | All relevant owner docs | `docs/api_architecture/**` | Semua kontrak relevan tertutup, readiness/build-order dipatuhi, tidak ada conflict penting terbuka | - | - | - | MISSING | Belum dikerjakan |