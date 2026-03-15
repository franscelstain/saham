# Audit Evidence Pack Contract (LOCKED)

## Purpose
Define the minimum evidence package that must be reconstructable for:
- one requested-date run
- one historical correction case
- one replay mismatch case

This contract ensures that publication safety, determinism, correction behavior, and replay discrepancies can be explained without guesswork.

## Core principle (LOCKED)
A run, correction, or replay outcome is not operationally healthy unless enough evidence exists to explain:
- what happened
- what was readable or not readable
- which publication state was current
- which hashes were involved
- whether prior safe state was preserved

## Evidence-pack categories
The platform must be able to reconstruct at minimum:
1. requested-date run evidence pack
2. historical correction evidence pack
3. replay result evidence pack

---

## 1. Requested-date run evidence pack

### Purpose
Explain one requested trade date T and its resolved publication/readiness outcome.

### Minimum contents
A conforming run evidence pack must include at minimum:
- run summary
- publication manifest or explicit publication-resolution object
- run-event summary or explicit event-trail reference
- requested trade date
- effective trade date
- terminal status
- dominant reason-code summary
- bars/indicators/eligibility hash set, when applicable
- seal state
- config identity
- current publication resolution
- anomaly summary if status is held/failed or materially degraded

### Minimum questions it must answer
1. What requested date was processed?
2. What run ID produced the result?
3. What terminal status occurred?
4. What effective date became readable, if any?
5. Was the requested date sealed/readable?
6. Which publication was current?
7. What event trail summary proves the run actually executed?
8. What dominant anomalies occurred?
9. Which config identity applied?

### Minimum example shape
    {
      "run_summary": { "...": "..." },
      "publication_manifest": { "...": "..." },
      "run_event_summary": { "...": "..." },
      "dominant_reason_codes": [
        { "reason_code": "RUN_COVERAGE_LOW", "count": 1 },
        { "reason_code": "ELIG_MISSING_BAR", "count": 158 }
      ],
      "publication_resolution": {
        "trade_date_effective": "2026-03-09",
        "publication_version": 3,
        "is_current_publication": true
      }
    }

---

## 2. Historical correction evidence pack

### Purpose
Explain one controlled correction event and prove that prior published state was preserved while a new current publication was safely introduced.

### Minimum contents
A conforming correction evidence pack must include at minimum:
- correction request identity
- approval metadata
- target trade date D
- prior current publication reference
- new execution run reference
- old hash set
- new hash set
- before/after publication resolution
- supersession relation
- correction comparison summary
- final publication switch result

### Minimum questions it must answer
1. Why was correction requested?
2. Who approved it?
3. What was the prior current publication?
4. What new run executed the correction?
5. What changed?
6. What were the old hashes?
7. What are the new hashes?
8. Did publication switch?
9. Which publication is current now?
10. Is the prior publication still preserved for audit?

### Minimum example shape
    {
      "correction_id": 9001,
      "trade_date": "2026-03-05",
      "approval": {
        "approved_by": "ops_lead",
        "approved_at": "2026-03-06T09:00:00+07:00"
      },
      "prior_publication": {
        "run_id": 5001,
        "publication_version": 1,
        "is_current": false
      },
      "new_publication": {
        "run_id": 5009,
        "publication_version": 2,
        "is_current": true
      },
      "old_hashes": {
        "bars_batch_hash": "H1B",
        "indicators_batch_hash": "H1I",
        "eligibility_batch_hash": "H1E"
      },
      "new_hashes": {
        "bars_batch_hash": "H2B",
        "indicators_batch_hash": "H2I",
        "eligibility_batch_hash": "H2E"
      },
      "publication_switch": true
    }

---

## 3. Replay result evidence pack

### Purpose
Explain why actual replay output diverged from expected outcome and classify the mismatch in a way that is auditable and actionable.

### Minimum contents
A conforming replay result evidence pack must include at minimum:
- replay identity
- expected run summary/hash outcome
- actual run summary/hash outcome
- comparison result
- mismatch classification
- mismatch summary
- config identity
- artifact-changed scope
- publication/reseal implication if relevant

### Minimum questions it must answer
1. What replay case was executed?
2. What did the fixture expect?
3. What actually happened?
4. Which artifact layer diverged?
5. Did hashes diverge?
6. Did status/effective-date diverge?
7. Was config identity consistent?
8. Does the mismatch indicate canonical drift, indicator drift, eligibility drift, formatting drift, or publication drift?

### Minimum example shape
    {
      "replay_id": 3001,
      "trade_date": "2025-12-10",
      "trade_date_effective": "2025-12-10",
      "status": "SUCCESS",
      "comparison_result": "MISMATCH",
      "comparison_note": "eligibility output diverged",
      "artifact_changed_scope": "eligibility_only",
      "config_identity": "cfg_2025_12_v2",
      "publication_version": 1,
      "bars_batch_hash": "A1",
      "indicators_batch_hash": "B1",
      "eligibility_batch_hash": "C2",
      "expected_status": "SUCCESS",
      "expected_trade_date_effective": "2025-12-10",
      "expected_seal_state": "SEALED",
      "mismatch_summary": "eligibility hash changed while bars hash remained unchanged"
    }

---

## Evidence preservation rules (LOCKED)
1. Evidence packs must preserve enough detail to support diagnosis, not just human narrative.
2. Evidence packs must be internally consistent with authoritative persisted run/publication data.
3. A correction evidence pack must always preserve both old and new state.
4. A replay result evidence pack must always preserve both expected and actual state.
5. Evidence packs must not imply readability for an unsealed or non-current publication.

## Relationship to run artifacts
Evidence packs may reference operator-facing artifacts such as:
- `run_summary.json`
- `anomaly_report.md`
- `correction_evidence.json`
- `replay_result.json`

But the evidence contract is about semantic completeness, not just filenames.

## Cross-contract alignment
This contract must remain aligned with:
- `Run_Artifacts_Format_LOCKED.md`
- `Failure_Playbook_LOCKED.md`
- `../book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
- replay schema/contracts
- publication/readiness contracts

## Anti-ambiguity rule (LOCKED)
If an incident, correction, or replay mismatch cannot be explained by a coherent evidence pack, then the operational proof layer is incomplete and the case is not considered fully auditable.