# System Read Order

## Standard read order
1. `system/README.md`
2. `system/SYSTEM_OVERVIEW.md`
3. `system/SYSTEM_BOUNDARY.md`
4. `audit/README.md`
5. `audit/AUDIT_BASELINE.md`
6. `audit/AUDIT_LAYER_CLASSIFICATION_RULES.md`
7. core `book/` contracts
8. `db/`
9. `ops/`
10. `tests/`
11. `examples/` and `evidence/` as needed

## Read path by audit layer
### For Layer A
Focus on `system/`, `book/`, and `db/` first.

### For Layer B
Focus on `ops/`, guidance-oriented docs, and testing guidance after core contracts.

### For Layer C
Focus on actual runtime-related artifacts, evidence, executed results, and any real implementation artifacts after contracts are understood.
