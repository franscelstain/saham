# Consumer Readability Decision Table (LOCKED)

## Purpose
Provide a single deterministic decision table for consumer readability so downstream readers can resolve:
- what date is readable
- what publication is readable
- when fallback applies
- when no readable date exists
- how corrected publications affect reading

This file is the compact readability oracle.
It complements, but does not replace, the fuller read model and readiness contracts.

## Core rule (LOCKED)
A consumer may read only the current sealed publication for the resolved readable date.

If requested date T is not readable, the consumer must resolve fallback explicitly, not by recency guessing.

## Decision table

| Requested-date state | Current sealed publication for T? | Prior readable sealed publication exists? | Resolved readable date | Consumer action |
|---|---:|---:|---|---|
| `SUCCESS` and current publication exists | Yes | N/A | `T` | read current publication for `T` |
| `SUCCESS` but no current sealed publication | No | maybe | prior readable if any, otherwise none | do not read unsealed/non-current requested date |
| `HELD` | No | Yes | latest prior readable date `D` | fallback to current publication for `D` |
| `HELD` | No | No | none | do not read |
| `FAILED` | No | Yes | latest prior readable date `D` | fallback to current publication for `D` |
| `FAILED` | No | No | none | do not read |
| corrected current publication exists for `D` | Yes | N/A | `D` | read corrected current publication |
| only superseded publication exists for `D` | No | maybe | depends on current readable date resolution | superseded publication is audit-only; do not use for normal reads |
| requested-date candidate is unsealed | No | maybe | prior readable if any, otherwise none | do not read candidate |
| publication resolution ambiguous | No safe resolution | maybe | none until ambiguity cleared | do not read |

## Interpretation rules (LOCKED)

### Rule 1 — Requested date readable
If requested date T has:
- readable terminal semantics
- current publication
- sealed state

then consumer reads T directly.

### Rule 2 — Held or failed requested date
If requested date T is not readable:
- resolve latest prior readable date D if it exists
- read only current sealed publication for D

### Rule 3 — No prior readable date
If no prior readable date exists:
- resolved readable date is none
- consumer must not invent fallback

### Rule 4 — Corrected publication
If a correction replaced the current publication for D:
- the new current publication is the only normal readable state for D
- superseded publication remains audit-only

### Rule 5 — Unsealed success-like candidate
A candidate dataset that looks complete but is not sealed is not readable.

### Rule 6 — Ambiguous publication state
If the system cannot prove exactly one current readable publication:
- readability resolution fails
- consumer must not read

## Forbidden shortcuts
The following are forbidden:
- `MAX(trade_date)` as readable-date resolver
- `MAX(run_id)` as publication resolver
- `MAX(updated_at)` as publication resolver
- selecting superseded publication because it is newer/older by timestamp logic
- merging rows from two publications in one logical read

## Pseudocode read resolution (LOCKED)

    input: requested_date T

    if current_sealed_publication_exists(T) and requested_date_readable(T):
        resolved_date = T
        resolved_publication = current_publication(T)
    else:
        D = latest_prior_readable_date(T)
        if D is null:
            no readable date
        else:
            resolved_date = D
            resolved_publication = current_publication(D)

    read only bars/indicators/eligibility from resolved_publication

## Cross-contract alignment
This document must remain aligned with:
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `Effective_Trade_Date_Contract_LOCKED.md`
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Publication_Switch_Integrity_Contract_LOCKED.md`

## Anti-ambiguity rule (LOCKED)
If two readers can follow this table honestly and still resolve different readable publication states for the same request, the publication/readiness contracts are incomplete.