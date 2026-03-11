# Downstream Consumer Read Model Contract (LOCKED)

## Purpose
Define the only allowed downstream read model for Market Data Platform outputs so consumers read a coherent, sealed, correction-aware upstream dataset without guessing from raw tables.

This contract is downstream-facing only in the sense of read behavior.
It does not define downstream scoring, ranking, picks, signals, portfolio actions, or broker execution.

## Core read rule (LOCKED)
A consumer must read one coherent current sealed publication for effective trade date D.

The consumer-readable upstream dataset for D consists of:
- canonical EOD bars for D
- EOD indicators for D
- eligibility snapshot for D
- associated run/hash/seal/publication metadata proving readability

Consumers must not assemble readable state by guessing from the newest rows in underlying tables.

## Required resolution order (LOCKED)
For any requested trade date T, consumer read logic must resolve in this order:

1. determine whether T itself is consumer-readable
2. if T is readable, resolve the current sealed publication for T
3. if T is not readable, resolve fallback to the latest prior readable effective trade date D
4. read the current sealed publication for D
5. load bars/indicators/eligibility only from that one coherent publication context

## Current publication rule (LOCKED)
For one trade date D, multiple historical publications may exist because of controlled correction.
However:
- only one publication may be current
- only the current publication is consumer-readable
- superseded publications are audit-only

## Superseded publication rule (LOCKED)
A superseded publication for D:
- remains queryable for audit and replay
- must not be used by normal consumer read flows
- must not be merged with the current publication
- must not be selected merely because it has a larger `run_id`, newer timestamp, or different row recency pattern

## Effective-date rule (LOCKED)
Consumers must resolve `trade_date_effective` explicitly from readiness/publication rules.

Consumers must not infer readability by:
- `MAX(trade_date)`
- latest inserted row
- latest updated row
- latest `run_id`
- latest `sealed_at` without current-publication resolution

## Publication-aware read rule (LOCKED)
Consumer reads must be publication-aware.

That means:
- one logical read must map to one current sealed publication
- bars, indicators, and eligibility must all belong to the same readable publication state
- the consumer must not mix rows across:
  - different `run_id`
  - different `publication_version`
  - current and superseded publication states

## Correction-aware read rule (LOCKED)
If a historical correction has replaced the current publication for D:
- the newly current sealed publication becomes the only consumer-readable state for D
- the old publication becomes audit-only
- consumers must automatically resolve to the new current publication for D
- consumers must not need custom correction-specific logic outside this publication contract

## Minimum consumer-readable fields
Consumer read implementations must be able to resolve at minimum:
- `trade_date_requested`
- `trade_date_effective`
- publication identity for D
- whether the resolved publication is sealed/current
- row set for bars/indicators/eligibility linked to that publication context

## Minimum safe read pattern
A safe consumer implementation must conceptually do the following:

1. resolve requested date T
2. determine readable effective date D
3. resolve current sealed publication for D
4. load eligibility for D from that publication context
5. load indicators for D from that publication context
6. optionally load bars for D from that publication context
7. never merge data from another publication context into the same logical read

## Eligibility-first read guidance
When consumers need a readable universe for D:
- use the eligibility snapshot first
- treat `eligible = 1` rows as the authoritative readable set
- do not infer readable universe from bars alone
- do not infer readable universe from indicators alone

## Bars/indicators read guidance
Consumers may:
- join indicators to eligibility on the same `(trade_date, ticker_id)` within the same publication context
- join bars to eligibility or indicators on the same `(trade_date, ticker_id)` within the same publication context

Consumers must not:
- read bars from one publication context and eligibility from another
- read indicators by “latest available” logic independent of publication state

## Forbidden consumer shortcuts (LOCKED)
The following are forbidden:
- `MAX(trade_date)` as readability logic
- `MAX(run_id)` as publication selection logic
- `MAX(updated_at)` as publication selection logic
- selecting rows by current timestamp recency alone
- mixing current publication and superseded publication rows
- reading unsealed candidate rows
- recomputing eligibility ad hoc from bars and indicators instead of using the published eligibility snapshot

## Read model examples

### Example A — Normal readable date
- requested date `T = 2026-03-10`
- current sealed publication exists for `2026-03-10`
- consumer reads current publication for `2026-03-10`

### Example B — Held requested date with fallback
- requested date `T = 2026-03-10`
- `2026-03-10` is not readable
- latest prior readable effective date is `2026-03-09`
- consumer reads current publication for `2026-03-09`

### Example C — Corrected historical date
- trade date `D = 2026-03-05`
- publication version 1 was current
- publication version 2 becomes current after controlled correction
- consumer now reads publication version 2
- publication version 1 remains audit-only

## Required cross-contract alignment
This read model must remain aligned with:
- `Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `Effective_Trade_Date_Contract_LOCKED.md`
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Audit_Hash_and_Reproducibility_Contract_LOCKED.md`

## Anti-ambiguity rule (LOCKED)
If a consumer cannot prove which single current sealed publication is being read for D, then the read is not contract-compliant.

## See also
- `Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `Effective_Trade_Date_Contract_LOCKED.md`
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Determinism_Invariants_LOCKED.md`