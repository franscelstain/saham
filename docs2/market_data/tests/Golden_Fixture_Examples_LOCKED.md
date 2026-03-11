# Golden Fixture Examples (LOCKED)

## Purpose
Give concrete human-readable examples of fixture shape so implementations do not guess formatting, expected outputs, or correction semantics.

These examples are normative for structure and meaning.
Storage format may vary, but semantic content must remain identical.

---

## Example 1 — `fixture_hash_payload`

### Bars serialized lines
```text
2026-03-02|101|100.0000|105.0000|99.0000|104.0000|100000|104.0000|API_FREE
2026-03-03|101|104.0000|106.0000|103.0000|105.0000|125000|105.0000|API_FREE
```

### Indicators serialized lines
```text
2026-03-03|101|1||baseline_v1|150000000.00|2.3456|1.2345|0.0567|106.0000
```

### Eligibility serialized lines
```text
2026-03-03|101|1|
2026-03-03|102|0|ELIG_MISSING_BAR
```

### Locked proof intent
- exact field order
- exact number formatting
- exact null serialization
- exact line separator semantics
- changing only run_id must not alter the hash result

## Example 2 — `fixture_bars_atr_seed`

### Simplified canonical bar shape
- At least 15 ordered trading-day bars for the same ticker must be present.

### Expected proof
- TR values are explicitly known for each day where needed
- first ATR14 appears only after 14 TR values exist
- first ATR14 seed is arithmetic mean of first 14 TR values
- next ATR14 follows Wilder recursion

### Locked proof intent
- The fixture must prove both seed and recursive step, not seed alone.

## Example 3 — `fixture_bars_short_history`

### Scenario
- Ticker has fewer bars than required for history-dependent indicators.

### Expected outputs
- indicator row exists but mandatory history-dependent fields are NULL where contract requires
- indicator row is marked invalid with IND_INSUFFICIENT_HISTORY
- eligibility row exists and is denied with ELIG_INSUFFICIENT_HISTORY

### Locked proof intent
- No forward-fill, zero-fill, or guessed history is allowed.

## Example 4 — `fixture_effective_date_fallback`

### Scenario
- requested date `2026-03-10`
- requested date run is not consumable
- latest prior sealed readable date is `2026-03-09`

### Expected outputs
- status for requested date is `HELD` or other non-readable terminal result per scenario
- `trade_date_effective = 2026-03-09`
- consumer reads sealed publication for `2026-03-09`

### Locked proof intent
- Consumer readiness is resolved by explicit effective-date logic, not by maximum available date guessing.

## Example 5 — `fixture_controlled_correction`

### Scenario
- original current sealed publication exists for `2026-03-05`
- correction request approved
- correction execution run changes one consumer-visible canonical value
- new hash set differs from prior publication
- new corrected publication becomes current

### Expected outputs
- old publication remains queryable
- new publication is sealed
- old publication becomes superseded
- new publication is current for `2026-03-05`
- old hash set `H1`
- new hash set `H2`
- `H1 != H2`

### Locked proof intent
A correction is not just “rerun + overwrite”.
It is “new sealed publication + preserved prior trail + explicit supersession”.

## Example 6 — `fixture_unchanged_rerun`

### Scenario
- prior current sealed publication exists for D
- rerun for D produces identical consumer-visible content
- all content hashes remain identical

### Expected outputs
- no new current publication
- prior publication remains current
- rerun may be recorded as audit execution only

### Locked proof intent
- An unchanged rerun must not create fake correction history.

## Example 7 — `fixture_correction_reseal_fail`

### Scenario
- prior current publication exists
- correction run produces candidate changed output
- reseal fails or seal preconditions fail

### Expected outputs
- prior publication remains current
- correction run does not become current publication
- failure trail remains visible

### Locked proof intent
- No ambiguous half-published correction state is allowed.

## Example 8 — `fixture_replay_degraded_input`

### Scenario
- replay injects degraded source coverage or invalid anomaly
- expected result is non-readable requested date outcome

### Expected outputs
- comparison result shows expected degraded behavior
- status matches expected degraded classification
- fallback/effective-date behavior is consistent with readiness contract

### Locked proof intent
Replay proof is not only for happy-path determinism.
It must also prove expected degraded outcomes.

## Locked rule
These examples are minimum proof-shape examples.
Real fixture packages may be richer, but must not be semantically weaker than these examples.