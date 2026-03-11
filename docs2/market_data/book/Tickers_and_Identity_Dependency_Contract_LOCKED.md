# Tickers and Identity Dependency Contract (LOCKED)

## Purpose
Lock what Market Data Platform requires from the global `tickers` master so:
- coverage denominator is deterministic
- provider symbol mapping is stable
- downstream consumers receive stable `ticker_id`

## Required fields
- `ticker_id` (immutable PK)
- `ticker_code` (display / exchange code)
- `is_active` (membership signal for default coverage universe)

## Recommended fields
- `ticker_type` when reliable and versioned
- `listed_since`
- `delisted_since`

## Locked rules
1) `ticker_id` is the canonical identity used in all market-data tables.
2) provider mapping must resolve provider symbols to `ticker_id` via the ticker master.
3) coverage universe defaults to tickers where `is_active=1` as-of D, unless a documented upstream refinement is enabled.
4) when `delisted_since` exists and `trade_date > delisted_since`, ingestion may stop and eligibility for that date must be 0.

## Consumer impact
Downstream consumers must treat `ticker_id` as canonical identity. `ticker_code` is not a stable join key across time.
