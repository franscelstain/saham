# Canonical Row History and Versioning Policy (LOCKED)

## Purpose
Define the official row-history strategy for preserving historical canonical artifact state across corrections and publication changes.

This policy applies to:
- canonical bars
- indicators
- eligibility

## Core principle (LOCKED)
A corrected publication for trade date D must never make prior consumer-visible row state disappear silently from auditability.

## Official production-grade strategy (LOCKED)
For production-grade Market Data Platform, the official default and required strategy is:

### Strategy A — Immutable publication-bound row snapshots

Under Strategy A:
- each publication for D has its own immutable row snapshot set
- row snapshots are preserved in history tables:
  - `eod_bars_history`
  - `eod_indicators_history`
  - `eod_eligibility_history`
- current readable state may still be served from current artifact tables
- historical row-level audit must be reconstructable exactly per publication

## Strategy B status
Strategy B (publication trail + hash trail + correction evidence only) is not the default production-grade strategy.

It may exist only as:
- legacy note
- simplified non-production deployment note
- explicitly weaker fallback model

It must not be presented as equal in strength to Strategy A.

## Strategy A rules (LOCKED)
If Strategy A is implemented, all of the following must hold:
1. each sealed publication must have one immutable snapshot set
2. history rows must be keyed by `publication_id` plus row identity
3. history rows must never be updated in place
4. corrected publication produces a new snapshot set
5. prior snapshot set remains queryable after supersession
6. history snapshot rows must link to `eod_publications`
7. history snapshot writes must happen only for sealed publication states

## Required history-table semantics
History tables must support:
- exact row-state recovery for one publication
- clear publication linkage
- append-only / immutable behavior
- no ambiguity between current-state tables and historical snapshot tables

## Current-state vs history-state distinction
Current-state tables:
- `eod_bars`
- `eod_indicators`
- `eod_eligibility`

serve the current readable state.

History tables:
- `eod_bars_history`
- `eod_indicators_history`
- `eod_eligibility_history`

serve immutable publication-bound audit state.

These roles must never be confused.

## Correction rule
On correction for D:
- prior publication snapshot remains preserved
- corrected publication creates a new snapshot set
- corrected snapshot becomes associated with the new current publication
- prior snapshot remains audit-only but fully queryable

## Minimum audit questions this policy must support
For any corrected date D, the system must answer:
1. what was the prior current publication?
2. what exact rows belonged to that prior publication?
3. what exact rows belong to the new publication?
4. which publication is current now?
5. which history-table snapshot corresponds to each publication?

## Required schema alignment
This policy must be reflected in:
- `../db/Database_Schema_MariaDB.sql`
- `../db/Database_Schema_Contracts_MariaDB.md`
- `../ops/History_Table_Immutability_Guards_LOCKED.sql`
- publication/correction contracts

## Required evidence alignment
Executed evidence examples should demonstrate:
- prior publication snapshot
- corrected publication snapshot
- publication manifest
- correction diff artifact

## Anti-ambiguity rule (LOCKED)
If the platform claims production-grade auditability but cannot point to immutable publication-bound history rows, then row-history integrity is overstated.