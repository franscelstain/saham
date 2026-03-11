# Downstream Data Readiness Guarantee (LOCKED)

## Purpose
Define the exact readiness guarantees that Market Data Platform makes to downstream consumers.

This guarantee exists so consumers know:
- when a date is readable
- when fallback is required
- when a candidate date must not be read
- how correction affects readable state
- what minimum publication conditions must hold before a dataset is treated as safe

This document is upstream-readiness only.
It does not express trading desirability or strategy approval.

## Core readiness guarantee (LOCKED)
A downstream consumer may read a date D only if there is a current sealed publication for D.

Readable state requires all of the following:
- coherent artifact set for D
- terminal run outcome compatible with readability
- content hashes available where required by publication contract
- seal completed
- publication resolved as current for D

If any of these are missing, D is not guaranteed readable.

## Guarantee scope
The readiness guarantee covers:
- bars
- indicators
- eligibility
- effective-date publication state
- seal/publication evidence

It does not guarantee:
- downstream strategy fit
- trading attractiveness
- portfolio suitability
- real-time market continuity
- session snapshot availability

## Readability conditions table (LOCKED)

| Condition | Readable? | Consumer action |
|---|---|---|
| `SUCCESS` + current sealed publication exists for D | Yes | read D |
| `SUCCESS` but seal missing | No | do not read |
| `HELD` for requested date T and prior readable sealed date exists | No for T | fallback to prior readable date |
| `FAILED` for requested date T and prior readable sealed date exists | No for T | fallback to prior readable date |
| `HELD` for requested date T and no prior readable date exists | No | no readable effective date |
| `FAILED` for requested date T and no prior readable date exists | No | no readable effective date |
| corrected new current publication exists for D | Yes | read the new current publication |
| superseded sealed publication exists for D | No for normal reads | audit-only |

## Effective-date fallback guarantee (LOCKED)
If requested date T is not readable:
- the consumer must not read T as if it were safe
- the platform may resolve `trade_date_effective` to the latest prior readable date D
- consumer reads D only if a current sealed publication exists for D

If no prior readable date exists:
- `trade_date_effective` must remain unresolved or NULL according to implementation contract
- consumer must not invent a fallback date

## Seal guarantee (LOCKED)
Seal is a hard readiness boundary.

Therefore:
- unsealed candidate artifacts are not readable
- technically complete but unsealed state is not consumer-safe
- a final readable `SUCCESS` must not exist without required seal semantics

## Publication guarantee (LOCKED)
For one trade date D:
- at most one publication may be current
- the current publication is the only consumer-readable state for D
- superseded publications are preserved for audit only

## Correction guarantee (LOCKED)
If a controlled correction replaces the current publication for D:
- the newly current sealed publication becomes the readable state for D
- prior publication remains auditable
- consumer readiness for D remains continuous through current-publication resolution, not through ad hoc row selection

## Eligibility guarantee
When D is readable:
- the eligibility snapshot for D is the authoritative readable-universe artifact
- blocked rows remain explicit
- consumers must not infer readiness from bars or indicators alone

## Non-guarantees
The platform does not guarantee readability merely because:
- a row exists in `eod_bars`
- a row exists in `eod_indicators`
- a date appears recent
- a `run_id` is large
- timestamps are recent
- session snapshot exists

These are insufficient by themselves.

## Consumer action summary
Consumers must:
- resolve effective date explicitly
- resolve current sealed publication explicitly
- read eligibility/bars/indicators from that one publication context
- treat superseded publications as audit-only

Consumers must not:
- guess from recency
- guess from raw-table presence
- mix publication contexts
- bypass readiness/seal logic

## Required cross-contract alignment
This readiness guarantee must remain aligned with:
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Effective_Trade_Date_Contract_LOCKED.md`
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Publication_Resolution_Contract_LOCKED.md` if implemented separately

## Anti-ambiguity rule (LOCKED)
If a consumer cannot explain why D is readable in terms of current publication, seal state, and effective-date resolution, then D is not safely readable under this contract.

## See also
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Effective_Trade_Date_Contract_LOCKED.md`
- `Run_Status_and_Quality_Gates_LOCKED.md`
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`