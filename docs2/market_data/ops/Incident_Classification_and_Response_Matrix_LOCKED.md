# Incident Classification and Response Matrix (LOCKED)

## Purpose
Provide one compact matrix for operator response so incident handling does not depend on improvisation.

## Matrix

| Incident type | Severity | Retry allowed | Hold publication? | Fail run? | Preserve prior current publication? | Escalate? |
|---|---|---:|---:|---:|---:|---:|
| isolated source timeout | Medium | Yes | Maybe | No | Yes | Maybe |
| broad source timeout | High | Yes, limited | Yes | Maybe | Yes | Yes |
| source schema drift | Critical | No blind retry | Yes | Yes | Yes | Immediate |
| coverage below threshold | High | Maybe after diagnosis | Yes | Usually no, prefer `HELD` | Yes | Yes |
| indicator compute failure | High | After diagnosis | Yes | Usually yes | Yes | Yes |
| hash computation failure | High | After diagnosis | Yes | Yes or non-final | Yes | Yes |
| seal write failure | High | After diagnosis | Yes | Yes or `HELD` | Yes | Yes |
| finalize before cutoff | Medium | Later retry | Yes | No immediate fail required | Yes | Maybe |
| run ownership conflict | High | Non-owner no | Yes | conflicting path yes | Yes | Yes |
| replay mismatch | High | Retry for diagnosis only | Existing publication may remain | N/A | Yes | Yes |
| config drift | High | After diagnosis only | Yes if publication unsafe | Maybe | Yes | Yes |
| correction reseal failure | High | After diagnosis | Yes | candidate non-published | Yes | Yes |
| publication switch ambiguity | Critical | No unsafe retry | Yes | candidate non-current | Yes | Immediate |
| session snapshot failure | Low/Medium | Yes or skip | No for EOD alone | No | Yes | Maybe |

## Usage rule (LOCKED)
This matrix is the first-stop classification layer.
Detailed step-by-step handling is still governed by:
- `Failure_Playbook_LOCKED.md`
- `Operator_Decision_Trees_LOCKED.md`
- correction runbooks

## Anti-ambiguity rule (LOCKED)
If an incident cannot be classified in this matrix, the ops layer is incomplete and the matrix must be versioned and extended.