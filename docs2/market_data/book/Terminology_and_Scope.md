# Terminology and Scope

## Purpose
Define the locked terminology and scope boundary for Market Data Platform (EOD) so all downstream documentation, schema, and implementation refer to the same meanings.

This document is upstream-only.
It does not define watchlist scoring, grouping, ranking, trade signals, portfolio allocation, or broker execution behavior.

## Scope of Market Data Platform (LOCKED)
Market Data Platform is responsible for producing upstream market-data artifacts that are:
- canonical
- validated
- deterministic
- auditable
- sealable
- safe for downstream consumption

Its minimum upstream outputs are:
- canonical EOD bars
- EOD indicators
- eligibility snapshot
- run metadata
- content hashes
- seal/publication metadata

Optional supplemental outputs may include:
- session snapshots aligned to the readable effective trade date
- replay results
- correction history
- operational audit artifacts

## Out of scope (LOCKED)
The following are outside Market Data Platform scope:
- watchlist scoring
- ranking/grouping logic
- buy/sell signal generation
- order execution
- broker integration
- portfolio construction
- risk sizing
- strategy-specific selection logic
- streaming / tick-by-tick engine assumptions

## Terminology

### Trade date
The logical market date associated with a dataset artifact.

For sealed EOD artifacts, this is the date the artifact belongs to in upstream market-data terms.

### Requested trade date
The date originally requested for processing by a run.

Stored conceptually as:
- `trade_date_requested`

### Effective trade date
The consumer-readable date resolved by readiness rules.

Stored conceptually as:
- `trade_date_effective`

Consumers must read using the effective trade date logic, not by guessing from the latest available raw date.

### Canonical bars
Validated EOD OHLCV rows that represent the authoritative upstream bar dataset for one trade date and one ticker.

Canonical bars must be deterministic and must not contain ambiguous duplicate rows for the same `(trade_date, ticker_id)`.

### Invalid bars
Source-derived rows rejected from canonical publication because they violate locked upstream validation rules.

Invalid bars are audit artifacts, not consumer-readable canonical dataset members.

### Indicators
Deterministic derived metrics computed from canonical bars under locked formula, calendar, window, and null-policy rules.

Indicators are upstream artifacts.
They are not downstream signals by themselves.

### Eligibility snapshot
One row per coverage-universe ticker for trade date D that explicitly states whether the ticker is upstream-readable and, if blocked, why.

Eligibility is an upstream readiness artifact, not a trading recommendation.

### Coverage universe
The set of ticker identities that belong to upstream universe membership as-of trade date D.

Coverage universe must be evaluated using temporal membership logic when historical as-of correctness is required.

### Run
A processing context that attempts to build upstream artifacts for a requested trade date.

A run may end in terminal status such as:
- `SUCCESS`
- `HELD`
- `FAILED`

### Seal
The act and metadata state that freeze a coherent consumer-readable dataset publication for one effective trade date D.

A dataset must not be considered consumer-readable until the required seal conditions are satisfied.

### Publication
The current sealed consumer-readable upstream dataset state for one effective trade date D.

If historical correction occurs, multiple historical publications may exist for the same D, but only one publication may be current.

### Superseded publication
A previously current publication for D that has been replaced by a newer corrected sealed publication.

Superseded publications must remain auditable.

### Historical correction
A controlled process that replaces the current published sealed dataset for a historical date D with a newly sealed corrected publication, while preserving prior publication history.

### Replay
A controlled re-execution of historical upstream processing used to verify determinism, data quality, correction behavior, and publication/readiness outcomes.

Replay is not a trading-strategy backtest.

### Session snapshot
An optional non-streaming upstream supplemental artifact captured at a real wall-clock time and aligned to the consumer-readable effective trade date D.

Important:
- `trade_date` for snapshot alignment refers to the effective readable dataset date
- `captured_at` stores the actual wall-clock capture timestamp

Session snapshot must not be described as inherently “same-day” unless the statement explicitly refers to `captured_at`, not `trade_date`.

### Consumer-readable dataset
The coherent sealed upstream dataset state that downstream consumers are allowed to read.

At minimum, this includes:
- canonical bars for D
- indicators for D
- eligibility snapshot for D
- associated run/hash/seal metadata proving readability

### Audit artifacts
Operational and traceability artifacts that support investigation and reproducibility, for example:
- invalid bars
- run events
- correction requests
- replay results
- failure evidence
- optional fetch-failure rows

Audit artifacts support proof and diagnosis, but they are not automatically part of the consumer-readable dataset.

## Locked interpretation rules
1. Upstream readiness must never be confused with downstream desirability.
2. Session snapshot must never be interpreted as a real-time streaming contract.
3. Publication must never mean “latest row by timestamp”; it must mean the current sealed readable state.
4. Historical correction must never silently mutate prior sealed publication history.
5. Replay must never be interpreted as strategy-performance backtesting.

## Anti-domain-leak rule (LOCKED)
Any wording that implies:
- pick selection
- ranking preference
- strategy approval
- broker action
- portfolio action

is outside this module unless explicitly documented as downstream consumer behavior outside Market Data Platform.