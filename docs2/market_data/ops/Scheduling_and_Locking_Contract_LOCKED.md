# Scheduling and Locking Contract (LOCKED)

## Required behavior
- scheduler may trigger jobs multiple times safely; pipeline must remain idempotent
- a second trigger for the same requested trade date must either attach to the existing run context or exit without duplicating output
- finalize/hash/seal must occur after compute stages in strict order
- intraday snapshot jobs must never block or mutate EOD sealed datasets