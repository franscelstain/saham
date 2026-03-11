# Determinism Invariants (LOCKED)

## Purpose
List the non-negotiable invariants that keep Market Data Platform deterministic, reproducible, auditable, and safe for downstream publication.

This document is the compact invariant layer.
It does not replace the detailed contracts for formulas, hashing, seal, publication, correction, replay, or consumer read behavior.
It binds them together.

## Core invariant set (LOCKED)

### Invariant 1 — Same canonical content implies same content hashes
If the consumer-visible canonical content for trade date D is identical, then:
- `bars_batch_hash` must be identical
- `indicators_batch_hash` must be identical
- `eligibility_batch_hash` must be identical

This must hold across reruns and replay.

### Invariant 2 — Different `run_id` alone must not change content identity
If only run provenance changes, such as:
- `run_id`
- timestamps
- operator identity
- audit notes

and consumer-visible content remains identical, then content hashes must remain identical.

### Invariant 3 — Changed consumer-visible content must change the relevant hash
If any consumer-visible artifact content for D changes, then the relevant artifact hash must change.

Examples:
- changed canonical bar value
- changed indicator value
- changed eligibility row
- changed blocking reason code
- changed included row set

### Invariant 4 — One logical readable date resolves to one current sealed publication
For one readable trade date D:
- exactly one publication may be current
- only that current publication is consumer-readable
- superseded publications are audit-only

### Invariant 5 — No mixed-publication reads
A consumer read for D must not mix rows across:
- different `run_id`
- different `publication_version`
- current and superseded publication states

Bars, indicators, and eligibility for one logical read must come from one coherent publication context.

### Invariant 6 — No unsealed readable publication
An unsealed candidate dataset is not readable.
Readable state requires current sealed publication semantics.

### Invariant 7 — Effective-date resolution must be explicit
Readable fallback must be resolved explicitly through effective-date rules.
It must not be guessed from:
- latest raw date
- latest inserted row
- latest updated row
- largest `run_id`

### Invariant 8 — Trading-day traversal is deterministic
Any window reference such as:
- `D[-1]`
- `D[-20]`
- prior-20
- inclusive-20
- ATR14 warmup

must be evaluated by trading-day sequence, not by calendar subtraction.

### Invariant 9 — Per-date price basis fallback is deterministic
Where closing-price basis is required:
- use `adj_close` if present on that specific date
- otherwise use `close`

This fallback is evaluated per date, not once for the whole series/window.

### Invariant 10 — Insufficient history must not be faked
If the required history window is not satisfied:
- dependent indicator output must not be fabricated
- `NULL` and invalid-state semantics must be explicit
- forward-fill, zero-fill, or guessed warmup is forbidden

### Invariant 11 — Missing dependency bars must invalidate dependent outputs
If a required dependency bar in the locked trading-day chain is missing unexpectedly:
- dependent outputs must become invalid
- the missing dependency must not be silently skipped

### Invariant 12 — Unchanged rerun must not create fake correction publication
If a rerun for D produces identical consumer-visible content and identical hashes:
- it may be recorded as audit rerun
- it must not create a new published state
- it must not increment publication version
- it must not supersede the current publication

### Invariant 13 — Corrected publication must preserve prior publication trail
If a controlled correction produces changed consumer-visible content for D:
- a new sealed publication may become current
- the old publication must remain queryable
- the old publication must become audit-only, not erased

### Invariant 14 — Replay must reproduce unchanged historical inputs
If replay uses identical:
- source snapshot
- calendar ordering
- ticker identity/membership semantics
- config identity
- formula contracts
- serialization rules

then replay must reproduce identical outputs and identical hashes.

### Invariant 15 — Formatting/runtime differences must not cause semantic drift
Runtime environment differences such as locale or formatting behavior must not change:
- serialized hash payloads
- content hashes
- publication interpretation

### Invariant 16 — One universe ticker/date yields one eligibility row
For one readable or evaluated trade date D:
- each coverage-universe ticker must resolve to exactly one eligibility row for D
- blocked readiness must be explicit through registered reason codes

### Invariant 17 — Publication switch must be unambiguous
During correction or publication change:
- there must never be ambiguous dual-current state
- there must never be zero-current state left behind as final resolution
- if ambiguity exists, prior safe publication must remain active until resolved

## What these invariants protect
Together, these invariants protect:
- reproducibility
- consumer safety
- correction integrity
- auditability
- replay trustworthiness
- schema/query determinism

## Required cross-contract alignment
These invariants must remain aligned with:
- `Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- `EOD_Indicators_Formula_Spec.md`
- `Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Downstream_Data_Readiness_Guarantee_LOCKED.md`
- replay and fixture contracts

## Anti-ambiguity rule (LOCKED)
If an implementation cannot explain its behavior using these invariants without inventing extra hidden rules, then the implementation is not deterministic enough for this contract set.

## See also
- `Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- `../indicators/EOD_Indicators_Formula_Spec.md`
- `Historical_Replay_and_Data_Quality_Backtest.md`
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`