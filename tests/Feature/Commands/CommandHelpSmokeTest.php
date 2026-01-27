<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * CLI contract smoke tests (signature-level).
 *
 * This checks that the commands are registered and their key options exist.
 * It does NOT execute commands and does NOT rely on parsing `--help` output.
 */
class CommandHelpSmokeTest extends TestCase
{
    private function assertCommandHasOptions(string $commandName, array $requiredOptions): void
    {
        $all = Artisan::all();
        $this->assertArrayHasKey($commandName, $all, "Command not registered: {$commandName}");

        $cmd = $all[$commandName];
        $def = $cmd->getDefinition();

        foreach ($requiredOptions as $opt) {
            $candidates = array_unique([
                $opt,
                ltrim($opt, '-'),
                str_replace('_', '-', $opt),
                str_replace('-', '_', $opt),
                str_replace('_', '-', ltrim($opt, '-')),
                str_replace('-', '_', ltrim($opt, '-')),
            ]);

            $found = false;
            foreach ($candidates as $cand) {
                if ($cand === '') continue;
                if ($def->hasOption($cand)) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue($found, "Command {$commandName} is missing option: {$opt}");
        }
    }

    public function testMarketDataImportEodHasDateOption(): void
    {
        $this->assertCommandHasOptions('market-data:import-eod', ['date']);
    }

    public function testMarketDataPublishEodHasRunOption(): void
    {
        $this->assertCommandHasOptions('market-data:publish-eod', ['run']);
    }

    public function testMarketDataRebuildCanonicalHasDateOption(): void
    {
        $this->assertCommandHasOptions('market-data:rebuild-canonical', ['date']);
    }

    public function testTradeComputeEodHasDateOption(): void
    {
        $this->assertCommandHasOptions('trade:compute-eod', ['date']);
    }

    public function testMarketDataValidateEodHasTickersOption(): void
    {
        $this->assertCommandHasOptions('market-data:validate-eod', ['date', 'tickers']);
    }

    public function testWatchlistScorecardCheckLiveHasTradeAndExecDateOptions(): void
    {
        $this->assertCommandHasOptions('watchlist:scorecard:check-live', ['trade-date', 'exec-date']);
    }

    public function testWatchlistScorecardComputeHasPolicyOption(): void
    {
        $this->assertCommandHasOptions('watchlist:scorecard:compute', ['trade-date', 'exec-date', 'policy']);
    }

    public function testPortfolioExpirePlansHasDateOption(): void
    {
        $this->assertCommandHasOptions('portfolio:expire-plans', ['date']);
    }

    public function testPortfolioIngestTradeHasCoreOptions(): void
    {
        $this->assertCommandHasOptions('portfolio:ingest-trade', [
            'account',
            'ticker',
            'date',
            'side',
            'qty',
            'price',
            'external_ref',
        ]);
    }

    public function testPortfolioValueEodHasDateAndAccountOptions(): void
    {
        $this->assertCommandHasOptions('portfolio:value-eod', ['date', 'account']);
    }

    public function testPortfolioCancelPlanHasReasonOption(): void
    {
        $this->assertCommandHasOptions('portfolio:cancel-plan', ['reason']);
    }
}
