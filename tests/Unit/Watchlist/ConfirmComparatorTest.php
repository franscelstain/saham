<?php

namespace Tests\Unit\Watchlist;

use PHPUnit\Framework\TestCase;
use App\Trade\Watchlist\Confirm\ConfirmComparator;

class ConfirmComparatorTest extends TestCase
{
    public function test_confirm_pending_when_snapshot_missing(): void
    {
        $cmp = new ConfirmComparator();

        $planPayload = [
            'groups' => [
                'top_picks' => [[
                    'ticker_code' => 'BBCA',
                    'rank' => 1,
                    'watchlist_score' => 88.5,
                    'levels' => [
                        'entry_trigger_price' => 10000,
                        'close_price' => 9900,
                    ],
                    'plan' => ['is_tradeable' => true, 'is_eligible_new_entry' => true, 'eligibility_block_codes' => []],
                ]],
                'secondary' => [],
                'watch_only' => [],
            ],
        ];

        $snapshots = []; // missing
        $cfg = [
            'enabled' => true,
            'guards' => [
                'WEEKLY_SWING' => ['max_gap_up_pct' => 0.03, 'max_chase_from_close_pct' => 0.02, 'max_spread_pct' => 0.015],
            ],
        ];

        $res = $cmp->build('WEEKLY_SWING', $planPayload, $snapshots, $cfg);

        $this->assertTrue($res['enabled']);
        $this->assertSame(1, $res['summary']['evaluated']);
        $this->assertSame('PENDING', $res['per_ticker']['BBCA']['confirm']['status']);
    }

    public function test_confirm_fail_on_wide_spread_and_gap_up(): void
    {
        $cmp = new ConfirmComparator();

        $planPayload = [
            'groups' => [
                'top_picks' => [[
                    'ticker_code' => 'BBRI',
                    'rank' => 1,
                    'watchlist_score' => 90.0,
                    'levels' => [
                        'entry_trigger_price' => 5000,
                        'close_price' => 4800,
                    ],
                ]],
                'secondary' => [],
                'watch_only' => [],
            ],
        ];

        $snapshots = [
            'BBRI' => ['open_or_last_exec' => 5200, 'spread_pct' => 0.02, 'source' => 'test'],
        ];

        $cfg = [
            'enabled' => true,
            'guards' => [
                'WEEKLY_SWING' => ['max_gap_up_pct' => 0.03, 'max_chase_from_close_pct' => 0.02, 'max_spread_pct' => 0.015],
            ],
        ];

        $res = $cmp->build('WEEKLY_SWING', $planPayload, $snapshots, $cfg);

        $this->assertSame('FAIL', $res['per_ticker']['BBRI']['confirm']['status']);
        $this->assertGreaterThan(0, $res['summary']['failed']);
    }

    public function test_confirm_pass_when_within_guards(): void
    {
        $cmp = new ConfirmComparator();

        $planPayload = [
            'groups' => [
                'top_picks' => [[
                    'ticker_code' => 'ANTM',
                    'rank' => 1,
                    'watchlist_score' => 85.0,
                    'levels' => [
                        'entry_trigger_price' => 2000,
                        'close_price' => 1980,
                    ],
                ]],
                'secondary' => [],
                'watch_only' => [],
            ],
        ];

        $snapshots = [
            'ANTM' => ['open_or_last_exec' => 1990, 'spread_pct' => 0.005, 'source' => 'test'],
        ];

        $cfg = [
            'enabled' => true,
            'guards' => [
                'WEEKLY_SWING' => ['max_gap_up_pct' => 0.03, 'max_chase_from_close_pct' => 0.02, 'max_spread_pct' => 0.015],
            ],
        ];

        $res = $cmp->build('WEEKLY_SWING', $planPayload, $snapshots, $cfg);

        $this->assertSame('PASS', $res['per_ticker']['ANTM']['confirm']['status']);
        $this->assertGreaterThan(0, $res['summary']['passed']);
    }
}
