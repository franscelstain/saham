# Golden Fixture Catalog (LOCKED)

## Purpose
Provide the minimum canonical catalog of fixture families required to prove Market Data Platform contracts.

This file is not the fixture data itself.
It is the authoritative inventory and semantic purpose of each fixture family.

## Catalog rules (LOCKED)
1. Fixture families are immutable once published.
2. Semantic changes require a new fixture version identifier.
3. Fixture family names must remain stable and descriptive.
4. Each fixture family must test a clear contract focus.
5. One fixture family may support multiple tests, but its semantic purpose must stay explicit.

## Fixture catalog

### `fixture_calendar_v1`
Purpose:
- prove trading-day traversal independent of wall-clock date subtraction

Minimum contents:
- at least 25 ordered trading days
- at least one non-trading gap
- explicit sequence index or equivalent order evidence

Supports:
- indicator window traversal
- D[-N] proofs
- effective-date logic

### `fixture_bars_valid_minimal_v1`
Purpose:
- prove standard valid canonical bars and basic indicator expectations

Minimum contents:
- at least 21 canonical bars for one ticker
- enough data for `dv20_idr`, `vol_ratio`, `roc20`, `hh20`

Supports:
- positive bar validation
- basic indicator expectations
- hash payload construction

### `fixture_bars_atr_seed_v1`
Purpose:
- prove ATR14 Wilder seed and recursive continuation

Minimum contents:
- at least 15 canonical bars
- explicit TR values
- expected ATR14 seed date and value
- expected next ATR14 recursive value

Supports:
- ATR14 seed
- ATR14 recursion
- warmup policy

### `fixture_bars_adj_close_fallback_v1`
Purpose:
- prove per-date basis fallback from `adj_close` to `close`

Minimum contents:
- mixed window where some rows have `adj_close`
- some rows lack `adj_close`
- expected indicator results using per-date fallback

Supports:
- ROC20
- any other price-basis-dependent outputs

### `fixture_invalid_provider_rows_v1`
Purpose:
- prove invalid row rejection and duplicate resolution

Minimum contents:
- invalid OHLC ordering row
- non-positive price row
- negative volume row
- missing required field row
- duplicate source rows for one `(trade_date, ticker_id)`

Supports:
- invalid-bar audit
- duplicate resolution
- reason-code correctness

### `fixture_bars_short_history_v1`
Purpose:
- prove insufficient history behavior

Minimum contents:
- fewer rows than needed for history-dependent indicators
- explicit expected invalid indicator rows
- explicit expected eligibility denial rows

Supports:
- null/warmup policy
- eligibility insufficiency reason codes

### `fixture_missing_dependency_bar_v1`
Purpose:
- prove missing bar in trading-day chain invalidates dependent indicator calculations

Minimum contents:
- a sequence where one required dependency date is absent
- expected invalid indicator output
- expected reason code

Supports:
- dependency-bar invalidation
- no-forward-fill rule

### `fixture_invalid_indicator_rows_v1`
Purpose:
- prove eligibility behavior when indicator rows exist but are invalid

Minimum contents:
- indicator rows with `is_valid = 0`
- expected eligibility reason codes

Supports:
- `ELIG_INVALID_INDICATORS`
- row-level invalid handling

### `fixture_eligibility_universe_v1`
Purpose:
- prove one-row-per-universe behavior

Minimum contents:
- at least one universe ticker with valid bar
- at least one universe ticker without valid bar
- at least one ticker excluded from universe
- expected full eligibility row set

Supports:
- denominator correctness
- one-row-per-universe rule

### `fixture_effective_date_fallback_v1`
Purpose:
- prove held requested date falls back to prior sealed readable date

Minimum contents:
- requested date T not consumable
- prior date with readable sealed publication
- expected `trade_date_effective`

Supports:
- fallback
- readiness behavior
- consumer safety

### `fixture_no_prior_readable_date_v1`
Purpose:
- prove no prior readable date leaves effective date unresolved

Minimum contents:
- requested date not consumable
- no prior sealed readable date

Supports:
- null effective date behavior

### `fixture_hash_payload_v1`
Purpose:
- prove exact serialization order and hash reproducibility

Minimum contents:
- explicit serialized lines for bars
- explicit serialized lines for indicators
- explicit serialized lines for eligibility
- expected SHA-256 outputs
- repeated case with different `run_id` but same content

Supports:
- field ordering
- formatting
- null serialization
- provenance exclusion

### `fixture_finalize_without_seal_v1`
Purpose:
- prove final success cannot happen without seal

Minimum contents:
- success-eligible-like run lacking seal
- expected blocked final success outcome

Supports:
- seal/finalization sequencing

### `fixture_hash_precondition_fail_v1`
Purpose:
- prove seal cannot proceed without mandatory hashes

Minimum contents:
- candidate run missing one or more hashes
- expected seal denial outcome

Supports:
- seal preconditions

### `fixture_controlled_correction_v1`
Purpose:
- prove corrected publication lifecycle

Minimum contents:
- original published sealed state for D
- correction request metadata
- new correction execution run
- changed consumer-visible content
- new hashes
- new seal
- supersession metadata

Supports:
- correction publication
- preserved prior publication
- current publication switch
- changed hash proof

### `fixture_unchanged_rerun_v1`
Purpose:
- prove unchanged rerun does not create fake correction publication

Minimum contents:
- prior published sealed state
- rerun with identical content
- identical hashes
- expected “no publication switch” result

Supports:
- audit rerun vs correction distinction

### `fixture_correction_request_v1`
Purpose:
- prove approval gate for correction flow

Minimum contents:
- correction request
- one approved case
- one unapproved case

Supports:
- correction approval requirement

### `fixture_correction_reseal_fail_v1`
Purpose:
- prove failed reseal blocks publication switch

Minimum contents:
- prior current publication
- correction run output
- reseal failure condition
- expected old publication remains current

Supports:
- correction failure safety

### `fixture_replay_unchanged_input_v1`
Purpose:
- prove unchanged replay reproducibility

Minimum contents:
- identical source extract
- identical registry/config snapshot
- identical calendar/mapping snapshot
- expected `MATCH`

Supports:
- replay determinism

### `fixture_replay_degraded_input_v1`
Purpose:
- prove replay of degraded data yields expected held/failed behavior

Minimum contents:
- degraded source snapshot or anomaly injection
- expected held/failed outcome
- expected comparison classification

Supports:
- replay anomaly behavior

## Locked requirement
The fixture catalog above is minimum required coverage.
Implementations may add more fixture families, but must not reduce or blur the semantics of the families listed here.