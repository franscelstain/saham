# Session Snapshot Scope Selection and Dependencies (LOCKED)

## Purpose
Define how optional session-snapshot capture scope is selected and what dependencies it may use, without leaking downstream trading logic into Market Data Platform.

## Scope principle (LOCKED)
Session snapshot scope must remain upstream and dataset-oriented.

Default scope is based on upstream-readable dataset membership for the effective trade date D, not on downstream picks, rankings, or strategy groups.

## Default scope
Unless a narrower upstream-approved contract is explicitly documented, snapshot scope defaults to:
- tickers with eligibility rows for effective trade date D

Recommended default:
- full eligibility set for D

This keeps snapshot capture aligned with the same effective-date reference dataset that downstream consumers are allowed to read.

## Important semantic note (LOCKED)
Snapshot capture may occur at a wall-clock time later than the effective trade date D.
This is allowed.

Therefore:
- `trade_date` represents the aligned effective dataset date
- `captured_at` represents the actual wall-clock capture timestamp

The scope contract must not describe this as “same-day snapshot” unless it explicitly refers to `captured_at`, not `trade_date`.

## Allowed narrowing inputs
The implementation may narrow scope only using upstream-safe criteria, for example:
- all eligibility rows for D
- only `eligible = 1` rows for D, if explicitly locked by upstream contract
- static universe subsets defined by upstream master-data rules

## Forbidden narrowing inputs
The implementation must not derive scope from:
- downstream watchlist picks
- ranking outputs
- strategy scores
- portfolio positions
- execution intents
- broker-specific filters

## Dependency rules
Snapshot scope selection may depend on:
- eligibility snapshot for D
- coverage universe definition for D
- upstream master-data attributes documented as part of market-data domain

Snapshot scope selection must not depend on downstream consumer decisions.

## Failure behavior
If snapshot scope cannot be resolved safely:
- snapshot capture may be skipped or partially captured according to snapshot contract
- this must not alter sealed EOD readiness
- snapshot failure remains non-blocking for EOD publication

## Anti-domain-leak rule (LOCKED)
Session snapshot support exists only as optional supplemental upstream reference data.
It must not become a hidden signal-selection or watchlist-selection mechanism.