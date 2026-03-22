# System Read Order

## Principle
Read order untuk paket ini harus mengikuti source-of-truth yang sudah ada, bukan menggantinya dengan summary generik.

## Mandatory outer read order
1. `book/Terminology_and_Scope.md`
2. `book/Domain_Boundary_Invariants_LOCKED.md`
3. `book/INDEX.md`
4. `docs/market_data/README.md`

Setelah empat anchor ini dipahami, pembaca boleh memakai `system/` dan `audit/` untuk navigasi lebih cepat. Urutan ini harus tetap sinkron dengan `audit/AUDIT_BASELINE.md` dan `docs/market_data/README.md`.

## Recommended repo-shaped read order
### Orientation
1. `system/README.md`
2. `system/SYSTEM_OVERVIEW.md`
3. `system/SYSTEM_BOUNDARY.md`
4. `audit/README.md`
5. `audit/AUDIT_BASELINE.md`

### Normative core
6. `book/`
7. `db/`
8. `registry/`
9. `indicators/`
10. `session_snapshot/` when snapshot capability is in scope

### Operational and proof-critical baseline
11. `ops/`
12. `tests/`
13. `backtest/` when replay/backtest proof is relevant to the audit or build

### Companion and archive
14. `examples/` for illustrative shape only
15. `evidence/` for actual archived proof if populated
16. `audit/` checklists/templates/reports for evaluation output


## Downstream consumer read path
Jika pembaca datang dari domain consumer seperti `watchlist`, jalur baca minimum yang sah adalah:
1. `book/Terminology_and_Scope.md`
2. `book/Domain_Boundary_Invariants_LOCKED.md`
3. `docs/market_data/README.md`
4. `book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`
5. `book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
6. `book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
7. `book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`

Baru setelah itu pembaca boleh lanjut ke `docs/watchlist/README.md` dan `docs/api_architecture/README.md`.

Jalur ini ada untuk mencegah downstream consumer membaca internals market-data secara bebas.

## Read path by audit layer
### For Layer A
Use the mandatory outer read order first, then focus on:
- `book/`
- `db/`
- `registry/`
- `indicators/`
- `session_snapshot/` when active

### For Layer B
After Layer A anchors, focus on:
- `ops/`
- `tests/`
- `backtest/` where it serves as implementation/proof support

### For Layer C
Only after contracts and guidance are understood, inspect:
- real code or runtime artifacts if present outside docs
- `evidence/` for executed-and-traceable proof
- executed proof material referenced from `ops/`, `tests/`, or `backtest/`

## Prohibited shortcut
Jangan memulai audit hanya dari `system/` atau hanya dari `audit/`. Itu akan membuat pembaca terlalu jauh dari source-of-truth yang locked.


## Prohibited downstream shortcut
Downstream consumer tidak boleh memulai intake dari raw bars, raw indicators, session snapshot internals, atau technical switch procedures lalu menganggapnya setara dengan consumer-facing read model tanpa owner contract yang eksplisit.
