# Golden Fixtures Specification (LOCKED)

## Purpose
Define how golden fixtures must be structured so contract tests and replay proofs can be implemented without guessing.

Golden fixtures are immutable proof inputs and expected outputs.
They are not ad-hoc sample files.

## Fixture design rules (LOCKED)
1. A fixture must test a clear contract focus.
2. A fixture family may include multiple files, but they must belong to one semantic scenario.
3. Changing semantic meaning requires a new fixture version.
4. Expected outputs are part of the fixture family, not optional commentary.
5. A fixture that lacks expected outputs does not satisfy this specification.

## Minimum fixture package structure
Each fixture family should include, as applicable:
- input source rows
- normalized/canonical expected rows
- expected invalid rows
- expected indicators
- expected eligibility
- expected run summary outcome
- expected hash payload lines
- expected hash values
- expected publication/correction outcome

## Required fixture families
The required families are listed in:
- `Golden_Fixture_Catalog_LOCKED.md`

This file defines how those fixture families must be shaped.

## Minimum contents by fixture type

### A. Bar-validation fixtures
Must include:
- source input rows
- expected canonical output rows
- expected invalid row outputs
- expected invalid reason codes

### B. Indicator fixtures
Must include:
- canonical bar inputs
- relevant calendar ordering inputs if needed
- expected indicator outputs
- expected invalid reason codes where applicable

### C. Eligibility fixtures
Must include:
- coverage-universe inputs
- canonical bar or indicator dependencies
- expected eligibility outputs
- expected row counts

### D. Hash fixtures
Must include:
- explicit serialized line payloads
- explicit expected SHA-256 outputs
- cases with different `run_id` and identical content

### E. Effective-date fixtures
Must include:
- requested date
- prior readable or non-readable states
- expected `trade_date_effective`
- expected terminal outcome

### F. Correction fixtures
Must include:
- prior current published state
- correction request metadata
- approval metadata where applicable
- correction execution outputs
- old hash set
- new hash set
- expected publication switch or non-switch result

### G. Replay fixtures
Must include:
- source snapshot/extract reference
- config/registry snapshot reference
- calendar/mapping snapshot reference
- expected comparison result
- expected run-level outputs

## Expected-output discipline (LOCKED)
Every fixture family must carry enough expected outputs to support:
- row-level assertions
- run-level assertions
- hash-level assertions where applicable
- publication/correction assertions where applicable

## Fixture naming rule
Fixture family names must be stable semantic identifiers, for example:
- `fixture_bars_atr_seed`
- `fixture_controlled_correction`

Do not use ad-hoc names based only on timestamps or local developer shorthand.

## Anti-drift rule (LOCKED)
When a contract meaning changes intentionally:
- create new fixture version
- keep prior fixture version preserved for historical comparison where needed

Do not silently edit old fixture semantics and pretend nothing changed.

## Executable package rule (LOCKED)
Golden fixtures are not satisfied by prose-only specification.
Each required fixture family must be packageable into concrete files that can be loaded by test runners without inventing missing rows, missing expected outputs, or missing manifest metadata.

Minimum expectation per fixture family:
- at least one concrete input file
- at least one concrete expected-output file
- manifest coverage that names the package and its assertion layers
- stable semantic version identifier
