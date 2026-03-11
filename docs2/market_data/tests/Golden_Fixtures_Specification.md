# Golden Fixtures Spec

Defines immutable fixtures for deterministic contract tests and replay tests.

## Required fixture sets
- valid canonical bars
- invalid provider bars
- minimal market calendar sample
- ticker identity mapping sample
- indicator vectors with expected outputs
- expected eligibility snapshot
- expected serialized hash inputs and hash outputs
- requested-date fallback scenarios
- seal/finalize sequencing scenarios
- controlled correction scenarios

## Fixture rules
- fixtures are immutable once published
- changes require new fixture version and explicit note
- fixture filenames should encode semantic version, not ad-hoc timestamps
- one fixture family should test one contract focus; do not mix unrelated assertions in one opaque file

## Minimum fixture catalog (LOCKED)
### `fixture_calendar_v1`
- at least 25 ordered trading days
- includes one non-trading gap so `D[-N]` walk is provable by calendar, not date subtraction

### `fixture_bars_valid_minimal_v1`
- at least 21 canonical bars for one ticker
- sufficient to prove `dv20_idr`, `vol_ratio`, `roc20`, and `hh20`

### `fixture_bars_atr_seed_v1`
- at least 15 canonical bars for one ticker
- expected TR series and first ATR14 seed date explicitly listed

### `fixture_bars_adj_close_fallback_v1`
- mixed window where some days have `adj_close` and some days fall back to `close`
- expected `roc20` proves per-date fallback

### `fixture_invalid_provider_rows_v1`
- negative/zero-invalid price cases
- high-low ordering violation
- duplicate provider rows for one `(trade_date, ticker_id)`

### `fixture_effective_date_fallback_v1`
- requested date blocked by `HELD`
- prior date sealed `SUCCESS`
- expected `trade_date_effective` resolves to prior date

### `fixture_hash_payload_v1`
- explicit serialized lines for bars/indicators/eligibility
- expected SHA-256 outputs
- same content with different `run_id` must keep identical hash

### `fixture_controlled_correction_v1`
- original sealed dataset for D
- corrected rerun for the same D with one intentional content change
- expected new hash + preserved audit trail