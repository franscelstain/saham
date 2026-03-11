# Invalid Bar Storage Policy (LOCKED)

- invalid provider rows must not be inserted into canonical `eod_bars`
- invalid provider rows may be stored in `eod_invalid_bars` for auditability
- downstream readers must ignore `eod_invalid_bars` as data input
- invalid rows must contribute to eligibility reasoning and run telemetry only
