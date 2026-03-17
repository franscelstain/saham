# Recommendation Runtime Schema

## Purpose

Dokumen ini menjelaskan schema logis minimal untuk artifact persistence recommendation jika sistem memilih menyimpan hasil recommendation harian.

Dokumen ini bukan owner business rule recommendation. Owner rule tetap berada pada dokumen contract dan algorithm recommendation.

## Logical Fields

| Field | Type | Nullable | Notes |
|---|---|---:|---|
| `trade_date` | date | no | trade date artifact |
| `ticker` | varchar | no | berasal dari PLAN item |
| `plan_rank` | int | no | copied from PLAN for traceability |
| `plan_group_semantic` | varchar | no | copied from PLAN for traceability |
| `recommendation_score` | decimal | no | score recommendation |
| `recommendation_rank` | int | no | ranking recommendation |
| `recommended_flag` | boolean | no | final selected flag |
| `recommendation_label` | varchar | no | selected / borderline / not_selected |
| `capital_mode` | varchar | no | `CAPITAL_FREE` / `CAPITAL_AWARE` |
| `input_capital` | decimal | yes | optional capital context |
| `suggested_lots` | int | yes | optional capital-aware field |
| `estimated_cost` | decimal | yes | optional allocation estimate |
| `recommendation_hash` | varchar | yes | optional hash |
| `created_at` | timestamp | no | artifact creation time |

## Suggested Keys

Suggested natural uniqueness for persistence artifact:
- (`trade_date`, `ticker`, `capital_mode`, `policy_code`, `param_set_id`) if policy/schema context is stored alongside the referenced paramset row; or
- include `policy_code`, `policy_version`, `schema_version`, and `param_set_id` directly in the artifact table if persistence needs stronger replay traceability.

## Boundary Rules

1. recommendation persistence berasal dari PLAN immutable;
2. recommendation persistence tidak dibentuk dari confirm;
3. confirm artifact harus tetap terpisah dari recommendation artifact;
4. empty recommendation set tetap valid secara artifact-level jika policy persistence mengizinkan penyimpanan summary tanpa selected rows.


## Terminology Guard

- `param_set_id` = reference ke instance paramset aktif yang dipakai saat recommendation dibentuk.
- `policy_version` = versi rule Weekly Swing yang berlaku untuk artifact tersebut.
- `schema_version` = versi schema paramset yang lolos validator.
- `paramset_version` tidak dipakai lagi karena ambigu dan tidak punya satu makna canonical.
