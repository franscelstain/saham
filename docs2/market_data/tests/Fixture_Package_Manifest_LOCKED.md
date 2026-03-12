# Fixture Package Manifest (LOCKED)

## Purpose
Define the manifest shape for one fixture package so every fixture family is explicit about:
- what files it contains
- what contracts it covers
- what assertion layers it supports

## Minimum manifest shape

    {
      "fixture_family": "fixture_controlled_correction_v1",
      "version": "v1",
      "contract_areas": [
        "historical_correction_integrity",
        "publication_resolution",
        "hash_determinism"
      ],
      "files": [
        "prior_publication.json",
        "correction_request.json",
        "bars_before.csv",
        "bars_after.csv",
        "expected_hashes_before.json",
        "expected_hashes_after.json",
        "expected_publication_state.json",
        "expected_run_summary.json"
      ],
      "assertion_layers": [
        "row",
        "run",
        "hash",
        "publication"
      ]
    }

## Required manifest fields
- fixture_family
- version
- contract_areas
- files
- assertion_layers

## Assertion layer values
Allowed values:
- `row`
- `run`
- `hash`
- `publication`
- `replay`

## Locked rule
Every real fixture package should be describable by one manifest like this.