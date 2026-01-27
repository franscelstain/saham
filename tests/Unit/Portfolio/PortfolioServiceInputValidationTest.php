<?php

namespace Tests\Unit\Portfolio;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\MarketData\CanonicalEodRepository;
use App\Repositories\MarketData\RunRepository;
use App\Repositories\PortfolioLotMatchRepository;
use App\Repositories\PortfolioLotRepository;
use App\Repositories\PortfolioPlanRepository;
use App\Repositories\PortfolioPositionEventRepository;
use App\Repositories\PortfolioPositionRepository;
use App\Repositories\PortfolioTradeRepository;
use App\Repositories\TickerRepository;
use App\Services\Portfolio\PortfolioService;
use App\Trade\Planning\PlanningPolicy;
use App\Trade\Portfolio\Policies\PolicyFactory;
use App\Trade\Pricing\FeeConfig;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortfolioServiceInputValidationTest extends TestCase
{
    public function testIngestTradeRejectsInvalidSideBeforeTouchingDb(): void
    {
        $svc = $this->buildPortfolioService();

        DB::enableQueryLog();

        $r = $svc->ingestTrade([
            'account_id' => 1,
            'ticker_id' => 1,
            'trade_date' => '2026-01-27',
            'side' => 'HOLD',
            'qty' => 100,
            'price' => 1000,
            'currency' => 'IDR',
            'external_ref' => 'X1',
        ]);

        $this->assertIsArray($r);
        $this->assertFalse($r['ok'] ?? true);

        // Critical: invalid payload must be rejected before hitting DB.
        $this->assertCount(0, DB::getQueryLog(), 'Expected no DB queries for invalid side');
    }

    public function testIngestTradeRejectsNonPositiveQtyAndPrice(): void
    {
        $svc = $this->buildPortfolioService();

        DB::enableQueryLog();

        $r = $svc->ingestTrade([
            'account_id' => 1,
            'ticker_id' => 1,
            'trade_date' => '2026-01-27',
            'side' => 'BUY',
            'qty' => 0,
            'price' => -1,
            'currency' => 'IDR',
            'external_ref' => 'X2',
        ]);

        $this->assertIsArray($r);
        $this->assertFalse($r['ok'] ?? true);
        $this->assertCount(0, DB::getQueryLog(), 'Expected no DB queries for invalid qty/price');
    }

    private function buildPortfolioService(): PortfolioService
    {
        $tradeRepo = new PortfolioTradeRepository();
        $lotRepo = new PortfolioLotRepository();
        $matchRepo = new PortfolioLotMatchRepository();
        $posRepo = new PortfolioPositionRepository();
        $eventRepo = new PortfolioPositionEventRepository();
        $planRepo = new PortfolioPlanRepository();
        $tickerRepo = new TickerRepository();
        $runRepo = new RunRepository();
        $canonRepo = new CanonicalEodRepository();
        $calRepo = new MarketCalendarRepository();

        $fee = new FeeConfig(0.0, 0.0, 0.0, 0.0, 0.0);

        $policyFactory = new PolicyFactory($calRepo, (array) (config('trade.portfolio.policies') ?? []));

        $pcfg = (array) (config('trade.planning') ?? []);
        // PlanningPolicy signature (TradeAxis5.11): no tp_mode; tp2 uses r-mult config.
        $planning = new PlanningPolicy(
            (string) ($pcfg['entry_mode'] ?? 'BREAKOUT'),
            (int) ($pcfg['entry_buffer_ticks'] ?? 0),
            (string) ($pcfg['sl_mode'] ?? 'ATR'),
            (float) ($pcfg['sl_pct'] ?? 0.0),
            (float) ($pcfg['sl_atr_mult'] ?? 2.0),
            (float) ($pcfg['tp1_r'] ?? 1.0),
            (float) ($pcfg['tp2_r_mult'] ?? 2.0),
            (float) ($pcfg['min_rr_tp2'] ?? 1.0),
            (float) ($pcfg['break_even_at_r'] ?? 1.0)
        );

        return new PortfolioService(
            $tradeRepo,
            $lotRepo,
            $matchRepo,
            $posRepo,
            $eventRepo,
            $planRepo,
            $tickerRepo,
            $runRepo,
            $canonRepo,
            $calRepo,
            $fee,
            $policyFactory,
            $planning
        );
    }
}
