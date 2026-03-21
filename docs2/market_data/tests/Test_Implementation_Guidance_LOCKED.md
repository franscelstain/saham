# Test Implementation Guidance (LOCKED)

## Purpose
Define how contract tests must be implemented so the test suite proves contract behavior, not merely code execution.

This document complements:
- `Contract_Test_Matrix_LOCKED.md`
- `Golden_Fixture_Catalog_LOCKED.md`
- `Golden_Fixture_Examples_LOCKED.md`

## Core implementation rule (LOCKED)
Every contract test must prove contract outputs that matter to:
- consumer safety
- determinism
- publication resolution
- correction integrity
- replay trustworthiness

A test is not sufficient merely because:
- the process completed
- no exception was raised
- rows were inserted somewhere
- logs look reasonable

## Required assertion layers
Each implemented test must assert all applicable layers below.

### 1. Row-level assertions
Use when the contract affects actual artifact rows.

Typical assertions:
- exact canonical bars written
- exact invalid rows captured
- exact indicator values
- exact eligibility rows
- exact reason codes

### 2. Run-level assertions
Use when the contract affects processing outcome.

Typical assertions:
- expected terminal status
- expected effective trade date
- expected counts
- expected warning/hard reject counts
- expected seal presence or absence

### 3. Hash-level assertions
Use when the contract affects reproducibility or sealing.

Typical assertions:
- exact bars hash
- exact indicators hash
- exact eligibility hash
- same content => same hash
- changed content => changed relevant hash

### 4. Publication-level assertions
Use when the contract affects readability or correction semantics.

Typical assertions:
- exactly one current publication
- publication did or did not switch
- superseded publication preserved
- unchanged rerun did not create new publication version
- unsealed candidate not readable

### 5. Replay-level assertions
Use when the contract affects replay proof.

Typical assertions:
- comparison_result class
- expected-vs-actual alignment
- mismatch classification
- config-identity-sensitive reproducibility

## Fixture-to-test mapping rule (LOCKED)
Every implemented test must explicitly state:
- test ID
- fixture family name
- fixture version
- which assertion layers are active
- expected outputs used as proof

If the mapping is not explicit, the proof trail is too weak.

## Minimum implementation structure
A good test implementation should conceptually follow this shape:

1. load fixture inputs
2. execute only the necessary pipeline slice or full flow under test
3. collect produced artifacts
4. assert row-level outputs
5. assert run-level outputs
6. assert hash outputs where applicable
7. assert publication/replay outputs where applicable

## No fake-proof rule (LOCKED)
The following are insufficient by themselves:
- “command exited successfully”
- “row count > 0”
- “terminal_status is not null”
- “hash exists”
- “publication table has one row”
- “no exception thrown”

These may be supporting signals, but not final proof.

## Fixture versioning rule
If the semantic meaning of a fixture changes:
- create a new fixture version
- do not silently rewrite the old fixture and keep the same version name

Examples:
- `fixture_hash_payload` -> `fixture_hash_payload_v2`
- `fixture_controlled_correction` -> `fixture_controlled_correction_v2`

## When to create a new fixture family
Create a new fixture family when:
- a new contract behavior must be proven
- a materially different failure mode must be isolated
- correction/publication behavior differs from existing fixture scope
- replay proof requires different evidence shape

Do not overload one fixture family with unrelated semantic purposes.

## When to reuse an existing fixture family
Reuse an existing fixture family only if:
- the contract scope is the same
- expected output semantics are the same
- no new ambiguity is introduced

## Publication-aware test rule
If the contract touches readable state, correction, fallback, or seal semantics, the test must include publication assertions.

Examples:
- effective-date fallback tests
- correction tests
- unsealed-success denial tests
- single-current-publication tests

## Hash-aware test rule
If the contract touches determinism, correction, or replay, the test should include hash assertions whenever content identity is relevant.

## Negative test rule
Negative scenarios are mandatory where contract violation risk is real.

Examples:
- missing dependency bar
- missing seal
- missing hashes
- invalid indicators
- correction without approval
- reseal failure
- replay mismatch

## Evidence preservation rule
Test outputs should preserve enough evidence to diagnose failure quickly:
- actual rows
- actual hashes
- actual run summary
- actual publication state
- actual replay result

A failing test that cannot explain *what diverged* is weaker than it should be.

## Cross-contract alignment
Test implementation must remain aligned with:
- hash contract
- correction contract
- publication/readiness contracts
- replay contracts
- schema contracts

## Anti-ambiguity rule (LOCKED)
If a test passes while the implementation could still violate the intended contract behavior in a realistic way, the test is too weak and must be strengthened.