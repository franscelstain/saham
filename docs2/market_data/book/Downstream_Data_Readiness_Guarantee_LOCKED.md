# Downstream Data Readiness Guarantee (LOCKED)

This document states only the minimum upstream readiness guarantee required by downstream consumers.
It does not introduce any downstream screening, scoring, grouping, ranking, or portfolio logic.

## Downstream-safe conditions
For effective trade date D, Market Data Platform guarantees safe consumption only if:
1) `eod_runs` has a finalized sealed `SUCCESS` run that resolves D
2) canonical bars exist for D
3) indicator rows exist for D with `is_valid` and `indicator_set_version`
4) eligibility snapshot exists for D
5) audit hashes exist for D
6) dataset is sealed for D

## What downstream consumers must never do
- infer D via raw tables
- compute indicators from bars
- use unsealed datasets
- treat missing eligibility rows as implicit exclusion logic