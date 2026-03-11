# Dataset Seal and Freeze Contract (LOCKED)

Purpose:
- freeze dataset for effective date D so Watchlist PLAN doesn't drift.

Seal when:
- run finalized
- eligibility built
- hashes computed

After seal:
- dataset for D must not change unless controlled correction (new run_id, notes, new hashes, reseal)

## Consumer dependency (LOCKED)
Watchlist PLAN must only run on SEALED datasets.
Unsealed datasets are considered "not ready" even if status is SUCCESS.