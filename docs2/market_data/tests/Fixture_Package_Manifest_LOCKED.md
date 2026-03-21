# Fixture Package Manifest (LOCKED)

## Purpose
Define the manifest shape for one fixture package so every fixture family is explicit about:
- what files it contains
- what contracts it covers
- what assertion layers it supports

## Minimum manifest shape

    {
      "fixture_family": "fixture_controlled_correction",
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

## Package filesystem minimum (LOCKED)
A real fixture package must be shippable as one stable directory or archive with this minimum shape:
- `manifest.json`
- `inputs/`
- `expected/`
- optional `notes/` for explanatory, non-normative context

Rules:
- files listed in `manifest.json` must resolve to actual package contents
- expected outputs must live under `expected/`
- input source material must live under `inputs/`
- explanatory notes must never replace missing expected outputs
