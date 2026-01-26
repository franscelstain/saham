<?php

namespace Tests\Unit\Contracts;

use App\Trade\Watchlist\Contracts\WatchlistContractValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WatchlistContractTest extends TestCase
{
    private function loadFixture(string $name): array
    {
        $path = __DIR__ . '/../../Fixtures/watchlist/' . $name;
        $json = file_get_contents($path);
        $this->assertNotFalse($json, 'Failed to read fixture: ' . $path);

        $data = json_decode($json, true);
        $this->assertIsArray($data, 'Fixture JSON must decode to array: ' . $path);
        return $data;
    }

    public function testWeeklySwingFixturePassesContract(): void
    {
        $payload = $this->loadFixture('sample_weekly_swing_buy1.json');
        $v = new WatchlistContractValidator();
        $v->validate($payload);

        $this->assertSame('WEEKLY_SWING', $payload['policy']['selected']);
        $this->assertSame('BUY_1', $payload['recommendations']['mode']);
        $this->assertCount(1, $payload['recommendations']['allocations']);
    }

    public function testNoTradeFixturePassesContract(): void
    {
        $payload = $this->loadFixture('sample_no_trade.json');
        $v = new WatchlistContractValidator();
        $v->validate($payload);

        $this->assertSame('NO_TRADE', $payload['recommendations']['mode']);
        $this->assertCount(0, $payload['recommendations']['allocations']);
        $this->assertCount(0, $payload['groups']['top_picks']);
    }

    public function testMissingRequiredKeyFailsFast(): void
    {
        $payload = $this->loadFixture('sample_weekly_swing_buy1.json');
        unset($payload['generated_at']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing key: generated_at');

        (new WatchlistContractValidator())->validate($payload);
    }
}
