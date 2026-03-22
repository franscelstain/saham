# Audit Method

## Purpose
Metode ini dibuat agar audit market-data konsisten, tidak generik, dan tetap mengikuti bentuk repo yang nyata.

## Standard audit flow
1. identify package boundary and claimed purpose
2. read repo anchors and locked boundary docs first
3. classify package layer
4. map normative core, normative support, companion, and illustrative areas
5. verify domain boundary and out-of-scope contamination
6. verify cross-folder authority and traceability
7. assess implementation guidance quality
8. assess evidence strength and Layer C claim viability
9. record findings and remediation
10. issue final verdict

## Mandatory first-read anchors
Audit tidak boleh dimulai dari summary generik. Audit harus mulai dari anchor resmi paket dan harus tetap sinkron dengan `audit/AUDIT_BASELINE.md`, `system/SYSTEM_READ_ORDER.md`, dan `docs/market_data/README.md`:
1. `book/Terminology_and_Scope.md`
2. `book/Domain_Boundary_Invariants_LOCKED.md`
3. `book/INDEX.md`
4. `docs/market_data/README.md`

Setelah empat anchor ini dipahami, baru lanjut ke `system/` dan `audit/` untuk mempercepat navigasi. `README.md` di sini dipakai sebagai anchor ke-4, bukan sebagai titik mulai yang menggeser tiga anchor book di atasnya.

## Repo-shaped reading order
### Core orientation
1. `book/Terminology_and_Scope.md`
2. `book/Domain_Boundary_Invariants_LOCKED.md`
3. `book/INDEX.md`
4. `docs/market_data/README.md`
5. `system/README.md`
6. `system/SYSTEM_BOUNDARY.md`
7. `audit/AUDIT_BASELINE.md`

### Contract and schema path
8. `book/`
9. `db/`
10. `registry/`
11. `indicators/`
12. `session_snapshot/` if snapshot capability is in scope

### Operational and proof path
13. `ops/`
14. `tests/`
15. `backtest/` when replay/backtest material is used as proof support

### Companion and proof archive path
16. `examples/` for illustration only
17. `evidence/` for actual archived proof if populated
18. `audit/` templates/checklists/reports for evaluation output

## Intake questions
- Paket ini dominan Layer A, B, atau C?
- Folder mana yang benar-benar owner behavior di paket ini?
- Apakah `registry/`, `indicators/`, `session_snapshot/`, atau `backtest/` aktif sebagai bagian baseline?
- Apakah evidence yang ada illustrative, admission-only, atau executed-and-traceable?
- Apakah ada drift ke watchlist, strategy, execution, atau portfolio domain?

## Review dimensions
- domain fit
- authority clarity
- cross-folder consistency
- schema alignment
- implementation readiness
- proof readiness
- evidence strength
- traceability

## Output requirement
Audit output minimal harus memuat:
- package classification
- dominant layer
- scope declaration
- PASS / PARTIAL / FAIL table
- major findings
- remediation items
- final verdict

## Failure rule
Audit harus memberi FAIL bila:
- paket salah domain
- source-of-truth tidak dapat diidentifikasi
- summary mengalahkan contract
- illustrative material diperlakukan sebagai owner behavior
- Layer C diklaim tanpa bukti executed-and-traceable


## Audit report persistence rule
Audit output untuk paket aktif harus disimpan sebagai **state saat ini**, bukan sebagai urutan revisi.

Aturannya:
- gunakan satu file kanonik: `audit/reports/AUDIT_FINAL_STATE.md`
- jangan membuat final-state report baru dengan suffix `R1`, `R2`, `R3`, dst.
- jangan menyimpan history audit lama di paket aktif bila history itu tidak dipakai untuk menilai state saat ini
- remediation report hanya boleh ada selama isu masih terbuka; bila isu selesai, remediation report harus dihapus dari paket aktif
