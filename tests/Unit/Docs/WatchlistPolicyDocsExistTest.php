<?php

namespace Tests\Unit\Docs;

use Tests\TestCase;

final class WatchlistPolicyDocsExistTest extends TestCase
{
    public function testPolicyDocFilesExistForAllKnownPolicies(): void
    {
        // Keep in sync with TradeWatchlistServiceProvider.
        $map = [
            'WEEKLY_SWING' => 'weekly_swing.md',
            'DIVIDEND_SWING' => 'dividend_swing.md',
            'INTRADAY_LIGHT' => 'intraday_light.md',
            'POSITION_TRADE' => 'position_trade.md',
            'NO_TRADE' => 'no_trade.md',
        ];

        $base = base_path('docs/watchlist/policy');
        foreach ($map as $policy => $file) {
            $path = $base . DIRECTORY_SEPARATOR . $file;
            $this->assertFileExists($path, "Missing policy doc for {$policy}: {$path}");
        }
    }
}
