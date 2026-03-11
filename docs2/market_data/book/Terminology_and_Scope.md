# Terminology and Scope

## Scope
This module covers:
- acquiring EOD OHLCV from public/free APIs and/or manual input files or manual entry
- mapping source payloads into canonical schema
- validating and publishing canonical bars
- computing EOD indicators from canonical bars using trading-day windows
- declaring run status, quality gates, effective trade date, audit hashes, and seal state
- publishing daily eligibility snapshot for consumer modules
- publishing optional non-streaming session snapshots aligned to the effective trade date
- supporting deterministic replay of historical dates using versioned dependencies

This module does **not** cover:
- scoring, grouping, ranking, or screening policy
- trade recommendation policy
- order routing / execution
- portfolio or risk allocation logic
- streaming or websocket market data
- paid-provider infrastructure assumptions
- maintenance of ticker master or market calendar beyond consuming them as versioned dependencies

## Key terms
- **Trading Day**: exchange trading date from the market calendar, not a calendar day inferred from timestamps.
- **Requested Trade Date (T)**: trade date asked for by the operator/job.
- **Effective Trade Date (D)**: official trade date consumers must read. D may equal T or the latest prior sealed `SUCCESS` date.
- **Canonical EOD Bar**: one validated OHLCV record per `(trade_date, ticker_id)` in `eod_bars`.
- **Invalid Source Bar**: source row that failed canonical bar validation and is stored only for audit in `eod_invalid_bars`.
- **Indicator Window**: ordered trading-day sequence using market calendar continuity.
- **Eligibility Snapshot**: one row per ticker in the coverage universe for D with `eligible=1/0` and reason code.
- **Seal**: readiness marker proving dataset for D is hashed, frozen, and explicitly marked consumable for consumers.
- **Finalized SUCCESS Run**: run state recorded only after required artifacts for D are complete, hashes are present, and seal metadata is written.
- **Controlled Correction**: explicit rerun for an already sealed date, producing a new `run_id`, new hashes, and a new seal record while preserving auditability.
- **Session Snapshot**: optional same-day snapshot captured manually or by normal API refresh at a fixed slot; it is non-streaming and not part of EOD finalization.

## Design principles (LOCKED)
1) Deterministic: same source rows + same config registry + same calendar/ticker mapping => same outputs.
2) Auditable: every output is traceable to `run_id`, versioned config, counts, hashes, and reason codes.
3) Fail-safe: consumers never read partial, unsealed, or unqualified datasets.
4) Easy to code: each contract must be implementable as simple input rules, validation rules, stage rules, and output tables without hidden interpretation.
5) Upstream/consumer separation: upstream publishes data contracts; consumer modules own policy logic.