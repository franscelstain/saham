# Audit Hash and Reproducibility Contract (LOCKED)

## What to hash (LOCKED)
- bars_batch_hash
- indicators_batch_hash (includes indicator_set_version)
- eligibility_batch_hash

Algorithm:
- SHA-256 hex lowercase

Ordering:
- rows ordered by ticker_id asc

Serialization:
- row strings joined by '\n'
- null => empty string

Formatting rules:
- see Hash_Number_Formatting_LOCKED.md