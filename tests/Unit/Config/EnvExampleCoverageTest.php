<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

final class EnvExampleCoverageTest extends TestCase
{
    public function testEnvExampleCoversRequiredConfigEnvKeys(): void
    {
        $configDir = base_path('config');
        $envExample = base_path('.env.example');

        $this->assertDirectoryExists($configDir, 'config/ directory missing');
        $this->assertFileExists($envExample, '.env.example missing');

        $presentKeys = $this->parseEnvKeys(file_get_contents($envExample) ?: '');

        $required = [];
        foreach (glob($configDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $code = file_get_contents($file) ?: '';
            foreach ($this->extractEnvCalls($code) as $call) {
                [$key, $hasDefault] = $call;

                // Only enforce keys that are TradeAxis-specific.
                // Laravel's stock configs reference many optional provider keys (AWS_*, MAILGUN_*, etc).
                if (!$this->isTradeAxisEnvKey($key)) continue;

                // Optional if env('KEY', <default>) exists.
                if ($hasDefault) continue;

                $required[$key] = true;
            }
        }

        $missing = [];
        foreach (array_keys($required) as $key) {
            if (!isset($presentKeys[$key])) $missing[] = $key;
        }

        sort($missing);
        $this->assertSame([], $missing, 'Missing required TradeAxis env keys in .env.example: ' . implode(', ', $missing));
    }

    private function isTradeAxisEnvKey(string $key): bool
    {
        return (bool) preg_match('/^(TRADEAXIS_|TA_|TRADE_|WATCHLIST_|SCORECARD_|PORTFOLIO_|MARKET_|EOD_|TICK_|FEE_|SLIPPAGE_)/', $key);
    }

    /** @return array<string,bool> */
    private function parseEnvKeys(string $content): array
    {
        $keys = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (!preg_match('/^([A-Z0-9_]+)=/', $line, $m)) continue;
            $keys[$m[1]] = true;
        }
        return $keys;
    }

    /**
     * Extract env('KEY') or env("KEY") calls from config file content.
     * Returns list of [key, hasDefault].
     *
     * @return array<int, array{0:string,1:bool}>
     */
    private function extractEnvCalls(string $php): array
    {
        $out = [];

        // Very small parser: good enough for config/*.php.
        $patterns = [
            "/env\(\s*'([^']+)'\s*(?:,\s*[^\)]+)?\)/",
            '/env\(\s*"([^"]+)"\s*(?:,\s*[^\)]+)?\)/',
        ];

        foreach ($patterns as $pat) {
            if (!preg_match_all($pat, $php, $m, PREG_OFFSET_CAPTURE)) continue;
            for ($i = 0; $i < count($m[0]); $i++) {
                $full = $m[0][$i][0];
                $key = $m[1][$i][0];
                $hasDefault = (strpos($full, ',') !== false);
                $out[] = [$key, $hasDefault];
            }
        }

        return $out;
    }
}
