<?php

namespace Tests\Unit\Watchlist\Contracts;

use PHPUnit\Framework\TestCase;
use App\Trade\Watchlist\Contracts\PreopenContractValidator;

final class PreopenContractGoldenFixturesTest extends TestCase
{
    /**
     * @dataProvider fixturePaths
     */
    public function testFixturesPassStrictValidator(string $path): void
    {
        $json = file_get_contents($path);
        $this->assertNotFalse($json, 'Failed to read fixture: '.$path);
        $payload = json_decode($json, true);
        $this->assertIsArray($payload, 'Invalid JSON in fixture: '.$path);

        (new PreopenContractValidator())->validate($payload);

        // If no exception: pass.
        $this->assertTrue(true);
    }

    public static function fixturePaths(): array
    {
        $base = __DIR__ . '/../../../Fixtures/watchlist';
        return [
            [$base.'/preopen_weekly_swing.json'],
            [$base.'/preopen_weekly_swing_with_capital.json'],
            [$base.'/preopen_eod_not_ready.json'],
        ];
    }
}
