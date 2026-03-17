# Weekly Swing Fixtures

Folder `fixtures/` berisi golden assets dan test inputs untuk replay, determinism, validation, dan cross-layer boundary.

Fixtures mendukung owner docs tetapi tidak menggantikan owner docs.

## Fixture Inventory

| File | Tujuan | Layer | Rule yang dibuktikan |
|---|---|---|---|
| `recommendation_empty_but_prioritized_groups_nonempty.json` | recommendation boleh kosong walau group prioritas ada | RECOMMENDATION | empty recommendation valid |
| `candidate_confirm_non_recommended.json` | non-recommended candidate tetap bisa confirm | CONFIRM | confirm eligibility dari candidate PLAN |
| `recommended_and_confirmed_pair.json` | recommended + confirmed coexist | CROSS-LAYER | confirm tidak ubah recommendation |
| `recommended_and_confirmed_pair_detailed.json` | strengthening semantics yang lebih lengkap | CROSS-LAYER | recommended + confirmed = strengthening signal |
| `recommendation_capital_free.json` | replay capital-free | RECOMMENDATION | determinism capital-free |
| `recommendation_capital_aware.json` | replay capital-aware | RECOMMENDATION | determinism capital-aware |
| `recommendation_capital_free_vs_capital_aware_pair.json` | membuktikan perubahan set karena capital mode, bukan confirm | RECOMMENDATION | capital mode affects recommendation, confirm does not |
| `recommendation_deterministic_ties.json` | tie-breaker recommendation | RECOMMENDATION | deterministic ranking |

## Usage Notes

- fixtures dipakai untuk test dan audit, bukan owner rule;
- fixture yang membuktikan edge case harus cukup kaya untuk menunjukkan rule inti, bukan sekadar placeholder;
- jika rule owner berubah, fixture yang relevan harus ikut diperbarui melalui change impact audit.
