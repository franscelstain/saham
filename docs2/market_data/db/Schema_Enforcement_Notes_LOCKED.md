# Schema Enforcement Notes (LOCKED)

## Purpose
Explain which invariants are enforced directly by schema constraints and which are enforced by application or procedure discipline.

This file exists so the boundary between:
- DB-enforced integrity
- procedure-enforced integrity
- application-enforced integrity

is explicit and auditable.

## Direct DB-enforced invariants
These are enforced directly by primary keys, unique keys, or simple column constraints.

### Current examples
- one current artifact row per `(trade_date, ticker_id)` in:
  - `eod_bars`
  - `eod_indicators`
  - `eod_eligibility`
- one replay reason-code row per `(replay_id, trade_date, reason_code)`
- one publication version row per `(trade_date, publication_version)`

## Procedure/application-enforced invariants
These are logically mandatory, but not fully expressible by direct MariaDB uniqueness alone.

### Current examples
- exactly one current publication per trade date
- no ambiguous publication switch
- no dual-current state after correction
- no readable state for unsealed publication
- no mixed-publication consumer read
- no fake publication switch for unchanged rerun

## Why this distinction exists
MariaDB cannot express every invariant as a direct partial unique index or declarative cross-table rule.
That does not weaken the contract; it only changes the enforcement mechanism.

## Required enforcement transparency (LOCKED)
For every critical invariant, the implementation must know:
1. whether DB enforces it directly
2. whether procedure/transaction flow enforces it
3. where the enforcement logic lives
4. what evidence proves the enforcement worked

## Current mapping

| Invariant | Enforced by DB directly? | Enforced by procedure/app? | Notes |
|---|---|---|---|
| one canonical bar row per `(trade_date, ticker_id)` | Yes | Optional additional checks | PK/unique semantics |
| one indicator row per `(trade_date, ticker_id)` | Yes | Optional additional checks | PK/unique semantics |
| one eligibility row per `(trade_date, ticker_id)` | Yes | Optional additional checks | PK/unique semantics |
| one publication version per `(trade_date, publication_version)` | Yes | Optional additional checks | unique key |
| exactly one current publication per trade date | No | Yes | enforced by publication switch flow |
| current publication must be sealed | Partially | Yes | procedure must reject unsealed switch |
| superseded publication remains preserved | No | Yes | correction/publication flow |
| current consumer read must not mix publications | No | Yes | read model / query discipline |

## Required evidence sources
Enforcement evidence should be visible through:
- `eod_publications`
- `eod_runs`
- `eod_run_events`
- correction evidence packs
- replay mismatch evidence where relevant

## Cross-contract alignment
This file must remain aligned with:
- `Database_Schema_Contracts_MariaDB.md`
- `Indices_and_Constraints_Contract_LOCKED.md`
- `Publication_Switch_Integrity_Contract_LOCKED.md`
- `Publication_Switch_Procedure_LOCKED.sql`

## Anti-ambiguity rule (LOCKED)
If an invariant is treated as critical but nobody can say whether DB, procedure, or application logic enforces it, then enforcement is not audit-grade.