# Audit Method

## Standard audit flow
1. intake package
2. identify folder structure
3. classify package layer
4. determine normative vs companion materials
5. verify domain boundary
6. review cross-layer consistency
7. assess evidence strength
8. record findings
9. produce remediation plan
10. issue final verdict

## Intake questions
- Paket ini dominan layer apa?
- Apa source-of-truth utamanya?
- Apakah ada guidance yang menerjemahkan contract?
- Apakah ada bukti runtime nyata?
- Apakah ada domain drift?

## Reading order
1. `system/README.md`
2. `system/SYSTEM_OVERVIEW.md`
3. `system/SYSTEM_BOUNDARY.md`
4. `audit/AUDIT_BASELINE.md`
5. `audit/AUDIT_LAYER_CLASSIFICATION_RULES.md`
6. `book/` core contracts
7. `db/`
8. `ops/`
9. `tests/`
10. `examples/` and `evidence/` as needed

## Review dimensions
- domain fit
- authority clarity
- schema alignment
- implementation readiness
- evidence strength
- traceability
- cross-layer consistency

## Output requirement
Audit output minimal harus memuat:
- package classification
- scope declaration
- table PASS / PARTIAL / FAIL
- findings
- remediation
- final verdict
