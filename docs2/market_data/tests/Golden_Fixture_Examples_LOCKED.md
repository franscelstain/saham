# Golden Fixture Examples (LOCKED)

## Purpose
Give concrete human-readable examples of fixture shape and expected outcomes so implementations do not guess formatting, expected outputs, run outcomes, publication behavior, or replay evidence.

These examples are normative for structure and meaning.
Storage format may vary, but semantic content must remain identical.

---

## Example 1 — `fixture_hash_payload`

### Bars serialized lines

    2026-03-02|101|100.0000|105.0000|99.0000|104.0000|100000|104.0000|API_FREE
    2026-03-03|101|104.0000|106.0000|103.0000|105.0000|125000|105.0000|API_FREE

### Indicators serialized lines

    2026-03-03|101|1||baseline|150000000.00|0.0238|1.2500|0.0500|109.0000

### Eligibility serialized lines

    2026-03-03|101|1|
    2026-03-03|102|0|ELIG_MISSING_BAR

### Locked proof intent
- exact field order
- exact number formatting
- exact null serialization
- exact line separator semantics
- changing only `run_id` must not alter the hash result

---

## Example 2 — `fixture_bars_atr_seed`

### Scenario
15 consecutive trading-day bars exist for one ticker.

### TR sequence used for seed proof

    1.20, 1.10, 1.00, 0.90, 1.30, 1.40, 1.10,
    1.00, 1.20, 1.00, 0.80, 1.10, 1.30, 1.20

### Expected seed ATR14

    ATR14(seed_date) = 15.60 / 14 = 1.1142857143

### Expected recursive ATR14 on next trading day
If next day `TR = 1.50`:

    ATR14(next_date) = ((1.1142857143 * 13) + 1.50) / 14
                     = 1.1418367347

If `basis_close(next_date)=48.00`:

    atr14_pct(next_date) = 1.1418367347 / 48.00
                         = 0.0237882653

### Locked proof intent
The fixture must prove:
- 15 bars needed before first ATR14 output
- seed is arithmetic average of first 14 TR values
- next ATR14 uses Wilder recursion
- percentage form divides by basis close on target date

---

## Example 3 — `fixture_bars_short_history`

### Scenario
Ticker has only 10 trading-day bars.

### Expected outputs
- `dv20_idr = NULL`
- `atr14_pct = NULL`
- `vol_ratio = NULL`
- `roc20 = NULL`
- `hh20 = NULL`
- `is_valid = 0`
- `invalid_reason_code = IND_INSUFFICIENT_HISTORY`

### Expected eligibility output

    trade_date=D
    ticker_id=101
    eligible=0
    reason_code=ELIG_INSUFFICIENT_HISTORY

### Locked proof intent
No forward-fill, zero-fill, or guessed history is allowed.

---

## Example 4 — `fixture_missing_dependency_bar`

### Scenario
Target date D exists, but a required dependency bar inside the locked trading-day chain is missing unexpectedly.

### Expected outputs
- dependent indicator fields become `NULL`
- `is_valid = 0`
- `invalid_reason_code = IND_MISSING_DEPENDENCY_BAR`

### Locked proof intent
A missing dependency inside a required locked window is not the same as ordinary warmup insufficiency.

---

## Example 5 — `fixture_effective_date_fallback`

### Scenario
- requested date `2026-03-10`
- requested-date run is not consumable
- latest prior sealed readable date is `2026-03-09`

### Expected run-level output

    {
      "trade_date_requested": "2026-03-10",
      "trade_date_effective": "2026-03-09",
      "terminal_status": "HELD",
      "publishability_state": "NOT_READABLE"
    }

### Locked proof intent
Consumer readability is resolved by explicit effective-date logic, not by maximum available date guessing.

---

## Example 6 — `fixture_controlled_correction`

### Scenario
- original current sealed publication exists for `2026-03-05`
- correction request approved
- correction execution run changes one consumer-visible canonical value
- new hash set differs from prior publication
- new corrected publication becomes current

### Expected publication state before correction

    {
      "trade_date": "2026-03-05",
      "publication_version": 1,
      "run_id": 5001,
      "is_current": 1,
      "bars_batch_hash": "H1B",
      "indicators_batch_hash": "H1I",
      "eligibility_batch_hash": "H1E"
    }

### Expected publication state after correction

    {
      "trade_date": "2026-03-05",
      "publication_version": 2,
      "run_id": 5009,
      "is_current": 1,
      "supersedes_publication_id": 1188,
      "bars_batch_hash": "H2B",
      "indicators_batch_hash": "H2I",
      "eligibility_batch_hash": "H2E"
    }

### Expected preserved old state

    {
      "trade_date": "2026-03-05",
      "publication_version": 1,
      "run_id": 5001,
      "is_current": 0
    }

### Locked proof intent
A correction is not “rerun + overwrite”.
It is “new sealed publication + preserved prior trail + explicit supersession”.

---

## Example 7 — `fixture_unchanged_rerun`

### Scenario
- prior current sealed publication exists for D
- rerun for D produces identical consumer-visible content
- all content hashes remain identical

### Expected outcome

    {
      "trade_date": "2026-03-05",
      "current_publication_run_id": 5001,
      "new_rerun_run_id": 5010,
      "publication_switch": false,
      "hashes_identical": true
    }

### Locked proof intent
An unchanged rerun must not create fake correction history.

### Naming note
Fields such as `current_publication_run_id`, `new_rerun_run_id`, `candidate_run_id`, and `prior_current_run_id` are derived proof-artifact names.
They summarize relationships across `eod_publications`, `eod_current_publication_pointer`, and `eod_runs`; they are not persisted column names that replace the schema contracts.

---

## Example 8 — `fixture_correction_reseal_fail`

### Scenario
- prior current publication exists
- correction run produces candidate changed output
- reseal fails or seal preconditions fail

### Expected outcome

    {
      "trade_date": "2026-03-05",
      "candidate_run_id": 5011,
      "candidate_seal_state": "UNSEALED",
      "publication_switch": false,
      "prior_current_run_id": 5001
    }

### Locked proof intent
No ambiguous half-published correction state is allowed.

---

## Example 9 — `fixture_replay_degraded_input`

### Scenario
- replay injects degraded source coverage
- expected result is non-readable requested-date outcome

### Expected replay summary

    {
      "trade_date_requested": "2025-12-10",
      "trade_date_effective": "2025-12-09",
      "terminal_status": "HELD",
      "comparison_result": "EXPECTED_DEGRADE",
      "mismatch_summary": null
    }

### Locked proof intent
Replay proof is not only for happy-path determinism.
It must also prove expected degraded outcomes.

---

## Example 10 — Expected run summary shape

    {
      "run_id": 7001,
      "trade_date_requested": "2026-03-10",
      "trade_date_effective": "2026-03-09",
      "lifecycle_state": "COMPLETED",
      "terminal_status": "HELD",
      "quality_gate_state": "FAIL",
      "publishability_state": "NOT_READABLE",
      "stage": "FINALIZE",
      "source": "API_FREE",
      "coverage_ratio": 0.8420,
      "bars_rows_written": 842,
      "indicators_rows_written": 830,
      "eligibility_rows_written": 1000,
      "invalid_bar_count": 18,
      "invalid_indicator_count": 170,
      "warning_count": 50,
      "hard_reject_count": 12,
      "bars_batch_hash": null,
      "indicators_batch_hash": null,
      "eligibility_batch_hash": null,
      "sealed_at": null,
      "config_version": "cfg_2026_03",
      "started_at": "2026-03-10T15:01:00+07:00",
      "finished_at": "2026-03-10T15:09:30+07:00"
    }

---

## Example 11 — Expected replay mismatch summary

    {
      "comparison_result": "MISMATCH",
      "mismatch_summary": "eligibility hash changed while canonical bars hash remained unchanged; investigate indicator or eligibility logic drift",
      "artifact_changed_scope": "eligibility_only"
    }

## Locked rule
These examples are minimum proof-shape examples.
Real fixture packages may be richer, but must not be semantically weaker than these examples.