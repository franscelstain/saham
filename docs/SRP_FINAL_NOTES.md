# SRP Final Notes (Watchlist)

## What changed
- WatchlistService sekarang hanya **orchestrator**.
- Logic dipisah jadi 3 komponen SRP:
  - `WatchlistSelector` : hard filter + setup classification
  - `WatchlistPresenter` : membentuk output JSON untuk UI
  - `WatchlistSorter` : sorting final (rankScore, liquidity, rrTp2)

## Files removed (karena kosong & belum dipakai)
File-file ini sengaja dihapus supaya repo tidak berisi placeholder kosong. Nanti kalau fitur Portfolio/Ticker/Intraday/Exit benar-benar mulai dikerjakan, file bisa dibuat lagi sesuai sprint.

- app/DTO/Trade/EntryGuidance.php
- app/DTO/Trade/ExitDecision.php
- app/DTO/Trade/TradePlan.php
- app/DTO/Watchlist/RecommendedPick.php
- app/DTO/Watchlist/WatchlistItem.php
- app/Http/Controllers/PortfolioController.php
- app/Http/Controllers/TickerController.php
- app/Repositories/IntradayRepository.php
- app/Repositories/PortfolioRepository.php
- app/Services/Portfolio/ExitDecisionService.php
- app/Services/Portfolio/PortfolioService.php
- app/Services/Portfolio/PositionMonitor.php
- app/Trade/Exit/ExitRuleEngine.php
- app/Trade/Explain/GuidanceTextBuilder.php
- app/Trade/Filters/ExpiryFilter.php
- app/Trade/Planning/EntryGuidance.php
- app/Trade/Pricing/PriceMath.php
- app/Trade/Signals/SetupStrength.php
