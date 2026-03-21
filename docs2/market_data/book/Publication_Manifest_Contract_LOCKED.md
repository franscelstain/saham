# Publication Manifest Contract (LOCKED)

## Purpose
Define the publication manifest as the compact deterministic identity object for one readable publication.

A publication manifest exists to bind together:
- publication identity
- run identity
- config identity
- artifact hashes
- seal state
- row counts
- correction linkage

This creates a single audit object that proves what one publication is.

## Core rule (LOCKED)
Every current or historical publication should be explainable by one manifest-shaped object, even if the implementation stores the fields across tables.

## Minimum publication manifest fields
A conforming manifest must contain at minimum:

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

## Required meanings
- `publication_id`: unique identity of one publication state
- `trade_date`: logical market date of the publication
- `run_id`: run that produced the publication
- `publication_version`: historical version number for that trade date
- `is_current`: whether this publication is the current readable one
- `supersedes_publication_id`: prior publication replaced by correction, if any
- `seal_state`: whether the publication is sealed
- `config_identity`: effective config snapshot identity used for output
- artifact hashes: content identity of bars/indicators/eligibility
- row counts: compact quantitative description of artifact size

## Manifest rules (LOCKED)
1. Same consumer-visible publication content must imply the same relevant artifact hashes.
2. Different run provenance alone does not change content identity.
3. A corrected publication must produce a new manifest if content changed.
4. An unchanged rerun must not create a fake new publication manifest/version.
5. A manifest for a superseded publication remains audit-valid even when not current.

## Relationship to current publication
The manifest does not replace current-publication resolution.
It is the compact proof object describing one publication.

## Relationship to row-history strategy
If immutable history tables are implemented:
- manifest identifies which publication those snapshots belong to

If history tables are not implemented:
- manifest still anchors the publication through hashes + evidence trail

## Minimum questions a manifest must answer
1. Which publication is this?
2. Which run produced it?
3. Which trade date does it serve?
4. Is it current?
5. Is it sealed?
6. Which config identity produced it?
7. Which artifact hashes define it?
8. Which publication did it supersede, if any?

## Recommended storage options
Allowed implementation patterns:
- explicit `eod_publications` + run table + artifact counts
- generated manifest artifact file
- queryable manifest view
- compact JSON artifact generated at seal/publication time

## Cross-contract alignment
This contract must remain aligned with:
- `Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- `Downstream_Consumer_Read_Model_Contract_LOCKED.md`
- `Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `../ops/Audit_Evidence_Pack_Contract_LOCKED.md`
- `Canonical_Row_History_and_Versioning_Policy_LOCKED.md`

## Anti-ambiguity rule (LOCKED)
If a publication cannot be described by one coherent manifest-shaped proof object, then publication identity is too fragmented for audit-grade use.