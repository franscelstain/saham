<?php

declare(strict_types=1);

namespace Tests\Unit\AntiDrift;

use PHPUnit\Framework\TestCase;

final class WatchlistSrpAntiDriftTest extends TestCase
{
    private function read(string $relativePath): string
    {
        $base = dirname(__DIR__, 2); // tests/
        $path = $base . '/../' . ltrim($relativePath, '/');
        $this->assertFileExists($path, "Missing file: {$relativePath}");
        $content = file_get_contents($path);
        $this->assertIsString($content);
        return $content;
    }

    public function test_watchlist_engine_must_not_read_config_directly(): void
    {
        $src = $this->read('app/Trade/Watchlist/WatchlistEngine.php');

        // SRP_Performa: config() access must be in Provider/Composition Root.
        $this->assertStringNotContainsString("config('trade.watchlist", $src);
        $this->assertStringNotContainsString('config("trade.watchlist', $src);
        $this->assertStringNotContainsString("config('trade.watchlist", $src);
    }

    public function test_watchlist_engine_must_not_query_db_directly(): void
    {
        $src = $this->read('app/Trade/Watchlist/WatchlistEngine.php');

        // SRP_Performa: DB access belongs to Repository.
        $this->assertDoesNotMatchRegularExpression('/\\\\DB::\s*table\s*\(/', $src);
        $this->assertDoesNotMatchRegularExpression('/\bDB::\s*table\s*\(/', $src);
        $this->assertDoesNotMatchRegularExpression('/\\\\DB::\s*select\s*\(/', $src);
    }
}
