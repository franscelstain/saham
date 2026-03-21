# Executed Publication Manifest Example (LOCKED)

## Purpose
Show what a real executed publication manifest looks like after a publication is sealed and current.

## Example — Current publication manifest

    {
      "publication_id": 1201,
      "trade_date": "2026-03-05",
      "run_id": 5009,
      "publication_version": 2,
      "is_current": true,
      "supersedes_publication_id": 1188,
      "seal_state": "SEALED",
      "sealed_at": "2026-03-06T10:15:00+07:00",
      "config_identity": "cfg_2026_03",
      "bars_batch_hash": "H2B",
      "indicators_batch_hash": "H2I",
      "eligibility_batch_hash": "H2E",
      "bars_rows_written": 1000,
      "indicators_rows_written": 1000,
      "eligibility_rows_written": 1000,
      "trade_date_effective": "2026-03-05"
    }

## Example — Superseded publication manifest

    {
      "publication_id": 1188,
      "trade_date": "2026-03-05",
      "run_id": 5001,
      "publication_version": 1,
      "is_current": false,
      "supersedes_publication_id": null,
      "seal_state": "SEALED",
      "sealed_at": "2026-03-05T18:20:00+07:00",
      "config_identity": "cfg_2026_03",
      "bars_batch_hash": "H1B",
      "indicators_batch_hash": "H1I",
      "eligibility_batch_hash": "H1E",
      "bars_rows_written": 1000,
      "indicators_rows_written": 1000,
      "eligibility_rows_written": 1000,
      "trade_date_effective": "2026-03-05"
    }

## What this proves
- publication identity is concrete
- current vs superseded state is concrete
- old/new publication manifests can coexist as real evidence
- publication versioning and supersession are auditable