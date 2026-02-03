<?php

namespace Tests\Unit\Contracts;

use App\Trade\Watchlist\Contracts\PreopenContractValidator;
use Tests\TestCase;

class PreopenContractTest extends TestCase
{
    public function test_fixture_weekly_swing_is_valid(): void
    {
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/watchlist/preopen_weekly_swing.json')), true);
        $this->assertIsArray($payload);

        (new PreopenContractValidator())->validate($payload);
        $this->assertTrue(true);
    }

    public function test_fixture_eod_not_ready_is_valid(): void
    {
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/watchlist/preopen_eod_not_ready.json')), true);
        $this->assertIsArray($payload);

        (new PreopenContractValidator())->validate($payload);
        $this->assertTrue(true);
    }

    public function test_missing_meta_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/watchlist/preopen_weekly_swing.json')), true);
        unset($payload['meta']);

        (new PreopenContractValidator())->validate($payload);
    }
}
