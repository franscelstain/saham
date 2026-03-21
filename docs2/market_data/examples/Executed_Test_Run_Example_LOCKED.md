# Executed Test Run Example (LOCKED)

## Purpose
Show what an executed proof-style test output looks like after a real test run.

## Example — Executed contract test output

    {
      "test_id": "hash_same_content_same_hash",
      "fixture_family": "fixture_hash_payload",
      "result": "PASS",
      "assertion_layers": [
        "row",
        "hash"
      ],
      "details": {
        "expected_bars_hash": "A1F0C3D7E9B21A0C9D3F1A7E8C4B9D2F1C0A4B6D8E2F9A1B3C5D7E9F0A1B2C3",
        "actual_bars_hash": "A1F0C3D7E9B21A0C9D3F1A7E8C4B9D2F1C0A4B6D8E2F9A1B3C5D7E9F0A1B2C3",
        "expected_indicators_hash": "B2C1E4F8D0A32B1D8E4F2B8D9C5A0E3D2B1C5D7F9A3C0B2D4E6F8A0B1C2D3E4",
        "actual_indicators_hash": "B2C1E4F8D0A32B1D8E4F2B8D9C5A0E3D2B1C5D7F9A3C0B2D4E6F8A0B1C2D3E4",
        "expected_eligibility_hash": "C3D2F5A9E1B43C2E9F5A3C9E0D6B1F4E3C2D6E8A0B4D1C3E5F7A9B1C2D3E4F5",
        "actual_eligibility_hash": "C3D2F5A9E1B43C2E9F5A3C9E0D6B1F4E3C2D6E8A0B4D1C3E5F7A9B1C2D3E4F5"
      }
    }

## Example — Executed negative test output

    {
      "test_id": "correction_failed_reseal_no_switch",
      "fixture_family": "fixture_correction_reseal_fail",
      "result": "PASS",
      "assertion_layers": [
        "run",
        "publication"
      ],
      "details": {
        "prior_current_run_id": 5001,
        "candidate_run_id": 5011,
        "publication_switch": false,
        "candidate_seal_state": "UNSEALED"
      }
    }


## Naming note
Fields such as `prior_current_run_id` and `candidate_seal_state` are derived executed-proof fields.
They summarize persisted publication/run state and must not be read as schema column names.

## What this proves
- tests are not only specified, but can be represented as executed evidence
- pass/fail evidence can be concrete
- proof-by-execution examples exist alongside proof-spec contracts