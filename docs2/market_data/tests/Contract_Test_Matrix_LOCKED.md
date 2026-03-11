# Contract Test Matrix (LOCKED)

## Purpose
Map every critical upstream contract to explicit proof artifacts:
- fixture families
- expected outputs
- expected terminal statuses
- expected hashes
- expected seal/publication behavior

This file exists so test implementation does not guess what must be proven.

## Matrix rules (LOCKED)
1. Every critical contract must map to at least one positive or negative test.
2. A contract that can fail in more than one way should have separate negative tests for materially different failure modes.
3. Every correction-related contract must prove both preservation of prior state and correct publication of new state.
4. Every determinism-related contract must prove both stable-equal and meaningfully-different cases.
5. Test names below are semantic identifiers; implementation names may differ if semantics remain unchanged.

## Matrix

| Contract area | Test ID | Fixture family | What must be proven | Expected outcome |
|---|---|---|---|---|
| Canonical bar validation | `bars_valid_accept_v1` | `fixture_bars_valid_minimal_v1` | valid bar enters canonical artifact | canonical bar written |
| Canonical bar validation | `bars_invalid_reject_v1` | `fixture_invalid_provider_rows_v1` | invalid row rejected from canonical bars and audited | invalid row stored with reason code |
| Canonical bar validation | `bars_duplicate_resolution_v1` | `fixture_invalid_provider_rows_v1` | duplicate source rows resolve deterministically | one winner, loser audited |
| Indicator correctness | `atr14_seed_v1` | `fixture_bars_atr_seed_v1` | ATR14 seed date/value follow Wilder | expected ATR14 seed row |
| Indicator correctness | `atr14_recursive_v1` | `fixture_bars_atr_seed_v1` | next ATR14 value follows Wilder recursion | expected recursive ATR14 value |
| Indicator correctness | `roc20_dminus20_v1` | `fixture_bars_valid_minimal_v1` | ROC20 uses D[-20], not calendar subtraction | expected ROC20 |
| Indicator correctness | `vol_ratio_prior20_excl_d_v1` | `fixture_bars_valid_minimal_v1` | vol_ratio excludes D from denominator window | expected vol_ratio |
| Indicator correctness | `hh20_inclusive_v1` | `fixture_bars_valid_minimal_v1` | hh20 window is inclusive of D | expected hh20 |
| Indicator correctness | `price_basis_adj_close_fallback_v1` | `fixture_bars_adj_close_fallback_v1` | basis uses per-date adj_close then close | expected ratio output |
| Null/warmup policy | `indicator_insufficient_history_v1` | `fixture_bars_short_history_v1` | insufficient history yields NULL mandatory indicator + invalid code | invalid indicator row |
| Null/warmup policy | `missing_dependency_bar_v1` | `fixture_missing_dependency_bar_v1` | missing dependency bar invalidates dependent indicator | invalid indicator row |
| Eligibility | `eligibility_one_row_per_universe_v1` | `fixture_eligibility_universe_v1` | exactly one eligibility row per universe ticker/date | row count matches universe |
| Eligibility | `eligibility_missing_bar_v1` | `fixture_effective_date_fallback_v1` | missing canonical bar yields specific reason | `ELIG_MISSING_BAR` row |
| Eligibility | `eligibility_invalid_indicators_v1` | `fixture_invalid_indicator_rows_v1` | invalid indicators block eligibility | `ELIG_INVALID_INDICATORS` row |
| Eligibility | `eligibility_insufficient_history_v1` | `fixture_bars_short_history_v1` | history insufficiency maps to eligibility denial | `ELIG_INSUFFICIENT_HISTORY` row |
| Effective-date readiness | `effective_date_hold_fallback_v1` | `fixture_effective_date_fallback_v1` | held requested date falls back to prior readable sealed date | expected effective date |
| Effective-date readiness | `effective_date_no_prior_success_v1` | `fixture_no_prior_readable_date_v1` | no prior readable date leaves effective date NULL | effective date NULL |
| Finalization | `success_requires_seal_v1` | `fixture_finalize_without_seal_v1` | final success impossible without seal | not `SUCCESS` |
| Finalization | `seal_requires_hashes_v1` | `fixture_hash_precondition_fail_v1` | seal impossible before hashes exist | seal denied |
| Determinism/hash | `hash_same_content_same_hash_v1` | `fixture_hash_payload_v1` | identical content rerun yields identical hashes | same hash set |
| Determinism/hash | `hash_different_runid_same_hash_v1` | `fixture_hash_payload_v1` | different `run_id` alone does not change hash | same hash set |
| Determinism/hash | `hash_changed_content_diff_hash_v1` | `fixture_controlled_correction_v1` | changed canonical content changes relevant hash | changed hash |
| Determinism/hash | `hash_field_order_locked_v1` | `fixture_hash_payload_v1` | field order matches hash contract | exact expected hash |
| Determinism/hash | `hash_formatting_locked_v1` | `fixture_hash_payload_v1` | formatting and null serialization are fixed | exact expected hash |
| Seal/publication | `single_current_publication_v1` | `fixture_controlled_correction_v1` | exactly one current publication exists for D | one current publication |
| Historical correction | `correction_preserves_prior_publication_v1` | `fixture_controlled_correction_v1` | prior publication remains queryable | preserved prior trail |
| Historical correction | `correction_publishes_new_current_v1` | `fixture_controlled_correction_v1` | corrected sealed publication becomes current | new current publication |
| Historical correction | `correction_unchanged_content_no_publish_v1` | `fixture_unchanged_rerun_v1` | unchanged rerun does not create new publication | current publication unchanged |
| Historical correction | `correction_requires_approval_v1` | `fixture_correction_request_v1` | unapproved correction cannot publish | correction blocked |
| Historical correction | `correction_failed_reseal_no_switch_v1` | `fixture_correction_reseal_fail_v1` | failed reseal must not switch current publication | old publication remains current |
| Replay/data-quality | `replay_same_input_same_output_v1` | `fixture_replay_unchanged_input_v1` | same inputs/config reproduce same outputs/hashes | `MATCH` |
| Replay/data-quality | `replay_degraded_expected_hold_v1` | `fixture_replay_degraded_input_v1` | degraded input produces expected held/failed outcome | expected degraded result |
| Replay/data-quality | `replay_runtime_format_stability_v1` | `fixture_hash_payload_v1` | runtime/locale differences do not alter hash payload | exact expected hash |

## Minimum proof payload per test (LOCKED)
Each implemented test must specify:
- test ID
- contract area
- fixture family
- setup inputs
- expected row-level outputs
- expected run-level outputs
- expected hash outputs if applicable
- expected seal/publication outcome if applicable

## Anti-fake-proof rule (LOCKED)
A test that asserts only “process completed” without verifying contract-level outputs does not satisfy this matrix.