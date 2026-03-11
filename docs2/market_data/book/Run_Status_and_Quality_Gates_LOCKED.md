# Run Status and Quality Gates (LOCKED)

## Purpose
Run telemetry + pass/fail rules so consumers never consume half-ready data.

## eod_runs fields (conceptual)
- run_id, trade_date_requested, trade_date_effective
- status: SUCCESS | HELD | FAILED
- stage: INGEST_BARS | PUBLISH_BARS | COMPUTE_INDICATORS | BUILD_ELIGIBILITY
- coverage_ratio, row counts, invalid counts, notes

## Gates (LOCKED minimum)
- coverage_ratio >= COVERAGE_MIN else HELD
- indicators stage must complete else FAILED
- eligibility must exist for effective date else FAILED

Consumer rule:
- must use trade_date_effective