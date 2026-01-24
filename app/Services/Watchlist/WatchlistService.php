<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistPersistenceRepository;
use App\Trade\Watchlist\WatchlistEngine;

class WatchlistService
{
    private WatchlistEngine $engine;
    private WatchlistPersistenceRepository $persistRepo;

    public function __construct(WatchlistEngine $engine, WatchlistPersistenceRepository $persistRepo)
    {
        $this->engine = $engine;
        $this->persistRepo = $persistRepo;
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
            $source = 'preopen_contract' . ($pol !== '' ? '_' . $pol : '');
            if ($tradeDate !== '') {
                $dailyId = $this->persistRepo->saveDailySnapshot($tradeDate, $payload, $source);
                $this->persistRepo->saveCandidates($dailyId, $tradeDate, (array)($payload['groups'] ?? []));
            }
        } catch (\Throwable $e) {
            // ignore persistence errors
        }

        return $payload;
    }
}
