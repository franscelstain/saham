# Patch Audit Summary

## Scope of this patch
This patch keeps the documentation set strictly inside the Market Data Platform (EOD) upstream domain and removes duplicate/example documents that were specific to watchlist naming.

## Updated / corrected
- removed watchlist-specific duplicate consumer docs so the source of truth stays generic and upstream-only
- cleaned `Audit_Hash_and_Reproducibility_Contract_LOCKED.md` so it contains only hash/reproducibility semantics and no accidental appended run-status content
- locked exact hash field order per artifact to eliminate serialization ambiguity
- locked explicit `trade_date_effective = NULL` behavior when no prior sealed `SUCCESS` date exists
- locked deterministic duplicate-provider-row resolution before canonical publish
- clarified `roc20` as ratio-scaled and clarified per-date `adj_close -> close` fallback semantics
- expanded contract tests to cover the corrected locked semantics above

## Removed duplicate files
- `docs/market_data/book/Watchlist_Consumer_Read_Model_Contract_LOCKED.md`
- `docs/market_data/book/Watchlist_Data_Readiness_Guarantee_LOCKED.md`

## Result
The remaining documentation set is narrower, less ambiguous, and aligned to a single upstream source of truth for canonical market data, readiness, seals, and deterministic consumption.