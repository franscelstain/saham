# Domain Boundary Invariants (LOCKED)

## Purpose
Define the non-negotiable domain boundary for Market Data Platform (EOD) so upstream market-data responsibilities never drift into downstream strategy, watchlist, or execution logic.

This document is a boundary invariant layer.
It exists to stop semantic leakage, not merely to describe it.

## Core boundary rule (LOCKED)
Market Data Platform produces upstream market-data artifacts only.

It may produce:
- canonical bars
- indicators
- eligibility snapshot
- run metadata
- hashes
- seal/publication metadata
- correction trail
- replay evidence
- optional supplemental session snapshots

It must not produce downstream decision artifacts.

## What this domain is allowed to decide
The domain may decide:
- whether data is canonical
- whether data is valid
- whether a row is eligible/readable as upstream artifact
- whether a requested date is readable
- which publication is current
- whether a correction replaces a prior publication
- whether replay matched expectation

These are upstream publication and data-quality decisions only.

## What this domain must never decide
The domain must never decide:
- buy vs sell
- long vs short
- ranking or priority list
- pick / candidate selection
- signal classification
- entry / exit timing
- position size
- portfolio action
- expected return
- alpha or edge
- strategy fit
- broker action

## Eligibility boundary rule (LOCKED)
Eligibility in this domain means:
- upstream dataset readability for one `(trade_date, ticker_id)`

Eligibility must never mean:
- trade desirability
- rank quality
- signal strength
- portfolio suitability

## Indicator boundary rule (LOCKED)
Indicators in this domain are:
- deterministic upstream derived metrics

Indicators here are not:
- strategy signals
- ranking scores
- recommendation engines

## Session snapshot boundary rule (LOCKED)
Session snapshot is:
- optional
- non-streaming
- supplemental
- aligned to effective trade date

Session snapshot must never become:
- hidden screening engine
- replacement for watchlist logic
- proxy ranking layer

## Consumer boundary rule (LOCKED)
Downstream consumers may use upstream artifacts in their own logic, but that downstream logic remains outside this module.

This module must not silently embed downstream consumer policy inside:
- eligibility
- publication rules
- snapshot scope
- indicator naming
- anomaly classifications

## Forbidden active semantics
The following terms are forbidden as active semantics inside Market Data Platform contracts unless explicitly stated as out-of-scope warnings:

- buy
- sell
- entry
- exit
- ranking
- pick
- alpha
- conviction
- target price
- stop loss
- take profit
- portfolio action
- risk sizing

## Allowed mention rule
The forbidden terms above may appear only in contexts such as:
- “out of scope”
- “must not be interpreted as”
- “not produced by this module”

They must not appear as positive feature definitions of Market Data Platform.

## Artifact naming rule (LOCKED)
Artifact names in this domain must remain upstream-neutral.

Examples of good names:
- `eod_bars`
- `eod_indicators`
- `eod_eligibility`
- `eod_publications`
- `session_snapshots`

Examples of bad names:
- `trade_candidates`
- `entry_signals`
- `ranked_picks`
- `buy_watchlist`

## Boundary invariants
1. Upstream readability is not downstream desirability.
2. Canonicalization is not strategy filtering.
3. Indicator derivation is not signal generation.
4. Eligibility is not ranking.
5. Session snapshot is not real-time signal infrastructure.
6. Publication currentness is not trading recency recommendation.
7. Replay is not trading backtest.

## Required cross-contract alignment
This document must remain aligned with:
- `Terminology_and_Scope.md`
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `../session_snapshot/Session_Snapshot_Contract_LOCKED.md`

## Anti-domain-leak rule (LOCKED)
If a document in this module can be read as producing trading advice, watchlist ranking, or execution guidance rather than upstream market-data readiness/publication semantics, that document violates the domain boundary.