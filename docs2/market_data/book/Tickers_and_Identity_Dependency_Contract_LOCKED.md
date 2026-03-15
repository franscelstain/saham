# Tickers and Identity Dependency Contract (LOCKED)

## Purpose
Lock what Market Data Platform requires from the global `tickers` master so:
- coverage denominator is deterministic
- provider symbol mapping is stable
- downstream consumers receive stable `ticker_id`
- historical replay can reconstruct universe membership as-of D without guessing from current ticker state

## Ownership note
Market Data Platform depends on a shared ticker-identity foundation.
This contract defines required dependency semantics only.
It does not make `market_data` the owner of the global ticker master, and it does not allow the shared foundation to reclaim ownership of canonical bars, indicators, eligibility, or publication behavior.

## Required fields
- `ticker_id` (immutable PK)
- `ticker_code` (display / exchange code)
- historical membership capability for as-of evaluation on D, implemented through either:
  - `listed_since` + `delisted_since`, or
  - another immutable equivalent temporal-membership mechanism documented outside this module

## Conditionally allowed fallback
A plain `is_active` flag may be used only when the application explicitly accepts a non-historical current-state universe.
If historical replay or historical as-of coverage is required, `is_active` alone is insufficient.

## Optional refinement fields
- `ticker_type` when reliable and versioned
- exchange/board metadata when stable and versioned

## Locked rules
1. `ticker_id` is the canonical identity used in all market-data tables.
2. Provider mapping must resolve provider symbols to `ticker_id` via the ticker master.
3. Default coverage universe must be evaluated as-of D using temporal membership, not current wall-clock state.
4. When `trade_date < listed_since`, the ticker is out of default coverage universe for D.
5. When `delisted_since` exists and `trade_date > delisted_since`, the ticker is out of default coverage universe for D.
6. `ticker_code` is not a stable historical join key by itself.

## Consumer impact
Downstream consumers must treat `ticker_id` as canonical identity. `ticker_code` is presentation metadata, not durable time-safe identity.