# Resumable Backfill Contract (LOCKED)

Backfill must be restartable by date range without corrupting canonical output.

## Locked rules
- each date is processed idempotently
- completed dates do not need to be recomputed unless explicitly requested
- historical corrections must create auditable new run artifacts
- partially processed ranges must be resumable from the first incomplete date
