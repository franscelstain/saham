# Dataset Seal and Freeze Contract (LOCKED)

Purpose:
- freeze dataset for effective date D so Watchlist PLAN doesn't drift.

Seal when:
- run finalized
- eligibility built
- hashes computed

After seal:
- dataset for D must not change unless controlled correction (new run_id, notes, new hashes, reseal)