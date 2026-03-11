# Golden Fixture Examples (LOCKED)

This file gives the minimum human-readable shape of golden fixtures so implementation does not guess.
It is not a duplicate of the fixture specification; it is the concrete example layer.

## Example: `fixture_hash_payload_v1`
Bars serialized lines must look like:
- `2026-03-02|101|100.0000|105.0000|99.0000|104.0000|100000|104.0000|API_FREE`
- `2026-03-03|101|104.0000|106.0000|103.0000|105.0000|125000|105.0000|API_FREE`

Indicators serialized lines must look like:
- `2026-03-03|101|1||baseline_v1|150000000.00|2.3456|1.2345|0.0567|106.0000`

Eligibility serialized lines must look like:
- `2026-03-03|101|1|`
- `2026-03-03|102|0|ELIG_MISSING_BAR`

## Example: `fixture_effective_date_fallback_v1`
- requested date `2026-03-10`
- run for `2026-03-10` ends `HELD`
- latest prior sealed `SUCCESS` is `2026-03-09`
- expected `trade_date_effective = 2026-03-09`

## Example: `fixture_controlled_correction_v1`
- original sealed D = `2026-03-05`, hash set `H1`
- corrected rerun for the same D changes one canonical bar, produces hash set `H2`
- `H1 != H2`
- prior run remains queryable in audit trail

## Locked rule
Fixture examples are normative for formatting shape and sequencing intent.
Implementations may store them as CSV/JSON/SQL seeds, but the semantic content must remain identical.