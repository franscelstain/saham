# 02 — WS Module Mapping

## Purpose

Dokumen ini memetakan baseline Weekly Swing ke modul implementasi aplikasi watchlist.

## Recommended Module Set

### Shared
- `PolicyResolver`
- `RuntimeArtifactRepository`
- `ReasonCodeResolver`
- `ManualInputValidator`
- `TradeDateResolver`

### PLAN
- `WsPlanInputProvider`
- `WsPlanEngine`
- `WsPlanAssembler`
- `WsPlanSerializer`

### RECOMMENDATION
- `WsRecommendationEngine`
- `WsRecommendationAssembler`
- `WsRecommendationSerializer`

### CONFIRM
- `WsConfirmBinder`
- `WsConfirmOverlayEngine`
- `WsConfirmAssembler`
- `WsConfirmSerializer`

### Consumer / Delivery
- `WsWatchlistReadService`
- `WsWatchlistApiPresenter`
- `WsWatchlistCompositeViewBuilder`

## Mapping to Source of Truth

- `01–10`, `22–25` = owner behavior
- `03` = owner runtime shape
- `13` = owner contract acceptance
- `21` = owner implementation order

Module implementasi harus dapat ditelusuri balik ke owner doc yang relevan.

## Forbidden Coupling

- modul confirm membaca recommendation untuk eligibility candidate
- modul recommendation membaca confirm
- modul watchlist langsung membaca market-data internals di luar input contract yang sudah tersedia
- modul watchlist menulis holdings/portfolio state
