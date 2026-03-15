# Executed Replay Evidence Example (LOCKED)

## Purpose
Show what a real replay evidence artifact should look like after actual execution, not just as a contract shape.

## Example — Executed `replay_result.json`

### Replay summary
    {
      "replay_id": 3001,
      "trade_date": "2025-12-10",
      "trade_date_effective": "2025-12-10",
      "status": "SUCCESS",
      "comparison_result": "MATCH",
      "comparison_note": "all artifact hashes matched expected fixture outcome",
      "artifact_changed_scope": "none",
      "config_identity": "cfg_2025_12_v2",
      "publication_version": 1,
      "bars_batch_hash": "A1F0C3D7E9B21A0C9D3F1A7E8C4B9D2F1C0A4B6D8E2F9A1B3C5D7E9F0A1B2C3",
      "indicators_batch_hash": "B2C1E4F8D0A32B1D8E4F2B8D9C5A0E3D2B1C5D7F9A3C0B2D4E6F8A0B1C2D3E4",
      "eligibility_batch_hash": "C3D2F5A9E1B43C2E9F5A3C9E0D6B1F4E3C2D6E8A0B4D1C3E5F7A9B1C2D3E4F5"
    }

### Replay reason-code counts
    {
      "replay_id": 3001,
      "trade_date": "2025-12-10",
      "reason_code_counts": []
    }

## What this proves
- a replay was actually executed
- actual hashes were captured
- replay result classification is concrete
- config identity used by replay is explicit
- the result is not just a design contract, but an executed evidence example

## Locked note
Production-grade documentation should preserve at least one executed replay example for:
- match
- expected degrade
- mismatch