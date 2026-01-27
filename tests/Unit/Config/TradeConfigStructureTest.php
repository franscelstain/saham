<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class TradeConfigStructureTest extends TestCase
{
    public function testTradeConfigHasRequiredTopLevelKeys(): void
    {
        $cfg = config('trade');
        $this->assertIsArray($cfg);

        // Keep aligned with config/trade.php in this codebase.
        foreach (['pricing', 'watchlist', 'portfolio', 'fees', 'planning'] as $k) {
            $this->assertArrayHasKey($k, $cfg, "config('trade') missing key: {$k}");
            $this->assertIsArray($cfg[$k], "config('trade.{$k}') must be array");
        }
    }

    public function testWatchlistAndScorecardConfigHaveExpectedKeysAndTypes(): void
    {
        $watch = config('trade.watchlist');
        $this->assertIsArray($watch);

        foreach (['policy_default', 'supported_policies', 'session_default', 'eod_cutoff_time'] as $k) {
            $this->assertArrayHasKey($k, $watch, "config('trade.watchlist') missing: {$k}");
        }

        $this->assertIsString($watch['policy_default']);
        $this->assertIsArray($watch['supported_policies']);
        $this->assertIsArray($watch['session_default']);
        $this->assertIsString($watch['eod_cutoff_time']);

        // scorecard config is a sibling of watchlist in this codebase
        $score = config('trade.watchlist.scorecard');
        $this->assertIsArray($score);

        foreach (['include_watch_only', 'session_open_time_default', 'session_close_time_default'] as $k) {
            $this->assertArrayHasKey($k, $score, "config('trade.watchlist.scorecard') missing: {$k}");
        }
        $this->assertIsBool($score['include_watch_only']);
        $this->assertIsString($score['session_open_time_default']);
        $this->assertIsString($score['session_close_time_default']);

        if (array_key_exists('breaks_json_default', $score)) {
            $this->assertIsArray($score['breaks_json_default']);
        }
    }

    public function testPricingConfigHasTickLadder(): void
    {
        $pricing = config('trade.pricing');
        $this->assertIsArray($pricing);
        $this->assertArrayHasKey('idx_ticks', $pricing);

        $ticks = $pricing['idx_ticks'];
        $this->assertIsArray($ticks);
        $this->assertNotEmpty($ticks);

        foreach ($ticks as $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('tick', $row);
            $this->assertIsNumeric($row['tick']);

            // Each row can be keyed by lt (numeric or null for final catch-all)
            $this->assertArrayHasKey('lt', $row);
            if ($row['lt'] !== null) $this->assertIsNumeric($row['lt']);
        }
    }

    public function testFeesAndPortfolioConfigHaveMinimumKeys(): void
    {
        $fees = config('trade.fees');
        $this->assertIsArray($fees);

        foreach (['buy_rate', 'sell_rate', 'extra_buy_rate', 'extra_sell_rate', 'slippage_rate'] as $k) {
            $this->assertArrayHasKey($k, $fees, "config('trade.fees') missing: {$k}");
            $this->assertIsNumeric($fees[$k]);
        }

        $portfolio = config('trade.portfolio');
        $this->assertIsArray($portfolio);

        foreach (['matching_method', 'reject_short_sell', 'max_open_positions'] as $k) {
            $this->assertArrayHasKey($k, $portfolio, "config('trade.portfolio') missing: {$k}");
        }
        $this->assertIsString($portfolio['matching_method']);
        $this->assertIsBool($portfolio['reject_short_sell']);
        $this->assertIsInt($portfolio['max_open_positions']);
    }
}
