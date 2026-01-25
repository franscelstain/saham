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
     *   capital_total?:int|float|string|null,
     *   risk_per_trade_pct?:int|float|string|null,
     *   now_ts?:string|null
     * } $opts
     */
    public function preopenContract(array $opts = []): array
    {
        $payload = $this->engine->build($opts);

        // Persist snapshot (docs/watchlist: audit & replay). Fail-soft if DB isn't ready.
        try {
            $tradeDate = (string)($payload['trade_date'] ?? '');
            $pol = (string)($payload['policy']['selected'] ?? '');
            // Keep source label stable for CLI tooling (watchlist:scorecard:* defaults).
            $source = 'preopen_contract' . ($pol !== '' ? '_' . strtolower($pol) : '');
            if ($tradeDate !== '') {
                $dailyId = $this->persistRepo->saveDailySnapshot($tradeDate, $payload, $source);
                $this->persistRepo->saveCandidates($dailyId, $tradeDate, (array)($payload['groups'] ?? []));

                // Also persist as a scorecard "strategy run" (plan). Fail-soft.
                if ($this->scorecard) {
                    if ($this->scorecardCfg) {
                        $dto = StrategyRunDto::fromPayloadArray($payload, 0, $this->scorecardCfg);
                        $this->scorecard->saveStrategyRunDto($dto, $source);
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore persistence errors
        }

        return $payload;
    }
}
