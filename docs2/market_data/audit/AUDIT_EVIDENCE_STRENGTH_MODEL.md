# Audit Evidence Strength Model

## Levels
### Level 0 — None
Tidak ada bukti atau referensi bukti.

### Level 1 — Specified
Baru ada contract, expected format, admission criteria, atau documentation claim.

### Level 2 — Guided
Sudah ada runbook, testing guidance, audit query cookbook, atau operational translation.

### Level 3 — Illustrative
Ada example payload atau example evidence bundle, tapi masih contoh.

### Level 4 — Executed but weakly traceable
Ada hasil run/test/correction nyata, tetapi traceability atau identity proof belum kuat.

### Level 5 — Executed and traceable
Ada actual runtime/test/correction evidence yang bisa ditelusuri ke contract, run identity, output, dan decision context.

## Minimum requirement for Layer C claim
Minimal harus ada evidence level 4, dan sebaiknya level 5 untuk klaim kuat.

## Disqualifiers for Layer C
Yang tidak boleh dipakai sebagai bukti Layer C:
- template evidence
- static example output
- file naming convention saja
- empty evidence folder
- admission criteria tanpa executed result

## Redacted evidence rule
Evidence yang disamarkan tetap boleh dihitung bila:
- struktur aslinya tetap jelas
- run identity masih bisa dihubungkan
- semantics penting tidak hilang
