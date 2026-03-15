# Error Taxonomy and Run Status Decision Table (LOCKED)

## Purpose
Map upstream failure classes, data-quality outcomes, and publish-state conditions to the only allowed downstream-visible terminal outcomes:
- `SUCCESS`
- `HELD`
- `FAILED`

This table exists so implementations do not guess when a requested date T may be published, held for fallback, or failed hard.

## Decision principles (LOCKED)
1. `SUCCESS` is allowed only after all mandatory artifacts for the same effective date D exist, all required gates pass, all content hashes exist, and the dataset is sealed.
2. `HELD` means the requested date T is not consumable, but fallback to the latest prior sealed `SUCCESS` date may still keep consumers operational.
3. `FAILED` means a mandatory processing step failed hard or a mandatory artifact is irrecoverably missing for the requested run.
4. Session snapshot failures must never decide EOD terminal status.
5. Warnings alone must not force `HELD` or `FAILED` unless a locked gate uses the aggregate warning condition.
6. A requested date must never appear as downstream-readable when seal is absent.

## Error classes (LOCKED)
### A. Provider / acquisition
- source timeout
- source rate limit
- source auth/config failure
- source schema/response drift
- source returned partial symbol set
- source returned malformed rows

### B. Canonical bar validation
- duplicate provider rows requiring deterministic winner selection
- invalid OHLC ordering
- zero/negative invalid prices
- negative invalid volume
- missing required fields

### C. Indicator computation
- insufficient history
- missing dependency bars in trading-day chain
- invalid upstream bar basis for indicator
- compute exception / overflow / implementation error

### D. Eligibility construction
- coverage-universe row exists but canonical bar missing
- indicators missing
- indicators invalid
- universe dependency unavailable

### E. Publish, hash, seal, finalize
- content hash step missing or failed
- seal preconditions not met
- seal write failed
- finalize attempted before cutoff or before seal
- run ownership / locking conflict

## Terminal outcome decision table (LOCKED)
| Condition | Terminal status | Consumer effect | Notes |
|---|---|---|---|
| Bars published, indicators computed, eligibility built, gates passed, hashes present, seal written | `SUCCESS` | requested/effective date may be consumed | only allowed post-seal success state |
| Provider incomplete or coverage below threshold, but system still has prior sealed `SUCCESS` date | `HELD` | consumers fall back to prior sealed effective date | do not seal requested date |
| Provider timeout/rate-limit exhausted and bars for T remain materially incomplete at finalize | `HELD` | fallback allowed | use run reason code for traceability |
| Provider auth/config failure prevents acquisition entirely | `FAILED` | requested date unusable; fallback may still be used by consumer read model | hard operational failure |
| Provider response/schema changed so normalization contract is no longer trustworthy | `FAILED` | requested date unusable | do not attempt best-effort silent publish |
| Canonical bar invalid row count exists but coverage and all mandatory artifacts still pass | `SUCCESS` | requested/effective date may be consumed | invalid rows stored in audit tables; warnings visible |
| Canonical valid bars missing for enough universe members to break coverage gate | `HELD` | fallback allowed | requested date not sealed |
| Indicator compute exception for mandatory dataset build | `FAILED` | requested date unusable | missing indicators are a hard failure |
| Mandatory indicator rows missing for requested date | `FAILED` | requested date unusable | eligibility build must not pretend success |
| Eligibility snapshot missing entirely | `FAILED` | requested date unusable | one row per coverage-universe ticker is mandatory |
| Hash step failed or hashes absent at finalization time | `FAILED` or remain `HELD`, but never `SUCCESS` | requested date not consumable | implementation may keep non-final hold until operator action, but downstream contract forbids success |
| Seal write failed after success-eligible state | `FAILED` or remain `HELD`, but never `SUCCESS` | requested date not consumable | no unsealed success exposure |
| Finalize attempted before cutoff and policy forbids early final success | non-final or `HELD` | requested date not consumable | exact pre-final label is implementation-specific |
| Lock collision or loss of run ownership on hash/seal/finalize | `FAILED` | requested date unusable for that run | prevents double publication |
| Session snapshot missing/partial/error while EOD artifacts are healthy | no impact on EOD terminal status | none on sealed EOD dataset | snapshot remains optional and separate |

## Minimum reason-code mapping (LOCKED)
### Provider / acquisition
- `RUN_SOURCE_TIMEOUT`
- `RUN_SOURCE_RATE_LIMIT`
- `RUN_SOURCE_AUTH_ERROR`
- `RUN_SOURCE_RESPONSE_CHANGED`
- `RUN_SOURCE_PARTIAL_COVERAGE`
- `RUN_SOURCE_MALFORMED_PAYLOAD`

### Bars / validation
- `BAR_DUPLICATE_SOURCE_ROW`
- `BAR_INVALID_OHLC_ORDER`
- `BAR_NON_POSITIVE_PRICE`
- `BAR_NEGATIVE_VOLUME`
- `BAR_MISSING_REQUIRED_FIELD`

### Indicators
- `IND_INSUFFICIENT_HISTORY`
- `IND_MISSING_DEPENDENCY_BAR`
- `IND_INVALID_BAR_INPUT`
- `IND_COMPUTE_ERROR`

### Eligibility
- `ELIG_MISSING_BAR`
- `ELIG_MISSING_INDICATORS`
- `ELIG_INVALID_INDICATORS`
- `ELIG_INSUFFICIENT_HISTORY`
- `ELIG_UNIVERSE_DEPENDENCY_MISSING`

### Run / publish state
- `RUN_COVERAGE_LOW`
- `RUN_HASH_MISSING`
- `RUN_HASH_FAILED`
- `RUN_SEAL_PRECONDITION_FAILED`
- `RUN_SEAL_WRITE_FAILED`
- `RUN_FINALIZE_BEFORE_CUTOFF`
- `RUN_LOCK_CONFLICT`

## Severity guidance (LOCKED)
- `INFO`: telemetry or successful stage transitions
- `WARN`: anomaly recorded but publication may still succeed if gates pass
- `HARD`: anomaly that directly blocks publication of requested date T

Recommended severity defaults:
- duplicate source row resolved deterministically => `WARN`
- invalid row rejected => `WARN` unless it contributes to gate failure
- coverage below threshold => `HARD`
- missing indicators => `HARD`
- hash/seal/finalize ownership failure => `HARD`
- source response drift => `HARD`

## Consumer rule (LOCKED)
Consumers do not interpret raw error classes directly.
Consumers rely on:
- terminal `eod_runs.terminal_status`
- `trade_date_effective`
- seal presence
- eligibility snapshot for D

Error taxonomy exists to guarantee those fields were produced by deterministic operational rules, not ad-hoc operator judgment.