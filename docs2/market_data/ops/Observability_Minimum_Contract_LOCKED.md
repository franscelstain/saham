# Observability Minimum Contract (LOCKED)

Minimum observable artifacts per requested date T:
- one `eod_runs` record representing final outcome
- structured stage/event trail in `eod_run_events`
- row counts for bars, indicators, eligibility
- invalid/warning/hard reject counts
- final hashes
- seal metadata when run becomes consumable

If these artifacts are incomplete, the requested date must not be treated as operationally healthy.
