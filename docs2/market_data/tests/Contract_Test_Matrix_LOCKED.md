# Contract Test Matrix (LOCKED)

## Purpose
Map every critical upstream contract to explicit proof artifacts so implementation does not guess what must be tested and what evidence each test must assert.

This matrix is normative.
A test suite is not complete merely because code paths execute without error.

## Matrix rules (LOCKED)
1. Every critical contract must map to at least one positive or negative test.
2. A contract that can fail in materially different ways should have separate tests for each important failure mode.
3. Each test must identify:
   - fixture family
   - row-level assertions
   - run-level assertions
   - hash assertions where applicable
   - publication assertions where applicable
   - replay assertions where applicable
4. A test that only proves process completion is insufficient.
5. Historical correction behavior must prove both preservation of prior state and correctness of the new current state.
6. Determinism behavior must prove both equality-under-same-input and inequality-under-real-content-change.

## Test matrix

| Test ID | Contract area | Fixture family | Row assertions | Run assertions | Hash assertions | Publication assertions | Replay assertions |
|---|---|---|---|---|---|---|---|
| `bars_valid_accept_v1` | Canonical bar validation | `fixture_bars_valid_minimal_v1` | expected canonical rows written | counts/status consistent | optional | none | none |
| `bars_invalid_reject_v1` | Canonical bar validation | `fixture_invalid_provider_rows_v1` | invalid rows excluded from canonical bars and captured in invalid storage | invalid counts updated | none | none | none |
| `bars_duplicate_resolution_v1` | Canonical bar validation | `fixture_invalid_provider_rows_v1` | one deterministic winner row and loser audit evidence | counts/status consistent | optional | none | none |
| `atr14_seed_v1` | Indicator correctness | `fixture_bars_atr_seed_v1` | expected ATR14 seed row values | indicator run row counts valid | optional | none | none |
| `atr14_recursive_v1` | Indicator correctness | `fixture_bars_atr_seed_v1` | expected recursive ATR14 value | indicator run row counts valid | optional | none | none |
| `roc20_dminus20_v1` | Indicator correctness | `fixture_bars_valid_minimal_v1` | expected `roc20` value uses `D[-20]` | indicator run row counts valid | optional | none | none |
| `vol_ratio_prior20_excl_d_v1` | Indicator correctness | `fixture_bars_valid_minimal_v1` | expected `vol_ratio` excludes D from denominator | indicator run row counts valid | optional | none | none |
| `hh20_inclusive_v1` | Indicator correctness | `fixture_bars_valid_minimal_v1` | expected `hh20` includes D | indicator run row counts valid | optional | none | none |
| `price_basis_adj_close_fallback_v1` | Indicator correctness | `fixture_bars_adj_close_fallback_v1` | expected per-date fallback behavior | indicator run row counts valid | optional | none | none |
| `indicator_insufficient_history_v1` | Null/warmup policy | `fixture_bars_short_history_v1` | mandatory indicator fields NULL; invalid reason = `IND_INSUFFICIENT_HISTORY` | run counts reflect invalid rows | none | none | none |
| `missing_dependency_bar_v1` | Null/warmup policy | `fixture_missing_dependency_bar_v1` | dependent indicator fields NULL; invalid reason = `IND_MISSING_DEPENDENCY_BAR` | run counts reflect invalid rows | none | none | none |
| `eligibility_one_row_per_universe_v1` | Eligibility determinism | `fixture_eligibility_universe_v1` | exactly one eligibility row per universe ticker/date | eligibility row count matches universe | none | none | none |
| `eligibility_missing_bar_v1` | Eligibility determinism | `fixture_effective_date_fallback_v1` or dedicated eligibility fixture | blocked row reason = `ELIG_MISSING_BAR` | eligibility counts valid | none | none | none |
| `eligibility_invalid_indicators_v1` | Eligibility determinism | `fixture_invalid_indicator_rows_v1` | blocked row reason = `ELIG_INVALID_INDICATORS` | eligibility counts valid | none | none | none |
| `eligibility_insufficient_history_v1` | Eligibility determinism | `fixture_bars_short_history_v1` | blocked row reason = `ELIG_INSUFFICIENT_HISTORY` | eligibility counts valid | none | none | none |
| `effective_date_hold_fallback_v1` | Effective-date readiness | `fixture_effective_date_fallback_v1` | row-level optional | requested date not readable, effective date resolves to prior readable date | none | current publication for fallback date remains authoritative | optional |
| `effective_date_no_prior_success_v1` | Effective-date readiness | `fixture_no_prior_readable_date_v1` | row-level optional | effective date unresolved/NULL | none | no readable publication | optional |
| `success_requires_seal_v1` | Seal/finalize sequencing | `fixture_finalize_without_seal_v1` | row-level optional | final readable success denied | none | no current readable publication switch | none |
| `seal_requires_hashes_v1` | Seal/finalize sequencing | `fixture_hash_precondition_fail_v1` | row-level optional | seal denied / non-final outcome | missing hash proven | no readable publication | none |
| `hash_same_content_same_hash_v1` | Determinism/hash | `fixture_hash_payload_v1` | same serialized payloads | run-level optional | same hash outputs | unchanged publication if rerun only | optional |
| `hash_different_runid_same_hash_v1` | Determinism/hash | `fixture_hash_payload_v1` | same canonical rows under different run IDs | run-level optional | same hash outputs | no fake new publication | optional |
| `hash_changed_content_diff_hash_v1` | Determinism/hash | `fixture_controlled_correction_v1` | changed artifact rows visible | correction run outcome valid | changed relevant hash outputs | new current publication supersedes old | optional |
| `hash_field_order_locked_v1` | Determinism/hash | `fixture_hash_payload_v1` | exact serialized field order implied by expected payload | optional | exact expected hash | none | runtime-stable hash proof optional |
| `hash_formatting_locked_v1` | Determinism/hash | `fixture_hash_payload_v1` | exact number/null/date formatting implied by expected payload | optional | exact expected hash | none | runtime/locale stability optional |
| `single_current_publication_v1` | Publication resolution | `fixture_controlled_correction_v1` | row-level optional | publication resolution valid | optional | exactly one current publication | optional |
| `correction_preserves_prior_publication_v1` | Historical correction integrity | `fixture_controlled_correction_v1` | old and new rows queryable in correct roles | correction run outcome valid | old/new hash trail preserved | old publication audit-only, new publication current | optional |
| `correction_publishes_new_current_v1` | Historical correction integrity | `fixture_controlled_correction_v1` | changed rows reflected in new publication | correction run outcome valid | new hashes present | publication switch succeeds | optional |
| `correction_unchanged_content_no_publish_v1` | Historical correction integrity | `fixture_unchanged_rerun_v1` | identical rows to prior publication | rerun outcome may succeed as audit rerun | hashes identical | no publication switch; version unchanged | optional |
| `correction_requires_approval_v1` | Historical correction integrity | `fixture_correction_request_v1` | row-level optional | unapproved correction cannot publish | none | no new current publication | optional |
| `correction_failed_reseal_no_switch_v1` | Historical correction integrity | `fixture_correction_reseal_fail_v1` | candidate rows may exist but remain non-current | correction candidate non-published | incomplete/new seal failure evidenced | prior publication remains current | optional |
| `replay_same_input_same_output_v1` | Replay/data-quality | `fixture_replay_unchanged_input_v1` | same expected artifact rows | expected terminal outcome | identical expected hashes | same publication semantics | comparison_result = `MATCH` |
| `replay_degraded_expected_hold_v1` | Replay/data-quality | `fixture_replay_degraded_input_v1` | degraded artifact outcomes match expectation | expected non-readable status/effective-date behavior | hashes as expected for degraded scenario when applicable | no false readable publication | comparison_result = `EXPECTED_DEGRADE` |
| `replay_runtime_format_stability_v1` | Replay/data-quality | `fixture_hash_payload_v1` | same logical rows under runtime/locale variance | optional | identical hashes | none | comparison_result = `MATCH` |

## Minimum assertion payload per implemented test (LOCKED)
Every implemented test must explicitly identify and assert:

### Row-level assertions
Examples:
- expected canonical rows
- expected invalid rows
- expected indicator values
- expected eligibility rows
- expected reason codes

### Run-level assertions
Examples:
- expected terminal status
- expected effective trade date
- expected counts
- expected warning/hard reject counts
- expected seal presence or absence

### Hash assertions
Examples:
- exact expected bars hash
- exact expected indicators hash
- exact expected eligibility hash
- equality across identical reruns
- inequality across changed-content correction runs

### Publication assertions
Examples:
- current publication did or did not switch
- superseded publication preserved
- unchanged rerun did not create a new current publication
- only one current publication resolves for D

### Replay assertions
Examples:
- comparison_result class
- mismatch summary expectation
- expected-vs-actual output alignment
- config-identity-sensitive reproducibility

## Anti-fake-proof rule (LOCKED)
A test that proves only “the process ran” or “no exception occurred” does not satisfy this matrix.

## See also
- `Golden_Fixture_Catalog_LOCKED.md`
- `Golden_Fixture_Examples_LOCKED.md`
- `Test_Implementation_Guidance_LOCKED.md`
- `../backtest/Historical_Replay_and_Data_Quality_Backtest.md`