# Executed Correction Diff Example (LOCKED)

## Purpose
Show what a real correction diff artifact looks like after a correction is executed and published.

## Example — Executed correction diff

    {
      "trade_date": "2026-03-05",
      "prior_publication_id": 1188,
      "new_publication_id": 1201,
      "prior_run_id": 5001,
      "new_run_id": 5009,
      "prior_publication_version": 1,
      "new_publication_version": 2,
      "changed_artifact_scope": "bars_and_indicators",
      "old_hashes": {
        "bars_batch_hash": "H1B",
        "indicators_batch_hash": "H1I",
        "eligibility_batch_hash": "H1E"
      },
      "new_hashes": {
        "bars_batch_hash": "H2B",
        "indicators_batch_hash": "H2I",
        "eligibility_batch_hash": "H2E"
      },
      "publication_switch": true
    }

## Supporting note
In this example:
- publication version 1 remains preserved as audit-only
- publication version 2 becomes current
- changed scope is explicit
- old/new hashes are explicit

## What this proves
- correction evidence is not only theoretical
- publication switch can be documented with executed-style evidence
- old/new diff is concretely auditable