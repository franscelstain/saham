# Hash Number Formatting Rules (LOCKED)

These rules apply only to hash serialization, not necessarily to storage precision.

## Fixed formats
- prices (`open/high/low/close/adj_close/hh20`): 4 decimal places
- `dv20_idr`: 2 decimal places
- `atr14_pct`, `vol_ratio`, `roc20`: 4 decimal places
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
