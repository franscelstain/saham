# Test Coverage Closure Contract (LOCKED)

## Purpose
Define how the documentation proves that critical contracts are actually covered by tests and fixtures.

## Closure table shape

| Contract | Covered by test IDs | Covered by fixture families | Closure state |
|---|---|---|---|
| hash determinism | `hash_same_content_same_hash`, `hash_different_runid_same_hash`, `hash_changed_content_diff_hash` | `fixture_hash_payload`, `fixture_controlled_correction` | full |
| correction publication integrity | `correction_preserves_prior_publication`, `correction_publishes_new_current`, `correction_unchanged_content_no_publish` | `fixture_controlled_correction`, `fixture_unchanged_rerun` | full |

## Allowed closure states
- `full`
- `partial`
- `missing`

## Locked rule
A critical contract must not be treated as fully proven unless closure state is `full`.