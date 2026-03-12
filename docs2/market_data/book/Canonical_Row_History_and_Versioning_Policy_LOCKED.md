# Canonical Row History and Versioning Policy (LOCKED)

## Purpose
Define the approved strategy for preserving historical row-level auditability of canonical upstream artifacts across corrected publications.

This policy exists because publication/history may be preserved in more than one way, but the chosen strategy must be explicit and non-misleading.

This applies to:
- canonical bars
- indicators
- eligibility

## Core principle (LOCKED)
A corrected publication for trade date D must never make prior consumer-visible row state disappear silently from auditability.

The system must preserve row-history semantics in one explicit way:
- either by immutable publication-bound row snapshots
- or by explicitly documented reliance on publication trail + hash trail + correction evidence

Silent ambiguity about row-history depth is forbidden.

## Approved strategies

### Strategy A — Immutable publication-bound row snapshots
This is the preferred and stronger audit strategy.

Under Strategy A:
- each publication for D has its own immutable row snapshot
- row snapshots are preserved in history tables such as:
  - `eod_bars_history`
  - `eod_indicators_history`
  - `eod_eligibility_history`
- current readable state may still be served from current artifact tables
- historical row-level audit can be reconstructed exactly per publication

### Strategy B — Publication + hash + correction evidence only
This is allowed for simpler deployments where explicit row-history tables are not materialized.

Under Strategy B:
- current readable artifact tables store only the current state
- historical row-level audit is inferred from:
  - publication trail
  - content hashes
  - correction request/evidence
  - replay and artifact evidence where available

This strategy is weaker than Strategy A for row-level audit, but may still be acceptable if documented honestly.

## Locked requirement
One of the above strategies must be explicitly chosen.

The implementation must not:
- imply publication-bound row snapshots exist when they do not
- imply current artifact tables alone preserve historical row state
- silently replace prior row state without correction/publication trail

## Recommended default
For stronger auditability and correction traceability, Strategy A is recommended.

## Strategy A rules (LOCKED)
If Strategy A is implemented:
1. each sealed publication must have one immutable snapshot set
2. history rows must be keyed by `publication_id` plus row identity
3. history rows must never be updated in place
4. corrected publication produces a new snapshot set
5. prior snapshot set remains queryable even after supersession

## Strategy B rules (LOCKED)
If Strategy B is implemented:
1. current artifact tables must be treated as current-state tables only
2. prior row-level state is not assumed to remain queryable from current tables
3. historical audit relies on publication trail + hash trail + correction evidence
4. contracts and runbooks must state this explicitly
5. unchanged rerun must not create fake historical row version state

## Consumer rule
Consumers must always read the current sealed publication state.
Historical row-history strategy is for audit and replay, not for normal consumer read paths.

## Correction rule
On correction for D:
- Strategy A: create new immutable snapshot set and preserve old snapshot set
- Strategy B: preserve old publication/hash/evidence trail and document that row-level snapshots are not materialized

## Minimum audit questions this policy must support
For any corrected date D, the system must be able to answer:
1. what was the prior current publication?
2. what is the new current publication?
3. what changed at artifact level?
4. what hash trail proves the change?
5. what row-history strategy is being used?
6. if snapshots exist, where are they?
7. if snapshots do not exist, what evidence replaces them?

## Relationship to schema
This policy must be reflected in:
- `Database_Schema_MariaDB.sql`
- `Database_Schema_Contracts_MariaDB.md`
- correction contract
- audit evidence pack contract

## Cross-contract alignment
This policy must remain aligned with:
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- `Database_Schema_Contracts_MariaDB.md`

## Anti-ambiguity rule (LOCKED)
If an implementation cannot clearly state whether it uses immutable row snapshots or publication/hash/evidence-only history, then its row-history policy is incomplete and must not be treated as audit-grade.