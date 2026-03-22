# System Data Product Map

## Purpose
Dokumen ini memetakan produk data utama yang dihasilkan platform. Ia tidak menggantikan contract detail.

## Product-class rule
Setiap produk data pada peta ini harus dibaca dalam salah satu kelas berikut:
- `consumer-facing` = boleh dipakai downstream consumer sebagai intake upstream yang sah bila owner contract mengizinkan
- `internal-only` = tidak boleh dipakai downstream consumer sebagai kontrak intake
- `implementation-support only` = artefak teknis/operasional yang membantu implementasi atau audit, tetapi bukan intake contract downstream

Kelas ini tidak menggantikan owner contract. Kelas ini hanya memperjelas apakah downstream consumer seperti `watchlist` boleh membaca artefak tersebut sebagai jalur intake.

## Quick intake matrix

| Product family | Owner area | Consumer-facing? | Publication-aware intake? | Internal-only? | Watchlist allowed as direct intake? |
|---|---|---:|---:|---:|---:|
| Canonical EOD bars | `market_data` | No, unless an owner contract explicitly exposes them | No | Yes | No |
| Deterministic EOD indicators | `market_data` | No, unless an owner contract explicitly exposes them | No | Yes | No |
| Eligibility and readability artifacts | `market_data` | Yes | Yes | No | Yes |
| Publication and current-pointer semantics | `market_data` | Yes, only where owner contract defines consumer-readable publication semantics | Yes | No for semantics, but technical switch procedures remain implementation-support only | Yes, for semantics only |
| Correction / replay / reseal artifacts | `market_data` | No | No | No | No |
| Session snapshot artifacts | `market_data` | No, unless a downstream-facing contract later states otherwise | No | Yes | No |

## Main products
### Canonical EOD bars
Classification: `internal-only` unless explicitly exposed through a downstream consumer-facing contract.

Owner pointers:
- `book/EOD_Bars_Contract.md`
- `book/Canonicalization_Contract_EOD_Bars.md`
- `book/Canonical_Row_History_and_Versioning_Policy_LOCKED.md`

### Deterministic EOD indicators
Classification: `internal-only` unless explicitly exposed through a downstream consumer-facing contract.

Owner pointers:
- `book/EOD_Indicators_Contract.md`
- `indicators/EOD_Indicators_Formula_Spec.md`
- `indicators/Indicator_Computation_Specification.md`
- `registry/Indicator_Registry_Baseline_LOCKED.md`

### Eligibility and readability artifacts
Classification: `consumer-facing`.

Owner pointers:
- `book/EOD_Eligibility_Snapshot_Contract_LOCKED.md`
- `book/Eligibility_Partial_Data_Behavior_LOCKED.md`
- `book/Downstream_Data_Readiness_Guarantee_LOCKED.md`
- `book/Downstream_Consumer_Read_Model_Contract_LOCKED.md`

Downstream consumers should anchor intake here first.

### Publication and current-pointer artifacts
Classification: `consumer-facing` only where the owner contract defines publication-aware readability; otherwise implementation must treat technical switching procedures as `implementation-support only`.

Owner pointers:
- `book/Publication_Manifest_Contract_LOCKED.md`
- `book/Publication_Current_Pointer_Integrity_Contract_LOCKED.md`
- `db/EOD_Publications_Table.sql`
- `db/EOD_Current_Publication_Pointer_Table.sql`
- `db/Publication_Switch_Procedure_LOCKED.sql`
- `db/Publication_Current_Pointer_Switch_Procedure_LOCKED.sql`

### Correction, replay, and reseal artifacts
Classification: `implementation-support only`.

Owner pointers:
- `book/Historical_Correction_and_Reseal_Contract_LOCKED.md`
- `book/Dataset_Seal_and_Freeze_Contract_LOCKED.md`
- `book/Audit_Hash_and_Reproducibility_Contract_LOCKED.md`
- `backtest/Historical_Replay_and_Data_Quality_Backtest.md`
- `backtest/Replay_Results_Schema_MariaDB.sql`

### Optional supplemental session snapshots
Classification: `internal-only` unless a downstream-facing contract later states otherwise.

Owner pointers:
- `session_snapshot/Session_Snapshot_Contract_LOCKED.md`
- `session_snapshot/Session_Snapshot_Scope_Selection_and_Dependencies_LOCKED.md`
- `session_snapshot/Session_Snapshot_Date_Alignment_with_Effective_Date_LOCKED.md`

## Downstream intake note
For the active scope, `watchlist` must not treat `internal-only` or `implementation-support only` products as a replacement for the consumer-facing intake path.

## Readability rule
System map ini hanya menunjukkan produk utama dan pointer owner-nya. Behavior rinci tetap harus dibaca dari file owner yang dirujuk.
