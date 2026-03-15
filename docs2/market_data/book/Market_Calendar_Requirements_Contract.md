# Market Calendar Requirements Contract (Global Dependency)

## Ownership note
Market Data Platform depends on a shared market-calendar foundation.
This document defines what this domain requires from that dependency.
It does not make `market_data` the owner of the shared calendar itself, and it does not move bars/indicators ownership back to the shared foundation.

## Required fields
- `trade_date`
- `is_trading_day`
- `prev_trading_day`
- `next_trading_day`

## Recommended fields
- `session_open_time`
- `session_close_time`
- `is_half_day`

## Locked usage rules
- Trading-day windows must follow `prev_trading_day` / `next_trading_day`, never subtract calendar days.
- `D[-N]` is resolved by walking the market calendar N times through `prev_trading_day`.
- If the calendar dependency is missing or inconsistent for a required date, requested-date processing must not finalize `SUCCESS`.

## Latest trade-date resolution
- if current date is a trading day and the platform is past cutoff, latest trade date may resolve to today
- otherwise latest trade date resolves to the prior trading day
