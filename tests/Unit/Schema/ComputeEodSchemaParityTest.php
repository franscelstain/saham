<?php

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use App\Repositories\TickerIndicatorsDailyRepository;
use App\Repositories\TickerSignalsDailyRepository;

class ComputeEodSchemaParityTest extends TestCase
{
    private function readMigration(string $needle): string
    {
        $base = base_path('database/migrations');
        $files = glob($base . DIRECTORY_SEPARATOR . '*_' . $needle . '_table.php');
        $this->assertNotEmpty($files, "Migration file not found for {$needle}");
        return (string) file_get_contents((string)$files[0]);
    }

    private function extractColumnsFromMigration(string $php): array
    {
        $cols = [];

        // Capture schema builder column definitions like:
        //   $table->decimal('atr14', 10, 6);
        //   $table->unsignedBigInteger("ticker_id");
        // Accept both single and double quotes.
        if (preg_match_all('/\$table->\w+\(\s*[\'\"]([^\'\"]+)[\'\"]/m', $php, $m)) {
            foreach ($m[1] as $c) {
                $cols[] = $c;
            }
        }

        // IMPORTANT: use single-quoted regex so "\s" isn't eaten by PHP string escaping.
        if (preg_match('/\$table->timestamps\s*\(/', $php)) {
            $cols[] = 'created_at';
            $cols[] = 'updated_at';
        }

        if (preg_match('/\$table->softDeletes\s*\(/', $php)) {
            $cols[] = 'deleted_at';
        }

        $cols = array_values(array_unique($cols));
        sort($cols);
        return $cols;
    }

    public function test_ticker_indicators_daily_columns_match_repository_whitelist(): void
    {
        $php = $this->readMigration('create_ticker_indicators_daily');
        $actual = $this->extractColumnsFromMigration($php);

        $expected = TickerIndicatorsDailyRepository::COLUMNS;
        sort($expected);

        $this->assertSame($expected, $actual, 'ticker_indicators_daily columns must match Repository::COLUMNS (anti-drift).');
    }

    public function test_ticker_signals_daily_columns_match_repository_whitelist(): void
    {
        $php = $this->readMigration('create_ticker_signals_daily');
        $actual = $this->extractColumnsFromMigration($php);

        $expected = TickerSignalsDailyRepository::COLUMNS;
        sort($expected);

        $this->assertSame($expected, $actual, 'ticker_signals_daily columns must match Repository::COLUMNS (anti-drift).');
    }
}
