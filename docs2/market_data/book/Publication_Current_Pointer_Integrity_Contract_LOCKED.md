# Publication Current Pointer Integrity Contract (LOCKED)

## Purpose
Define the hardened database-facing rule for resolving the one current readable publication for a trade date.

This contract strengthens current-publication integrity beyond a soft `is_current` flag pattern.

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
If the current-publication pointer table is implemented, then current publication resolution must follow this hierarchy:

1. `eod_current_publication_pointer`
2. pointed `eod_publications` row validation
3. `eod_runs` publication/readability consistency checks

Under this hierarchy:
- the pointer table is the primary source of truth for current publication resolution
- `eod_publications.is_current` is a consistency mirror / supporting state
- `eod_runs.is_current_publication` is a consistency mirror / supporting state

## Read-resolution rule (LOCKED)
If this pointer table is implemented, consumer-readable publication resolution must prefer:
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