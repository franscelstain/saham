# Audit Scoring and Verdict

## Review dimensions
Audit menilai minimal 7 dimensi:
1. domain fit
2. contract clarity
3. schema alignment
4. implementation guidance readiness
5. evidence strength
6. cross-layer consistency
7. auditability / traceability

## Status meanings
### PASS
Dipakai bila area yang dinilai:
- jelas
- konsisten
- cukup lengkap
- tidak mengandung ambiguity material
- tidak melanggar boundary

### PARTIAL
Dipakai bila area yang dinilai:
- arah dasarnya benar
- tapi masih ada gap, ambiguity, atau missing bridge
- belum aman dipakai tanpa interpretasi tambahan

### FAIL
Dipakai bila area yang dinilai:
- melanggar domain boundary
- source of truth kabur
- guidance bertentangan dengan contract
- runtime claim tidak didukung evidence nyata
- contoh dibaca sebagai owner behavior

## Suggested score bands
- 90–100 = strong
- 80–89 = usable with focused remediation
- 70–79 = partial / caution
- below 70 = unsafe as baseline claim

## Verdict labels
- PASS — Strong Layer A baseline
- PASS — Strong A+B package
- PARTIAL — Good baseline, guidance gaps remain
- PARTIAL — Runtime claim not yet proven
- FAIL — Boundary drift detected
- FAIL — Misclassified package
- FAIL — Layer C claim unsupported by actual evidence

## Important note
Nilai angka tidak boleh menggantikan verdict kategoris. Verdict tetap wajib ditulis.
