# Patch Audit Summary

## What was corrected
- removed consumer-specific naming from contracts that should be generic downstream contracts
- tightened LOCKED status for commands/runbook, failure playbook, intraday snapshot contract, indicator registry, and config registry
- clarified ATR14 Wilder warmup semantics so the first seed requires 15 canonical bars
- tightened hash scope to the effective date only
- expanded config registry to include timezone and hash/serialization keys
- tightened schema contract wording around controlled correction and sealed audit trail

## Boundary check
This documentation set now stays within market-data upstream scope:
- canonical bars
- indicators
- eligibility
- effective date
- seal/freeze
- intraday snapshot as optional upstream artifact
- locking/logging/runbook/replay/tests

Explicitly excluded:
- downstream screening
- scoring
- grouping
- ranking
- portfolio/execution logic