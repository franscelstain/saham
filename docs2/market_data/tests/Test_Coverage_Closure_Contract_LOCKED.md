# Test Coverage Closure Contract (LOCKED)

## Purpose
Define how the documentation proves that critical contracts are actually covered by tests and fixtures.

## Closure table shape

| Contract | Covered by test IDs | Covered by fixture families | Closure state |
|---|---|---|---|
| hash determinism | `hash_same_content_same_hash_v1`, `hash_different_runid_same_hash_v1`, `hash_changed_content_diff_hash_v1` | `fixture_hash_payload_v1`, `fixture_controlled_correction_v1` | full |
| correction publication integrity | `correction_preserves_prior_publication_v1`, `correction_publishes_new_current_v1`, `correction_unchanged_content_no_publish_v1` | `fixture_controlled_correction_v1`, `fixture_unchanged_rerun_v1` | full |

## Allowed closure states
- `full`
- `partial`
- `missing`

## Locked rule
A critical contract must not be treated as fully proven unless closure state is `full`.