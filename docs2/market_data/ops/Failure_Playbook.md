# Failure Playbook

## Goal
Define operator actions for common failures without weakening downstream safety.

## Cases
### Provider rate limit / timeout
- retry with configured backoff until policy exhausted
- record run events with reason code
- if bars for T remain incomplete at finalization, mark `HELD` or `FAILED` per severity
- consumers fall back to prior sealed date

### Coverage drop
- mark requested date `HELD`
- do not seal T
- investigate provider completeness, ticker mapping drift, and calendar mismatch

### Mass invalid bars
- store rejected rows in `eod_invalid_bars`
- record aggregate invalid count and sample reasons
- if coverage/gates fail, mark `HELD` or `FAILED`

### Indicators missing or invalid at scale
- mark `FAILED`
- do not publish requested date as effective
- recompute only after root cause is fixed

### Hash or seal step fails
- requested date is not consumable even if prior stages succeeded
- final outcome must be `FAILED` or remain `HELD`; never expose unsealed SUCCESS to consumers
