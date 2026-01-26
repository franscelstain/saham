<?php

namespace Tests\Unit\Regression;

use PHPUnit\Framework\TestCase;

class WatchlistLotSizingRegressionTest extends TestCase
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

    public function testAllocationEstimatedCostMatchesFeeAndSlippageFormula(): void
    {
        $payload = $this->loadFixture('sample_weekly_swing_buy1.json');

        $capital = (int) $payload['recommendations']['capital_total'];
        $allocs = $payload['recommendations']['allocations'];
        $this->assertCount(1, $allocs);

        // Must match config/trade.php defaults
        $buyFeePct = 0.0015;
        $slippagePct = 0.0005;
        $lotSize = 100;

        $remaining = $capital;
        foreach ($allocs as $a) {
            $entry = (int) $a['entry_price_ref'];
            $lots  = (int) $a['lots_recommended'];
            $shares = $lots * $lotSize;

            $rawCost = $entry * $shares;
            $buyFee = (int) ceil($rawCost * $buyFeePct);
            $slip   = (int) ceil($rawCost * $slippagePct);
            $expected = (int) ($rawCost + $buyFee + $slip);

            $this->assertSame($expected, (int) $a['estimated_cost']);

            $remaining = max(0, $remaining - $expected);
            $this->assertSame($remaining, (int) $a['remaining_cash']);
        }

        // Candidate sizing should mirror allocation sizing for BUY_1.
        $c = $payload['groups']['top_picks'][0];
        $this->assertSame($allocs[0]['ticker_code'], $c['ticker_code']);
        $this->assertSame($allocs[0]['entry_price_ref'], $c['levels']['entry_trigger_price']);
        $this->assertSame($allocs[0]['lots_recommended'], $c['sizing']['lots_recommended']);
        $this->assertSame($allocs[0]['estimated_cost'], $c['sizing']['estimated_cost']);
        $this->assertSame($allocs[0]['remaining_cash'], $c['sizing']['remaining_cash']);
    }
}
