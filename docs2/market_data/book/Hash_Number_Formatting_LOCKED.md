# Hash Number Formatting Rules (LOCKED)

These rules apply only to hash serialization, not necessarily to storage precision.

These rules are part of the locked reproducibility contract and must stay aligned with schema precision and the indicator oracle/test vectors.

## Fixed formats
- prices (`open`, `high`, `low`, `close`, `adj_close`, `hh20`): 4 decimal places
- `dv20_idr`: 2 decimal places
- `atr14_pct`, `vol_ratio`, `roc20`: 10 decimal places
- `coverage_ratio`: 4 decimal places
- integer counts and `volume`: base-10 integer with no separators
- booleans / flags: `0` or `1`
- NULL: empty string
- dates: `YYYY-MM-DD`
- timestamps: `YYYY-MM-DD HH:MM:SS` in platform timezone used by the run

## Examples
- `123.4` => `123.4000`
- `7` in `dv20_idr` => `7.00`
- NULL => ``

## Locked rule
Locale must never affect formatting. No thousands separator, no scientific notation, no trimmed trailing zeros.

## Cross-contract alignment
This file must remain aligned with:
- `Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- `../indicators/EOD_Indicators_Formula_Spec.md`
- `../tests/Indicator_Test_Vectors_LOCKED.md`
- `../tests/Indicator_Expected_Output_Oracle_LOCKED.md`
- `../db/Database_Schema_MariaDB.sql`
