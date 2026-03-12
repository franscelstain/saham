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

## Read-resolution rule (LOCKED)
If this pointer table is implemented, consumer-readable publication resolution should prefer:
1. `eod_current_publication_pointer`
2. then publication metadata validation
3. then run/readiness validation

This is stronger than resolving current state from `eod_publications` flags alone.

## Failure rule
If:
- the pointer row is missing for a trade date expected to be readable, or
- pointer row and publication row disagree materially,

then readability for that trade date must be treated as unsafe until reconciled.

## Cross-contract alignment
This contract must remain aligned with:
- `Publication_Switch_Integrity_Contract_LOCKED.md`
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Consumer_Readability_Decision_Table_LOCKED.md`
- `Publication_Current_Pointer_Switch_Procedure_LOCKED.sql`
- `Database_Schema_Contracts_MariaDB.md`

## Anti-ambiguity rule (LOCKED)
If the system claims one current readable publication per trade date but cannot prove it via either a hardened pointer model or an equally strong enforcement mechanism, then current-publication integrity is overstated.