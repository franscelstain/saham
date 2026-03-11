# Tickers and Identity Dependency Contract (LOCKED)

## Purpose
Lock what Market Data Platform requires from the global `tickers` master so:
- coverage_ratio denominator is deterministic
- provider symbol mapping is stable
- watchlist consumes consistent ticker_id

## Required fields (semantic, names can differ)
- ticker_id (INT, PK) — immutable identity
- ticker_code (STRING) — exchange code (e.g., BBCA), may change over time but must map to ticker_id
- is_active (BOOLEAN/flag) — indicates active listing eligibility for coverage universe

Recommended fields (if available)
- ticker_type (equity/etf/warrant/etc) — only if reliable; used to refine coverage universe
- listed_since (DATE)
- delisted_since (DATE nullable)

## LOCKED rules
1) ticker_id is the only identity used in all market_data tables.
2) ticker_code changes must not break mapping: provider adapter must resolve code->ticker_id using tickers master.
3) coverage universe (for coverage_ratio) = tickers where is_active=1, unless a reliable ticker_type filter is explicitly enabled.
4) When delisted_since is set and trade_date > delisted_since:
   - ingestion may stop
   - eligibility must be 0 for that trade_date

## Consumer impact
Watchlist must treat ticker_id as canonical; ticker_code is display only.