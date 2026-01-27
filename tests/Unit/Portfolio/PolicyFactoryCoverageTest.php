<?php

namespace Tests\Unit\Portfolio;

use App\Repositories\MarketCalendarRepository;
use App\Trade\Portfolio\Policies\DividendSwingPolicy;
use App\Trade\Portfolio\Policies\IntradayLightPolicy;
use App\Trade\Portfolio\Policies\PolicyFactory;
use App\Trade\Portfolio\Policies\PositionTradePolicy;
use App\Trade\Portfolio\Policies\WeeklySwingPolicy;
use Tests\TestCase;

class PolicyFactoryCoverageTest extends TestCase
{
    public function testFactoryBuildsKnownPolicies(): void
    {
        $factory = new PolicyFactory(new MarketCalendarRepository(), config('trade.portfolio') ?? []);

        $p1 = $factory->make('WEEKLY_SWING');
        $this->assertInstanceOf(WeeklySwingPolicy::class, $p1);

        $p2 = $factory->make('DIVIDEND_SWING');
        $this->assertInstanceOf(DividendSwingPolicy::class, $p2);

        $p3 = $factory->make('INTRADAY_LIGHT');
        $this->assertInstanceOf(IntradayLightPolicy::class, $p3);

        $p4 = $factory->make('POSITION_TRADE');
        $this->assertInstanceOf(PositionTradePolicy::class, $p4);
    }

    public function testUnknownPolicyReturnsNull(): void
    {
        $factory = new PolicyFactory(new MarketCalendarRepository(), config('trade.portfolio') ?? []);
        $this->assertNull($factory->make('UNKNOWN_POLICY_CODE'));
    }
}
