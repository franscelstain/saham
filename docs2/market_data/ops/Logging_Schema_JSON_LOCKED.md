# Logging Schema JSON (LOCKED)

## Purpose
Define the minimum machine-readable run-event payload used for auditability, troubleshooting, and replay support.

## Storage
Structured events are written to `eod_run_events`.
`event_payload_json` must be a valid JSON object when present.

## Minimum top-level fields
- `run_id`
- `trade_date_requested`
- `stage`
- `event_type`
- `severity`
- `reason_code` (nullable)
- `message`
- `event_time`

## Recommended payload fields
- `ticker_id` when event is row/ticker specific
- `source`
- `http_status`
- `counts`
- `timings_ms`
- `attempt`
- `endpoint`
- `exception_class`
- `exception_message`

## Locked rules
- logs must not be the only place where final status is stored; terminal outcome still belongs to `eod_runs.terminal_status`
- log entries must be append-only
- event timestamps use platform timezone consistently per run
- sensitive credentials must never be written to event_payload_json
