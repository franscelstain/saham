# Audit Evidence Pack Contract (LOCKED)

## Purpose
Define the minimum evidence package that must be reconstructable for:
- one requested-date run
- one historical correction case
- one replay mismatch case

## Requested-date evidence pack
Must include at minimum:
- run summary
- requested/effective trade date
- terminal status
- dominant reason codes
- hash set
- seal state
- config identity
- current publication resolution

## Historical correction evidence pack
Must include at minimum:
- correction request
- approval metadata
- prior current publication reference
- new run reference
- old hash set
- new hash set
- publication switch result
- supersession relation

## Replay mismatch evidence pack
Must include at minimum:
- expected run summary/hash outcome
- actual run summary/hash outcome
- mismatch classification
- mismatch summary
- config identity
- artifact-changed scope

## Locked rule
A run or correction that cannot produce enough evidence to explain publication safety is not operationally healthy.