# Contract Tests Specification (LOCKED)

## Purpose
Define the minimum automated contract tests required so Market Data Platform remains:
- canonical
- deterministic
- consumer-safe
- auditable
- correction-safe
- replay-verifiable

This specification is normative.
It is not satisfied by smoke tests or process-completed assertions.

## Test implementation rule (LOCKED)
Every implemented contract test must map to:
- a test ID from `Contract_Test_Matrix_LOCKED.md`
- a fixture family from `Golden_Fixture_Catalog_LOCKED.md`
- expected row-level outputs
- expected run-level outputs
- expected hash/seal/publication outcomes when applicable

## Required test groups

### 1. Canonical bar validation
Must prove:
- valid bars enter canonical artifact
- invalid rows never enter canonical bars
- invalid rows are preserved in audit storage
- duplicate provider rows resolve deterministically
- duplicate resolution is auditable

Minimum tests:
- `bars_valid_accept_v1`
- `bars_invalid_reject_v1`
- `bars_duplicate_resolution_v1`

### 2. Indicator correctness
Must prove:
- ATR14 Wilder seed
- ATR14 recursive continuation
- ROC20 uses D[-20]
- vol_ratio uses prior 20 excluding D
- hh20 is inclusive of D
- per-date `adj_close -> close` fallback works
- results do not depend on wall-clock date subtraction

Minimum tests:
- `atr14_seed_v1`
- `atr14_recursive_v1`
- `roc20_dminus20_v1`
- `vol_ratio_prior20_excl_d_v1`
- `hh20_inclusive_v1`
- `price_basis_adj_close_fallback_v1`

### 3. Null and warmup policy
Must prove:
- insufficient history produces NULL mandatory indicators
- invalid indicators carry the correct invalid reason code
- missing dependency bars invalidate dependent indicators
- no forward-fill or zero-fill is used to fake readiness

Minimum tests:
- `indicator_insufficient_history_v1`
- `missing_dependency_bar_v1`

### 4. Eligibility determinism
Must prove:
- one eligibility row exists per universe ticker/date
- missing bars map to the correct reason code
- invalid indicators map to the correct reason code
- insufficient history maps to the correct reason code
- eligibility semantics do not depend on downstream ranking or selection logic

Minimum tests:
- `eligibility_one_row_per_universe_v1`
- `eligibility_missing_bar_v1`
- `eligibility_invalid_indicators_v1`
- `eligibility_insufficient_history_v1`

### 5. Effective-date readiness and fallback
Must prove:
- held requested date falls back to prior readable sealed date
- if no prior readable date exists, effective date stays NULL
- consumers do not infer readability from `MAX(trade_date)`
- readable state requires seal

Minimum tests:
- `effective_date_hold_fallback_v1`
- `effective_date_no_prior_success_v1`
- `success_requires_seal_v1`

### 6. Hash determinism and reproducibility
Must prove:
- identical content yields identical hashes
- different `run_id` alone does not change hashes
- changed content changes the relevant hash
- field ordering is fixed
- formatting is fixed
- null serialization is fixed
- hashing covers only the effective-date artifact content being sealed

Minimum tests:
- `hash_same_content_same_hash_v1`
- `hash_different_runid_same_hash_v1`
- `hash_changed_content_diff_hash_v1`
- `hash_field_order_locked_v1`
- `hash_formatting_locked_v1`

### 7. Seal/finalize sequencing
Must prove:
- seal cannot happen before mandatory hashes
- final success cannot happen before seal
- a readable publication maps to one coherent sealed run context

Minimum tests:
- `seal_requires_hashes_v1`
- `success_requires_seal_v1`
- `single_current_publication_v1`

### 8. Historical correction integrity
Must prove:
- prior published state remains queryable after correction
- corrected publication becomes current only after reseal
- unchanged rerun does not create fake correction publication
- unapproved correction does not publish
- failed reseal does not switch current publication

Minimum tests:
- `correction_preserves_prior_publication_v1`
- `correction_publishes_new_current_v1`
- `correction_unchanged_content_no_publish_v1`
- `correction_requires_approval_v1`
- `correction_failed_reseal_no_switch_v1`

### 9. Replay and data-quality proof
Must prove:
- identical replay inputs reproduce identical outputs/hashes
- degraded input produces expected degraded outcome
- runtime/locale differences do not change hash payload formatting

Minimum tests:
- `replay_same_input_same_output_v1`
- `replay_degraded_expected_hold_v1`
- `replay_runtime_format_stability_v1`

## Minimum assertion payload per implemented test (LOCKED)
Every implemented test must assert all relevant layers below:

### Row-level assertions
Examples:
- expected canonical bar rows
- expected invalid rows
- expected indicator values
- expected eligibility rows
- expected reason codes

### Run-level assertions
Examples:
- expected terminal status
- expected effective date
- expected counts
- expected warning/hard reject counts

### Hash-level assertions
Examples:
- expected bars hash
- expected indicators hash
- expected eligibility hash
- equality across identical reruns
- inequality across changed-content correction runs

### Publication-level assertions
Examples:
- seal exists or does not exist
- current publication did or did not switch
- superseded publication preserved
- unchanged rerun did not create new current publication

## Anti-ambiguity rule (LOCKED)
A test is incomplete if it only validates process completion without validating the contract outputs that matter to consumers and auditors.