# Backtest Metrics and Acceptance Criteria (LOCKED)

This file defines acceptance for upstream historical replay and data-quality verification, not downstream alpha quality.

## Minimum acceptance criteria
- deterministic hash match for bars / indicators / eligibility on unchanged fixtures
- zero unexpected effective-date mismatches
- zero unexpected seal-state mismatches
- reason-code counts match expected fixtures
- replay failure cases degrade to `HELD` / `FAILED` exactly as specified