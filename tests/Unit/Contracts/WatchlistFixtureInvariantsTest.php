<?php

namespace Tests\Unit\Contracts;

use Tests\TestCase;

/**
 * Light-weight invariants over the JSON fixtures themselves.
 *
 * Goal: catch accidental schema drift / inconsistent meta without needing a live DB.
 */
class WatchlistFixtureInvariantsTest extends TestCase
{
    public function testMetaCountsMatchGroupSizes(): void
    {
        $doc = $this->load('watchlist/sample_weekly_swing_buy1.json');

        $this->assertSame(
            $doc['meta']['counts']['top_picks'],
            count($doc['groups']['top_picks'] ?? []),
            'meta.counts.top_picks must match groups.top_picks size'
        );
        $this->assertSame(
            $doc['meta']['counts']['secondary'],
            count($doc['groups']['secondary'] ?? []),
            'meta.counts.secondary must match groups.secondary size'
        );
        $this->assertSame(
            $doc['meta']['counts']['watch_only'],
            count($doc['groups']['watch_only'] ?? []),
            'meta.counts.watch_only must match groups.watch_only size'
        );
    }

    public function testGroupRanksAreUniqueAndStartAtOne(): void
    {
        $doc = $this->load('watchlist/sample_weekly_swing_buy1.json');
        $top = $doc['groups']['top_picks'] ?? [];

        $ranks = array_map(fn($x) => $x['rank'] ?? null, $top);
        $ranks = array_values(array_filter($ranks, fn($x) => $x !== null));
        $this->assertNotEmpty($ranks);
        $this->assertSame(count($ranks), count(array_unique($ranks)), 'Ranks must be unique');
        $this->assertContains(1, $ranks, 'Ranks should start at 1');
    }

    public function testAllocationsReferenceExistingTickers(): void
    {
        $doc = $this->load('watchlist/sample_weekly_swing_buy1.json');

        $tickers = [];
        foreach (['top_picks', 'secondary', 'watch_only'] as $g) {
            foreach (($doc['groups'][$g] ?? []) as $c) {
                $tickers[(string)($c['ticker_code'] ?? '')] = true;
            }
        }

        foreach (($doc['recommendations']['allocations'] ?? []) as $a) {
            $code = (string)($a['ticker_code'] ?? '');
            $this->assertNotSame('', $code);
            $this->assertTrue(isset($tickers[$code]), "Allocation must reference a ticker present in groups: $code");
        }
    }

    public function testSessionTimesAreWellFormed(): void
    {
        $doc = $this->load('watchlist/sample_weekly_swing_buy1.json');
        $session = $doc['meta']['session'] ?? [];

        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', (string)($session['open_time'] ?? ''));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', (string)($session['close_time'] ?? ''));
        $this->assertIsArray($session['breaks'] ?? []);
    }

    private function load(string $rel): array
    {
        $path = base_path('tests/Fixtures/' . $rel);
        $this->assertFileExists($path);
        $json = json_decode(file_get_contents($path), true);
        $this->assertIsArray($json);
        return $json;
    }
}
