# Publication Current Pointer Integrity Contract (LOCKED)

## Normative ownership (LOCKED)
This document is the only normative contract for current-publication pointer integrity and current-publication read resolution.

No database note, SQL file, runbook, or schema companion document may redefine, weaken, or soften the behavioral rules stated here.

## Purpose
Define the hardened rule for resolving the one current readable publication for a trade date.

This contract strengthens current-publication integrity beyond a soft `is_current` flag pattern.

## Production stance (LOCKED)
This documentation set treats the hardened pointer model as the normative production target.

That means:
- current publication resolution is owned by `eod_current_publication_pointer`
- `eod_publications.is_current` is mirror state only
- `eod_runs.is_current_publication` is mirror state only
- any legacy or compatibility procedure that updates only soft-current flags is subordinate to this contract

A build that uses only soft-current flags may be acceptable as a temporary compatibility mode, but it is not the preferred production-grade implementation described by this documentation set.

## Core rule (LOCKED)
For each trade date D, there must be exactly one authoritative current-publication pointer entry.

This is represented by:
- `eod_current_publication_pointer.trade_date` as the unique trade-date key
- `publication_id` pointing to the current readable publication for D

## Why this exists
Using only `eod_publications.is_current` is workable, but weaker because:
- MariaDB cannot express the strongest partial-unique constraint elegantly for one `is_current=1` per trade date
- correctness depends more heavily on transaction discipline

The current-publication pointer table hardens this by making:
- one row per trade date
- one pointed publication per trade date
- one publication not reusable across trade dates as current pointer

## Required invariants
1. One trade date D maps to one pointer row only.
2. The pointed publication must belong to the same trade date D.
3. The pointed publication must be sealed.
4. The pointed publication must be the only normal consumer-readable publication for D.
5. Superseded publications remain audit-only and must not be pointed to as current.
6. Pointer update must occur transactionally with publication-state promotion.

## Source-of-truth precedence hierarchy (LOCKED)
If the current-publication pointer table is implemented, current publication resolution must follow this hierarchy:

1. `eod_current_publication_pointer`
2. pointed `eod_publications` row validation
3. `eod_runs` publication/readability consistency checks

Under this hierarchy:
- the pointer table is the primary source of truth for current publication resolution
- `eod_publications.is_current` is a consistency mirror / supporting state
- `eod_runs.is_current_publication` is a consistency mirror / supporting state

## Read-resolution rule (LOCKED)
If this pointer table is implemented, consumer-readable publication resolution must use:
1. `eod_current_publication_pointer`
2. validate the pointed publication row
3. validate seal/current/readability consistency

Consumer resolution must not prefer `eod_publications.is_current` over the pointer table.

## Mismatch handling rule (LOCKED)
If any of the following occurs:
- pointer row points to a publication that is not sealed
- pointer row points to a publication with a mismatched trade date
- pointer row and `eod_publications.is_current` disagree materially
- pointer row and `eod_runs.is_current_publication` disagree materially

then:
- readability for that trade date must be treated as unsafe
- normal consumer read resolution for that trade date must fail safe
- the condition must be treated as an operational incident until reconciled

## Failure rule
If:
- the pointer row is missing for a trade date expected to be readable, or
- pointer row and publication row disagree materially,

then readability for that trade date must be treated as unsafe until reconciled.

## Derived implementation documents
The following documents may describe enforcement, DDL, transaction flow, or operational handling, but they are subordinate to this contract and must not redefine its behavior:
- `../db/Database_Schema_Contracts_MariaDB.md`
- `../ops/Commands_and_Runbook_LOCKED.md`

## Cross-contract alignment
This contract must remain aligned with:
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Consumer_Readability_Decision_Table_LOCKED.md`
- `../db/Publication_Current_Pointer_Switch_Procedure_LOCKED.sql`
- `../db/Database_Schema_Contracts_MariaDB.md`

## Anti-ambiguity rule (LOCKED)
If the system claims one current readable publication per trade date but cannot prove both:
- which object is the primary source of truth
- how mismatches are handled safely

then current-publication integrity is overstated.
## Legacy-compatibility demotion rule (LOCKED)
Any procedure, query, or helper that switches current state through `eod_publications.is_current` without treating the pointer table as the primary owner must be treated as legacy-compatibility material only.

Such material may exist to help migration or local rollout, but it must not be presented to builders as the main publication-resolution path.
