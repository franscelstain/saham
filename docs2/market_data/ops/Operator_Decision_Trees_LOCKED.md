# Operator Decision Trees (LOCKED)

## Purpose
Provide simple deterministic decision trees for high-impact operational cases.

## Decision Tree A — Source schema drift

1. Did payload shape/required field contract change unexpectedly?
   - If yes -> stop trusting best-effort normalization
2. Is readable publication for requested date already committed?
   - If no -> block requested-date publication
3. Does prior readable current publication exist?
   - If yes -> preserve prior publication and hold/fail requested date
4. Escalate immediately.

## Decision Tree B — Hash failure

1. Are required artifacts complete?
   - If no -> fix upstream artifact completeness first
2. Is serialization contract input coherent?
   - If no -> block publication
3. Do hashes remain missing?
   - If yes -> requested date is not readable
4. Preserve prior current publication.
5. Escalate if reproducibility drift is suspected.

## Decision Tree C — Seal write failure

1. Are hashes present?
   - If no -> seal cannot proceed
2. Is candidate publication otherwise coherent?
   - If no -> block publication
3. Did any current publication switch already happen?
   - If yes -> restore safe single-current state immediately
4. Keep candidate non-readable until seal succeeds safely.

## Decision Tree D — Publication ambiguity

1. Is more than one publication current for the same trade date?
   - If yes -> critical incident
2. Can safe previous current publication be restored deterministically?
   - If yes -> restore it first
3. If not -> freeze readable resolution for that date until corrected
4. Escalate immediately.

## Decision Tree E — Correction reseal failure

1. Did candidate correction content change?
   - If no -> treat as audit rerun, not correction publication
2. If yes, did reseal succeed?
   - If no -> no publication switch
3. Preserve prior current publication
4. Keep candidate correction non-current
5. Preserve correction evidence and escalate if needed.

## Decision Tree F — Replay mismatch

1. Is mismatch on identical controlled inputs?
   - If yes -> treat as serious determinism concern
2. Which artifact scope changed?
   - bars / indicators / eligibility / multi-artifact
3. Is config identity unchanged?
   - If yes -> suspect logic/serialization drift
4. Do not ignore mismatch without diagnosis.

## Cross-contract alignment
This file must remain aligned with:
- `Failure_Playbook_LOCKED.md`
- `Incident_Classification_and_Response_Matrix_LOCKED.md`
- `Historical_Correction_Runbook_LOCKED.md`

## Anti-ambiguity rule (LOCKED)
If operators still need ad hoc interpretation for the incidents covered here, the ops decision layer is not yet 10/10.