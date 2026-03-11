# Golden Fixtures Spec

Defines immutable fixtures for deterministic contract tests and replay tests.

## Required fixture sets
- valid canonical bars
- invalid provider bars
- minimal market calendar sample
- ticker identity mapping sample
- indicator vectors with expected outputs
- expected eligibility snapshot
- expected serialized hash inputs and hash outputs
- requested-date fallback scenarios

## Fixture rules
- fixtures are immutable once published
- changes require new fixture version and explicit note
- fixture filenames should encode semantic version, not ad-hoc timestamps