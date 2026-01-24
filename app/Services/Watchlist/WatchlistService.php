<?php

namespace App\Services\Watchlist;

use App\Repositories\WatchlistRepository;
use App\Repositories\MarketCalendarRepository;
use App\Repositories\MarketBreadthRepository;
use App\Repositories\WatchlistPersistenceRepository;
use App\Repositories\DividendEventRepository;
use App\Repositories\IntradaySnapshotRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\TickerStatusRepository;
use App\Trade\Watchlist\WatchlistEngine;

class WatchlistService
{
    private WatchlistEngine $engine;
    private WatchlistPersistenceRepository $persistRepo;

    public function __construct(
        WatchlistRepository $watchRepo,
        MarketBreadthRepository $breadthRepo,
        MarketCalendarRepository $calRepo,
        WatchlistPersistenceRepository $persistRepo,
        DividendEventRepository $divRepo,
        IntradaySnapshotRepository $intraRepo,
        PortfolioPositionRepository $posRepo,
        TickerStatusRepository $statusRepo
    ) {
        $this->persistRepo = $persistRepo;

        // IMPORTANT: WatchlistEngine constructor order is strict (typed deps)
        // (watchRepo, breadthRepo, calRepo, divRepo, intraRepo, statusRepo, posRepo)
        $this->engine = new WatchlistEngine(
            $watchRepo,
            $breadthRepo,
            $calRepo,
            $divRepo,
            $intraRepo,
            $statusRepo,
            $posRepo
        );
    }

    public function preopenContract(): array
    {
        $policy = request()->query('policy');
        $capital = request()->query('capital_total', request()->query('capital'));
        $riskPct = request()->query('risk_per_trade_pct');

        $opts = [
            'policy' => $policy ? (string)$policy : null,
            'capital_total' => $capital !== null && $capital !== '' ? (float) preg_replace('/[^0-9.]/', '', (string)$capital) : null,
            'risk_per_trade_pct' => $riskPct !== null && $riskPct !== '' ? (float) preg_replace('/[^0-9.]/', '', (string)$riskPct) : null,
            'eod_date' => request()->query('trade_date') ? (string) request()->query('trade_date') : null,
        ];

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
