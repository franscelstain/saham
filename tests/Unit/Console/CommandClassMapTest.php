<?php

namespace Tests\Unit\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class CommandClassMapTest extends TestCase
{
    /**
     * This catches accidental renames/moves where the signature still exists
     * but points to a different class.
     */
    public function testTradeComputeEodCommandResolvesToExpectedClass(): void
    {
        $all = Artisan::all();
        $this->assertArrayHasKey('trade:compute-eod', $all);
        $this->assertSame('App\\Console\\Commands\\ComputeEod', get_class($all['trade:compute-eod']));
    }
}
