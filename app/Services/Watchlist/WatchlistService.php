<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistPersistenceRepository;
use App\Trade\Watchlist\WatchlistEngine;
use App\Services\Watchlist\WatchlistScorecardService;
use App\Trade\Watchlist\Config\ScorecardConfig;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;

class WatchlistService
{
    private WatchlistEngine $engine;
    private WatchlistPersistenceRepository $persistRepo;
    private ?WatchlistScorecardService $scorecard;
    private ?ScorecardConfig $scorecardCfg;

    public function __construct(WatchlistEngine $engine, WatchlistPersistenceRepository $persistRepo, ?WatchlistScorecardService $scorecard = null, ?ScorecardConfig $scorecardCfg = null)
    {
        $this->engine = $engine;
        $this->persistRepo = $persistRepo;
        $this->scorecard = $scorecard;
        $this->scorecardCfg = $scorecardCfg;
    }

    /**
     * Build watchlist contract payload.
     *
     * HTTP layer (controller) is responsible for parsing request() and passing options here.
     * Console callers can pass an empty array.
     *
     * @param array{
     *   eod_date?:string|null,
     *   policy?:string|null,
     *   capital_idr?:int|float|string|null,
     *   risk_per_trade_pct?:int|float|string|null,
     *   now_ts?:string|null
     * } $opts
     */
    public function preopenContract(array $opts = []): array
    {
        $both = $this->engine->buildBoth($opts);
        $payload = $both['contract'];

        // Persist snapshot (docs/watchlist: audit & replay). Fail-soft if DB isn't ready.
        try {
	            // Strict preopen contract (docs/watchlist/preopen.md)
	            $tradeDate = (string)($payload['meta']['trade_date'] ?? '');
	            $pol = (string)($payload['meta']['policy'] ?? ($opts['policy'] ?? ''));
            // Keep source label stable for CLI tooling (watchlist:scorecard:* defaults).
            $source = 'preopen_contract' . ($pol !== '' ? '_' . strtolower($pol) : '');
            if ($tradeDate !== '') {
	                $dailyId = $this->persistRepo->saveDailySnapshot($tradeDate, $payload, $source);
	                $meta = (array)($payload['meta'] ?? []);
	                $groups = (array)($payload['groups'] ?? []);
	                $this->persistRepo->saveCandidatesFromContract($dailyId, $meta, $groups);
            }
        } catch (\Throwable $e) {
            // ignore persistence errors
        }

        return $payload;
    }
}
