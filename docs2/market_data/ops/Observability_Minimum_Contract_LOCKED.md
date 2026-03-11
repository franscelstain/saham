# Observability Minimum Contract (LOCKED)

## Purpose
Define the minimum observable evidence required so one requested date T can be diagnosed, audited, and replay-verified without relying on ad-hoc log scraping.

## Minimum observable artifacts per requested date T
- one `eod_runs` record representing the terminal outcome
- structured append-only stage/event trail in `eod_run_events`
- row counts for bars, indicators, and eligibility
- invalid/warning/hard reject counts
- final content hashes when hash stage completed
- seal metadata when run becomes consumable
- config identity for the effective registry snapshot used by the run

## Minimum observability questions that must be answerable
1. What was the terminal outcome for requested date T?
2. What effective date D did consumers resolve to?
3. Which stage failed or held publication?
4. Which dominant reason codes occurred?
5. Did coverage pass or fail?
6. Were hashes produced?
7. Was the dataset sealed?
8. Which config identity was used?

## Locked rules
- `eod_runs` is the system of record for terminal outcome; logs are supporting evidence, not the final authority
- `eod_run_events` must be append-only
- missing observability artifacts are themselves an operational defect
- if terminal status, effective date, hash presence, or seal presence cannot be proven, the requested date must not be treated as operationally healthy