# Failure Playbook

- provider rate limit/timeout => retry/backoff, then HELD/FAILED
- coverage drop => HELD, fallback effective date
- mass invalid => HELD/FAILED
- indicators missing => FAILED