# Negative Test Catalog (LOCKED)

## Purpose
List the minimum negative-path test categories required so proof readiness is not biased toward happy paths only.

## Minimum negative categories
- invalid source row rejection
- duplicate resolution loser preservation
- insufficient history invalidation
- missing dependency bar invalidation
- missing hash prevents seal
- missing seal prevents readable success
- held requested date fallback
- no prior readable date
- correction without approval
- correction reseal failure
- publication ambiguity
- replay mismatch on identical controlled inputs
- config drift affecting output unexpectedly

## Locked rule
If a contract is safety-critical, it must have a negative-path proof where realistic failure is possible.