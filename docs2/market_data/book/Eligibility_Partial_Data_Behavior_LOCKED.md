# Eligibility Partial Data Behavior (LOCKED)

## Purpose
Define how eligibility must behave when upstream data for trade date D is partial, missing, invalid, or degraded.

This document prevents ambiguous “best effort” readiness that could mislead downstream consumers.

## Core rule (LOCKED)
Eligibility must be explicit even when upstream data is partial.

For every ticker in coverage universe for D:
- one eligibility row must still exist
- partial or degraded data must result in `eligible = 0` with a registered blocking `reason_code`
- partial data must never be silently treated as readable

## Allowed partial-data outcomes
The platform may still build an eligibility snapshot for D even when some rows are blocked.

Examples:
- some tickers have missing bars
- some tickers have invalid indicators
- some tickers have insufficient history
- some tickers have optional fetch failures

This is allowed as long as:
- one row per universe ticker still exists
- each blocked row has the correct registered blocking reason
- run-level readiness rules remain independent from row-level eligibility details

## Minimum row-level blocking codes (LOCKED)
Use only official registered codes:
- `ELIG_MISSING_BAR`
- `ELIG_MISSING_INDICATORS`
- `ELIG_INVALID_INDICATORS`
- `ELIG_INSUFFICIENT_HISTORY`
- `ELIG_UNIVERSE_DEPENDENCY_MISSING`
- `ELIG_FETCH_FAILURE` if optional fetch-failure tracking is implemented

## Missing-bar behavior
If a ticker is in universe for D but no canonical valid bar exists:
- emit eligibility row
- `eligible = 0`
- `reason_code = ELIG_MISSING_BAR`

## Missing-indicators behavior
If canonical bar exists but required indicators are absent:
- emit eligibility row
- `eligible = 0`
- `reason_code = ELIG_MISSING_INDICATORS`

## Invalid-indicators behavior
If indicator row exists but required indicator state is invalid:
- emit eligibility row
- `eligible = 0`
- `reason_code = ELIG_INVALID_INDICATORS`

## Insufficient-history behavior
If the blocking cause is insufficient history:
- emit eligibility row
- `eligible = 0`
- `reason_code = ELIG_INSUFFICIENT_HISTORY`

## Optional fetch-failure behavior
If optional fetch-failure tracking is implemented and source retrieval failure is the direct blocking cause for a ticker:
- emit eligibility row
- `eligible = 0`
- `reason_code = ELIG_FETCH_FAILURE`

If fetch-failure tracking is not implemented, the implementation must still map the row to a valid registered blocking reason based on the downstream artifact that is actually missing or invalid.

## Run-level vs row-level distinction (LOCKED)
Row-level blocked eligibility does not automatically determine run-level terminal status.

Run-level status still depends on:
- coverage thresholds
- required artifact existence
- hash/seal/finalization rules
- other locked gates

Therefore:
- some blocked eligibility rows may coexist with terminal `SUCCESS`
- severe degradation may cause run `HELD` or `FAILED`
- row-level eligibility and run-level final status must not be conflated

## Anti-silent-readiness rule (LOCKED)
Partial data must never cause:
- missing eligibility rows
- empty blocking reason for blocked rows
- implicit best-effort readiness
- consumer-side guessing about why a ticker is blocked

## Determinism rule
Given identical upstream inputs and registry contracts, partial-data eligibility outcomes must remain deterministic across reruns.