# Run Ownership and Recovery (LOCKED)

## Purpose
Define ownership rules for publish-critical processing so one requested trade date is never finalized, hashed, sealed, or published by conflicting run contexts.

## Core ownership rule (LOCKED)
For one requested trade date T:
- publish-critical stages must have one active owner run
- non-owner runs must not finalize, seal, or publish the candidate dataset for T

Publish-critical stages include:
- hash
- seal
- finalize
- publication switch during correction

## Allowed ownership model
Implementation may use:
- application lock
- DB lock row
- lease record
- stored procedure owner semantics

But the semantic rule is fixed:
- one owner
- no ambiguous dual-owner publish path

## Owner responsibilities
Owner run must:
- produce the coherent candidate artifact set
- compute hashes
- attempt seal only on its own candidate
- finalize terminal status
- preserve prior publication safety if failure occurs

## Non-owner behavior
Non-owner runs must not:
- seal
- finalize as current publication
- switch publication state
- overwrite owner publication evidence

Non-owner runs may:
- fail fast
- abort
- remain informational for diagnosis only

## Recovery rule
If owner run dies, hangs, or loses ownership:
- recovery must explicitly determine whether candidate state is trustworthy
- recovery must not guess publication safety
- if uncertain, prior current readable publication remains active

## Takeover rule
Takeover by another run is allowed only if:
- ownership expiry/recovery policy is satisfied
- ambiguous partial publication state is ruled out
- takeover path is auditable

## Anti-double-finalize rule (LOCKED)
Two runs must never both finalize the same requested date into competing readable publication states.

## Anti-double-seal rule (LOCKED)
Two competing runs must never both become current readable publication for the same effective trade date.

## Minimum evidence
Ownership and recovery flow must preserve:
- owner run identity
- competing/non-owner run identity
- recovery/takeover decision
- resulting publication resolution state