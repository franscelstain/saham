# 06 — WS Test Implementation Guidance

## Purpose

Dokumen ini menerjemahkan baseline contract menjadi target testing implementasi aplikasi watchlist.

## Mandatory Test Layers

### A. Unit / Service Tests
- `WsPlanEngine` deterministic untuk input yang sama
- `WsRecommendationEngine` deterministic untuk `PLAN` yang sama
- `WsConfirmOverlayEngine` tidak memutasi recommendation

### B. Contract Tests
- payload `PLAN` sesuai `03`
- payload `RECOMMENDATION` sesuai `23`
- payload `CONFIRM` sesuai `10`

### C. Cross-Layer Tests
- recommendation tersedia tanpa confirm
- recommendation kosong tetap valid
- non-recommended candidate dapat di-confirm
- recommended + confirmed coexist tanpa mutation

### D. Manual Input Tests
- payload manual valid diterima
- payload manual invalid ditolak
- ticker di luar candidate `PLAN` ditolak

## Minimal Named Test Intent

1. `plan_build_is_deterministic`
2. `recommendation_build_is_plan_only`
3. `recommendation_can_be_empty`
4. `confirm_requires_plan_candidate`
5. `confirm_can_accept_non_recommended_candidate`
6. `confirm_does_not_mutate_recommendation`
7. `composite_view_preserves_source_semantics`
