# Eligibility Behavior on Partial Data (LOCKED)

Eligibility is a global minimum gate.

Rules:
- missing bar => eligible=0 ELIG_MISSING_BAR
- missing indicators => eligible=0 ELIG_MISSING_INDICATORS
- invalid indicators => eligible=0 ELIG_INVALID_INDICATORS
- provider fetch failure => eligible=0 ELIG_PROVIDER_ERROR
- mandatory indicator NULL => eligible=0 ELIG_INSUFFICIENT_HISTORY

SUCCESS still allowed if coverage >= COVERAGE_MIN and stages complete.