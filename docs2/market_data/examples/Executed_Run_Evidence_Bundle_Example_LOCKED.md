# Executed Run Evidence Bundle Example (LOCKED)

## Purpose
Show what one real executed evidence bundle should contain when the platform wants to demonstrate proof-by-execution.

## Example bundle contents

### A. Executed run summary
    {
      "run_id": 8124,
      "trade_date_requested": "2026-04-21",
      "trade_date_effective": "2026-04-21",
      "lifecycle_state": "COMPLETED",
      "terminal_status": "SUCCESS",
      "quality_gate_state": "PASS",
      "publishability_state": "READABLE",
      "stage": "FINALIZE",
      "bars_rows_written": 1000,
      "indicators_rows_written": 1000,
      "eligibility_rows_written": 1000,
      "bars_batch_hash": "9d4b8d1a2f6c0e4f7a1d3c5e9b2a4d6f8c1b3e5a7d9f0c2b4a6d8e1f3b5c7a9",
      "indicators_batch_hash": "8c3a7f2d1e5b9a4c6f0d2b8e1a3c5f7d9b1e3a5c7d0f2b4e6a8c1d3f5b7a9c2",
      "eligibility_batch_hash": "7b2c6e1f4a8d0b3c5e9a1d7f2b4c6e8a0d3f5b7c9a1e2d4f6b8c0a3d5e7f9b1",
      "started_at": "2026-04-21T17:31:00+07:00",
      "finished_at": "2026-04-21T17:39:20+07:00"
    }

### B. Executed publication manifest
    {
      "publication_id": 1402,
      "trade_date": "2026-04-21",
      "run_id": 8124,
      "publication_version": 1,
      "is_current": true,
      "seal_state": "SEALED",
      "sealed_at": "2026-04-21T17:39:05+07:00",
      "config_identity": "cfg_2026_04_v3"
    }

### C. Executed reason-code summary
    {
      "trade_date": "2026-04-21",
      "blocked_reason_code_counts": []
    }

### D. Executed `run_event_summary.json`
    {
      "run_id": 8124,
      "trade_date_requested": "2026-04-21",
      "event_count": 17,
      "first_event_time": "2026-04-21T17:31:00+07:00",
      "last_event_time": "2026-04-21T17:39:18+07:00",
      "first_event_type": "RUN_CREATED",
      "last_event_type": "FINAL_STATUS_COMMITTED",
      "highest_severity": "INFO",
      "stage_counts": {
        "INGEST": 5,
        "CANONICALIZE": 4,
        "INDICATORS": 3,
        "FINALIZE": 5
      },
      "reason_code_counts": {}
    }

## What this bundle proves
- one actual run completed
- one actual publication became current
- hashes are actual produced outputs
- publication state is actual produced state
- event trail exists for the run

## Locked note
At least one evidence bundle like this should exist as actual archived execution evidence, not only as illustrative structure.