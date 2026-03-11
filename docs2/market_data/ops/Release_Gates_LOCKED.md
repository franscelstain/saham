# Release Gates (LOCKED)

A release that changes output-affecting behavior is not ready unless:
- contract tests pass
- golden fixtures still match or are version-bumped intentionally
- replay/data-quality checks are reviewed
- config registry changes are documented
- downstream safety invariants (effective date, seal, fallback) remain intact