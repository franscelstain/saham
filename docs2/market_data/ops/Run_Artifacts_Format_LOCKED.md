# Run Artifacts Format (LOCKED)

## Purpose
Define the minimum operator-facing artifact formats generated per requested trade date T so a run outcome can be audited, diagnosed, replay-verified, and compared against expectations without manual interpretation of raw tables alone.

These artifacts are operational evidence companions.
They do not replace the authoritative state stored in the main database contracts.

## Artifact categories
For one requested trade date T, the implementation should be able to produce artifact outputs for:
- normal readable success
- held or failed requested date
- historical correction execution
- replay mismatch or replay comparison output

## Minimum artifact set per requested-date run
At minimum, the following artifact shapes must be reconstructable:

1. `run_summary.json`
2. `publication_manifest.json` for each readable or superseded publication state being evidenced
3. `run_event_summary.json`
4. `eligibility_export.csv`
5. `invalid_bars_export.csv` or equivalent bounded sample
6. `anomaly_report.md` or equivalent machine-readable anomaly summary

Where applicable, the following should also be available:
7. `correction_evidence.json`
8. `replay_result.json`

## 1. `run_summary.json`
### Purpose
Provide a compact authoritative run evidence summary for one requested date T.

### Minimum fields
A conforming summary should contain at minimum:

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
      "config_hash": "abc123",
      "config_snapshot_ref": "configs/2026-03-10.json",
      "publication_version": null,
      "is_current_publication": false,
      "supersedes_run_id": null,
      "started_at": "2026-03-10T15:01:00+07:00",
      "finished_at": "2026-03-10T15:09:30+07:00"
    }

### Locked rules
- summary must reflect actual persisted run outcome, not speculative operator interpretation
- if `terminal_status` is readable success, seal/publication evidence must be compatible with readability
- if requested date is held or failed, summary must not imply requested date is readable
- run-summary fields that mirror persisted run state must use the persisted names from `eod_runs`
- derived publication-facing fields may appear only when clearly marked as derived companion evidence, not as replacement names for persisted columns

## 2. `publication_manifest.json`
### Purpose
Provide the compact deterministic proof object for one readable or historical publication state.

### Minimum fields
A conforming manifest should contain at minimum:

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

### Locked rules
- manifest field names must align with the publication-manifest contract, even when values are assembled from multiple persisted tables
- `publication_manifest.json` is publication-shaped evidence, not a promise that every field lives in `eod_publications` itself
- `is_current` and `supersedes_publication_id` are publication fields and must not be replaced by run-mirror names such as `is_current_publication` or `supersedes_run_id`
- a superseded publication manifest remains audit-valid and must not be retroactively rewritten to look current

## 3. `run_event_summary.json`
### Purpose
Provide a compact summary of the append-only `eod_run_events` trail for one run without replacing the underlying event log.

### Minimum fields
A conforming summary should contain at minimum:

    {
      "run_id": 8124,
      "trade_date_requested": "2026-04-21",
      "event_count": 17,
      "first_event_time": "2026-04-21T17:31:00+07:00",
      "last_event_time": "2026-04-21T17:39:18+07:00",
      "first_event_type": "RUN_CREATED",
      "last_event_type": "FINAL_STATUS_COMMITTED",
      "highest_severity": "INFO",
      "stage_counts": {
        "INGEST": 5,
        "CANONICALIZE": 4,
        "INDICATORS": 3,
        "FINALIZE": 5
      },
      "reason_code_counts": {}
    }

### Locked rules
- summary must be derivable from `eod_run_events` and must not invent event history that is absent from the append-only trail
- `first_event_type`, `last_event_type`, `highest_severity`, `stage_counts`, and `reason_code_counts` are derived summary fields, not persisted column names
- if the underlying event trail contains `ERROR` severity, the summary must not present `highest_severity` as lower than `ERROR`
- `run_event_summary.json` summarizes the event trail; it does not replace row-level inspection of `eod_run_events` when detailed diagnosis is required

## 4. `eligibility_export.csv`
### Purpose
Provide a row-level readable/blocking view for the resolved trade date D.

### Minimum columns
- `trade_date`
- `ticker_id`
- `eligible`
- `reason_code`

### Example
    trade_date,ticker_id,eligible,reason_code
    2026-03-09,101,1,
    2026-03-09,102,0,ELIG_MISSING_BAR

### Locked rules
- export must represent one coherent publication context for D
- blocked rows must carry registered reason codes
- export must not mix current and superseded publication states

## 5. `invalid_bars_export.csv`
### Purpose
Provide row-level audit evidence for rejected source rows.

### Minimum columns
- `trade_date`
- `ticker_id`
- `source`
- `source_row_ref`
- `invalid_reason_code`

### Example
    trade_date,ticker_id,source,source_row_ref,invalid_reason_code
    2026-03-10,101,API_FREE,row_001,BAR_INVALID_OHLC_ORDER
    2026-03-10,205,API_FREE,row_019,BAR_NON_POSITIVE_PRICE

### Locked rules
- this export is audit-only
- invalid bars must not be confused with canonical readable bars
- bounded sampling is allowed only if the summary states that sampling was used

## 6. `anomaly_report.md`
### Purpose
Provide a short operator-facing summary of what went wrong or what materially changed.

### Minimum sections
- run identity
- requested/effective date
- dominant anomalies
- terminal status explanation
- fallback implication
- publish safety implication

### Example shape
    # Anomaly Report
    - Requested date: 2026-03-10
    - Effective date: 2026-03-09
    - Status: HELD
    - Dominant anomaly: coverage below threshold due to source timeout burst
    - Consumer effect: fallback to prior readable sealed publication
    - Publication safety: requested date not readable

### Locked rules
- anomaly report must be consistent with run summary
- narrative explanation must not contradict status/seal/publication facts

## 7. `correction_evidence.json`
### Purpose
Provide a compact before/after proof package for a historical correction event.

The JSON shape below is an operator-facing derived artifact.
Fields such as `prior_publication_is_current` and `new_publication_is_current` summarize publication state and are not persisted column names in `eod_publications`.

### Minimum fields
    {
      "correction_id": 9001,
      "trade_date": "2026-03-05",
      "prior_run_id": 5001,
      "new_run_id": 5009,
      "prior_publication_version": 1,
      "new_publication_version": 2,
      "old_hashes": {
        "bars_batch_hash": "H1B",
        "indicators_batch_hash": "H1I",
        "eligibility_batch_hash": "H1E"
      },
      "new_hashes": {
        "bars_batch_hash": "H2B",
        "indicators_batch_hash": "H2I",
        "eligibility_batch_hash": "H2E"
      },
      "publication_switch": true,
      "prior_publication_is_current": false,
      "new_publication_is_current": true
    }

### Locked rules
- old and new publication evidence must coexist
- unchanged rerun must not claim a publication switch
- evidence must not imply silent overwrite of prior publication

## 8. `replay_result.json`
### Purpose
Provide a machine-readable summary when replay comparison does not fully match expectation.

### Minimum fields
    {
      "replay_id": 3001,
      "trade_date": "2025-12-10",
      "trade_date_effective": "2025-12-10",
      "status": "SUCCESS",
      "comparison_result": "MISMATCH",
      "comparison_note": "eligibility output diverged",
      "artifact_changed_scope": "eligibility_only",
      "config_identity": "cfg_2025_12_v2",
      "publication_version": 1,
      "bars_batch_hash": "A1",
      "indicators_batch_hash": "B1",
      "eligibility_batch_hash": "C2",
      "seal_state": "SEALED",
      "expected_status": "SUCCESS",
      "expected_trade_date_effective": "2025-12-10",
      "expected_seal_state": "SEALED",
      "mismatch_summary": "eligibility hash changed while bars hash remained unchanged"
    }

### Locked rules
- replay-result fields that mirror `md_replay_daily_metrics` must use the persisted replay names from replay proof storage
- expected comparison context must remain explicit through replay-expected fields such as `expected_status`, `expected_trade_date_effective`, and `expected_seal_state`
- mismatch summary must not replace detailed evidence, only summarize it

## Artifact timestamp rule
All operator-facing artifact timestamps must be represented consistently in platform timezone or in an explicitly stated alternative representation.
Timestamp inconsistency across artifact families is forbidden.

## Artifact secrecy rule
Artifacts must not expose:
- credentials
- access tokens
- secrets
- internal-only sensitive tokens unrelated to audit/proof

## Cross-contract alignment
These formats must remain aligned with:
- `Audit_Evidence_Pack_Contract_LOCKED.md`
- `Failure_Playbook_LOCKED.md`
- `../book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
- replay contracts
- publication/readiness contracts

## Anti-ambiguity rule (LOCKED)
If two artifacts for the same requested date tell conflicting stories about status, seal state, or publication resolution, the artifact set is invalid and must be corrected.

## See also
- `Audit_Evidence_Pack_Contract_LOCKED.md`
- `Failure_Playbook_LOCKED.md`
- `Historical_Correction_Runbook_LOCKED.md`
- `../backtest/Historical_Replay_and_Data_Quality_Backtest.md`