# System Artifact Map

## Purpose
Map setiap kategori artifact market-data ke folder sumber, status authority, fungsi operasional, dan cara artifact itu dibaca dalam audit.

Dokumen ini bersifat orientasi sistem.
Ia tidak menggantikan owner contracts di `book/`, `db/`, `registry/`, `indicators/`, `session_snapshot/`, `ops/`, `tests/`, atau `backtest/`.

## Artifact map

| Artifact category | Primary folder(s) | Authority status | Typical contents | Used for | Audit reading rule |
|---|---|---|---|---|---|
| System overview artifacts | `system/` | summary only | system overview, boundary, runtime flow, read order | orientasi repo dan entry-point pembaca | dipakai untuk orientasi; tidak boleh mengubah owner behavior |
| Domain contract artifacts | `book/` | normative core | terminology, scope, invariants, publication contracts, correction contracts, readiness guarantees | definisi behavior inti market-data | bila ada konflik, kontrak owner/LOCKED di sini menang |
| Schema and persistence contract artifacts | `db/` | normative support / enforcement | schema contracts, DDL, migration policy, publication switch procedures | menjelaskan storage, constraints, switching, enforcement | dibaca bersama `book/`; tidak boleh menciptakan behavior yang bertentangan |
| Registry artifacts | `registry/` | normative constrained baseline | registry baselines, platform config registries, allowed values | menjaga daftar nilai/platform config yang constrained dan traceable | dinilai sebagai bagian baseline, bukan sekadar appendix |
| Indicator specification artifacts | `indicators/` | normative deterministic spec | indicator formulas, computation specification | memastikan hasil indikator deterministik dan stabil | bila indikator memengaruhi output data, folder ini punya bobot owner/support yang kuat |
| Session snapshot artifacts | `session_snapshot/` | feature-scoped normative support | snapshot contracts, dataset snapshot support, point-in-time capture rules | reproducibility, point-in-time behavior, snapshot semantics | dinilai hanya bila fitur snapshot aktif, tetapi tetap bagian baseline repo |
| Operational artifacts | `ops/` | normative support and operational contract support | runbooks, execution gates, locking rules, logging schema, recovery rules | menjalankan pipeline, correction, reseal, replay, publish | boleh memperinci operasi, tidak boleh menciptakan behavior domain baru |
| Test specification artifacts | `tests/` | proof-critical baseline | contract test spec, test matrix, fixture catalog, coverage closure | membuktikan contract benar-benar diuji | audit memeriksa trace contract -> test ID -> fixture -> executed evidence |
| Replay/backtest proof artifacts | `backtest/` | proof-critical support | replay rules, replay schemas, metrics and acceptance criteria | memvalidasi replay/data-quality/correction behavior | tidak otomatis berarti domain watchlist; dibaca sesuai fungsi proof market-data |
| Illustrative example artifacts | `examples/` | illustrative only | example manifests, example evidence shapes, worked examples | membantu implementer/reviewer memahami bentuk artifact | tidak boleh dijadikan source-of-truth atau bukti executed runtime |
| Archived runtime evidence artifacts | `evidence/` | archived actual proof if genuine | executed run bundles, replay results, correction evidence, executed test results | menunjukkan bahwa run/replay/correction/test tertentu pernah benar-benar terjadi | hanya dihitung sebagai Layer C bila traceable, actual, dan bukan placeholder |
| Audit control artifacts | `audit/` | evaluation tooling | baseline audit rules, checklists, templates, reports | mengaudit paket dan mencegah drift pembacaan | menilai artifact lain; bukan owner behavior market-data |

## Authority reminders
1. `LOCKED` owner contracts remain the highest behavioral authority.
2. `system/` helps readers enter the repo but never overrides owner docs.
3. `examples/` may illustrate a valid shape without proving that the shape was executed in reality.
4. `evidence/` may support Layer C only when execution identity, timestamps, and traceability are strong enough.
5. `ops/`, `tests/`, and `backtest/` can be proof-critical without becoming a parallel owner for semantics already locked elsewhere.

## Layer reading notes
- **Layer A focus:** `system/`, `book/`, `db/`, `registry/`, `indicators/`, `session_snapshot/`.
- **Layer B focus:** `ops/`, implementation-facing parts of `tests/`, supporting guidance across schema and publication flow.
- **Layer C focus:** `evidence/`, executed outputs referenced by `ops/` and `tests/`, plus any real runtime payload/log surface that can be traced back to owner contracts.

## Practical audit route
1. Start from `docs/market_data/README.md` and the locked boundary/terminology anchors.
2. Use `system/` to understand the repo map and artifact roles.
3. Read owner contracts before reading examples or archived evidence.
4. Use `tests/` and `backtest/` to verify proof expectations.
5. Use `evidence/` only after the expected contract and test IDs are already known.


## Evidence reading note
Archived evidence counts in audit only when it is genuinely traceable to actual execution, uses historically valid timestamps, and carries bundle admission metadata.
