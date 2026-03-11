# Locking Implementation Standard (LOCKED)

Use one logical run lock per requested trade date and pipeline domain.

## Locked rules
- daily pipeline for the same requested date must be single-writer
- finalize/hash/seal steps must run under the same logical ownership as the producing run
- lock loss or lock collision must prevent duplicate finalization/seal
- backfill for historical dates must not overwrite a running daily job for the same date
