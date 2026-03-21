# Golden Fixture Catalog (LOCKED)

## Purpose
Provide the authoritative catalog of fixture families required to prove Market Data Platform contracts.

This file is not the fixture data itself.
It defines:
- minimum fixture families
- the semantic purpose of each family
- minimum file sets
- minimum expected outputs
- what each family must be able to prove

## Catalog rules (LOCKED)
1. Fixture families are immutable once published.
2. Semantic changes require a new fixture version identifier.
3. Fixture names must remain stable and descriptive.
4. A fixture family must have a clear contract purpose.
5. Expected outputs are part of the fixture family, not optional commentary.
6. If a fixture family supports correction-aware or publication-aware behavior, that scope must be explicit.

## Catalog execution rule (LOCKED)
Every fixture family listed below is a required semantic family.
A compliant implementation must materialize each required family as an executable package matching:
- `Golden_Fixtures_Specification.md`
- `Fixture_Package_Manifest_LOCKED.md`

## Fixture catalog

### `fixture_calendar`
Purpose:
- prove trading-day traversal independent of wall-clock date subtraction

Minimum file set:
- `calendar.csv`

Minimum expected outputs:
- ordered trading-day sequence
- non-trading gap proof

What must be asserted:
- `D[-N]` traversal correctness
- trading-day ordering correctness

Correction-aware:
- No

Hash-aware:
- No

Publication-aware:
- No

---

### `fixture_bars_valid_minimal`
Purpose:
- prove valid canonical bars and basic indicator expectations

Minimum file set:
- `bars.csv`
- `expected_bars.csv`
- `expected_indicators.csv`
- `expected_run_summary.json`

Minimum expected outputs:
- canonical valid bars
- basic indicator outputs

What must be asserted:
- canonical write correctness
- indicator correctness for standard positive cases

Correction-aware:
- No

Hash-aware:
- Yes, if used for hash payload generation

Publication-aware:
- Optional

---

### `fixture_bars_atr_seed`
Purpose:
- prove ATR14 Wilder seed and recursion

Minimum file set:
- `calendar.csv`
- `bars.csv`
- `expected_indicators.csv`
- `expected_run_summary.json`

Minimum expected outputs:
- ATR14 seed value
- ATR14 recursive next-step value

What must be asserted:
- 15-bar warmup requirement
- seed arithmetic average of first 14 TR values
- recursive Wilder update

Correction-aware:
- No

Hash-aware:
- Optional

Publication-aware:
- No

---

### `fixture_bars_adj_close_fallback`
Purpose:
- prove per-date `adj_close -> close` basis fallback

Minimum file set:
- `bars.csv`
- `expected_indicators.csv`
- `expected_run_summary.json`

Minimum expected outputs:
- expected price-basis-dependent indicator values

What must be asserted:
- per-date fallback behavior
- no one-time global basis choice for the whole window

Correction-aware:
- No

Hash-aware:
- Optional

Publication-aware:
- No

---

### `fixture_invalid_provider_rows`
Purpose:
- prove invalid row rejection and deterministic duplicate resolution

Minimum file set:
- `source_rows.csv`
- `expected_bars.csv`
- `expected_invalid_bars.csv`
- `expected_run_summary.json`

Minimum expected outputs:
- rejected invalid rows
- one deterministic canonical winner for duplicates

What must be asserted:
- invalid rows excluded from canonical bars
- invalid reason codes
- deterministic duplicate resolution

Correction-aware:
- No

Hash-aware:
- Optional

Publication-aware:
- No

---

### `fixture_bars_short_history`
Purpose:
- prove insufficient-history behavior

Minimum file set:
- `calendar.csv`
- `bars.csv`
- `expected_indicators.csv`
- `expected_eligibility.csv`
- `expected_run_summary.json`

Minimum expected outputs:
- NULL mandatory indicator outputs
- invalid indicator reason
- blocked eligibility reason

What must be asserted:
- no fake warmup
- `IND_INSUFFICIENT_HISTORY`
- `ELIG_INSUFFICIENT_HISTORY`

Correction-aware:
- No

Hash-aware:
- Optional

Publication-aware:
- No

---

### `fixture_missing_dependency_bar`
Purpose:
- prove missing dependency bar invalidates dependent computations

Minimum file set:
- `calendar.csv`
- `bars.csv`
- `expected_indicators.csv`
- `expected_run_summary.json`

Minimum expected outputs:
- NULL dependent indicator outputs
- `IND_MISSING_DEPENDENCY_BAR`

What must be asserted:
- missing dependency ≠ ordinary short history
- dependent outputs invalidated correctly

Correction-aware:
- No

Hash-aware:
- Optional

Publication-aware:
- No

---

### `fixture_invalid_indicator_rows`
Purpose:
- prove eligibility behavior when indicator rows exist but are invalid

Minimum file set:
- `expected_indicators.csv`
- `expected_eligibility.csv`
- `expected_run_summary.json`

Minimum expected outputs:
- invalid indicator rows
- eligibility blocked by invalid indicators

What must be asserted:
- `ELIG_INVALID_INDICATORS`
- explicit row-level invalidity propagation

Correction-aware:
- No

Hash-aware:
- Optional

Publication-aware:
- No

---

### `fixture_eligibility_universe`
Purpose:
- prove one-row-per-universe behavior

Minimum file set:
- `universe.csv`
- `bars.csv`
- `expected_eligibility.csv`
- `expected_run_summary.json`

Minimum expected outputs:
- one eligibility row per universe ticker/date
- excluded non-universe tickers absent

What must be asserted:
- exact row cardinality
- correct blocked/readable row mapping

Correction-aware:
- No

Hash-aware:
- Optional

Publication-aware:
- No

---

### `fixture_effective_date_fallback`
Purpose:
- prove held requested date falls back to prior readable sealed date

Minimum file set:
- `requested_run_state.json`
- `prior_publication_state.json`
- `expected_run_summary.json`

Minimum expected outputs:
- effective trade date resolution
- non-readable requested date outcome

What must be asserted:
- no direct read of held requested date
- fallback to prior readable publication

Correction-aware:
- Optional

Hash-aware:
- No

Publication-aware:
- Yes

---

### `fixture_no_prior_readable_date`
Purpose:
- prove no prior readable date leaves effective date unresolved

Minimum file set:
- `requested_run_state.json`
- `expected_run_summary.json`

Minimum expected outputs:
- NULL/unresolved effective date

What must be asserted:
- no invented fallback date
- non-readable state handled explicitly

Correction-aware:
- No

Hash-aware:
- No

Publication-aware:
- Yes

---

### `fixture_hash_payload`
Purpose:
- prove exact serialization and hash reproducibility

Minimum file set:
- `bars_payload.txt`
- `indicators_payload.txt`
- `eligibility_payload.txt`
- `expected_hashes.json`
- `expected_run_summary.json`

Minimum expected outputs:
- exact payload lines
- exact SHA-256 outputs

What must be asserted:
- row ordering
- field ordering
- number formatting
- null serialization
- different `run_id` alone does not change hash

Correction-aware:
- Optional

Hash-aware:
- Yes

Publication-aware:
- Optional

---

### `fixture_finalize_without_seal`
Purpose:
- prove final readable success cannot happen without seal

Minimum file set:
- `candidate_run_state.json`
- `expected_run_summary.json`

Minimum expected outputs:
- success denied or non-readable final state

What must be asserted:
- unsealed state is not readable
- publication does not switch/read as current

Correction-aware:
- No

Hash-aware:
- Optional

Publication-aware:
- Yes

---

### `fixture_hash_precondition_fail`
Purpose:
- prove seal cannot proceed without mandatory hashes

Minimum file set:
- `candidate_run_state.json`
- `expected_run_summary.json`

Minimum expected outputs:
- seal denied
- missing hash evidence

What must be asserted:
- no seal without full hash set
- no readable publication state

Correction-aware:
- No

Hash-aware:
- Yes

Publication-aware:
- Yes

---

### `fixture_controlled_correction`
Purpose:
- prove corrected publication lifecycle

Minimum file set:
- `prior_publication.json`
- `correction_request.json`
- `bars_before.csv`
- `bars_after.csv`
- `expected_hashes_before.json`
- `expected_hashes_after.json`
- `expected_publication_state.json`
- `expected_run_summary.json`

Minimum expected outputs:
- preserved prior publication
- new current publication
- changed hash set
- explicit supersession relation

What must be asserted:
- prior publication remains queryable
- new publication becomes current only after reseal
- old/new hash trail preserved
- publication switch is explicit

Correction-aware:
- Yes

Hash-aware:
- Yes

Publication-aware:
- Yes

---

### `fixture_unchanged_rerun`
Purpose:
- prove unchanged rerun does not create fake correction publication

Minimum file set:
- `prior_publication.json`
- `rerun_outputs.json`
- `expected_hashes.json`
- `expected_publication_state.json`
- `expected_run_summary.json`

Minimum expected outputs:
- identical hashes
- no publication switch
- no version increment

What must be asserted:
- unchanged rerun remains audit rerun only
- current publication unchanged

Correction-aware:
- Yes

Hash-aware:
- Yes

Publication-aware:
- Yes

---

### `fixture_correction_request`
Purpose:
- prove approval gate for correction flow

Minimum file set:
- `approved_request.json`
- `unapproved_request.json`
- `expected_publication_state.json`

Minimum expected outputs:
- approved path may proceed
- unapproved path may not publish

What must be asserted:
- correction requires approval before publication

Correction-aware:
- Yes

Hash-aware:
- No

Publication-aware:
- Yes

---

### `fixture_correction_reseal_fail`
Purpose:
- prove failed reseal blocks publication switch

Minimum file set:
- `prior_publication.json`
- `correction_candidate_run.json`
- `expected_publication_state.json`
- `expected_run_summary.json`

Minimum expected outputs:
- candidate non-current
- prior publication remains current

What must be asserted:
- no half-published correction state
- failed reseal cannot switch publication

Correction-aware:
- Yes

Hash-aware:
- Yes, if hash stage present

Publication-aware:
- Yes

---

### `fixture_replay_unchanged_input`
Purpose:
- prove unchanged replay reproducibility

Minimum file set:
- `source_snapshot_ref.json`
- `config_identity.json`
- `calendar_snapshot_ref.json`
- `expected_run_summary.json`
- `expected_hashes.json`
- `expected_replay_result.json`

Minimum expected outputs:
- same rows
- same hashes
- `comparison_result = MATCH`

What must be asserted:
- reproducibility under identical inputs
- correct comparison classification

Correction-aware:
- Optional

Hash-aware:
- Yes

Publication-aware:
- Optional

---

### `fixture_replay_degraded_input`
Purpose:
- prove replay of degraded data yields expected degraded behavior

Minimum file set:
- `source_snapshot_ref.json`
- `degradation_note.json`
- `expected_run_summary.json`
- `expected_replay_result.json`

Minimum expected outputs:
- expected non-readable or degraded result
- expected comparison classification

What must be asserted:
- correct degraded status behavior
- correct effective-date handling
- no false-success publication

Correction-aware:
- Optional

Hash-aware:
- Optional

Publication-aware:
- Optional

## Locked minimum coverage rule
The fixture families above are the minimum required catalog.
Implementations may add more families, but must not weaken or blur the semantics of the families listed here.

## Cross-contract alignment
This catalog must remain aligned with:
- `Contract_Test_Matrix_LOCKED.md`
- `Golden_Fixture_Examples_LOCKED.md`
- `Test_Implementation_Guidance_LOCKED.md`
- replay/correction/publication contracts

## See also
- `Contract_Test_Matrix_LOCKED.md`
- `Golden_Fixture_Examples_LOCKED.md`
- `Test_Implementation_Guidance_LOCKED.md`