<?php

namespace Tests\Unit\Console;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

final class CommandSignatureStabilityTest extends TestCase
{
    /**
     * @dataProvider signatureProvider
     */
    public function testCommandSignatureContainsExpectedOptions(string $commandName, array $mustContain): void
    {
        $cmd = $this->findCommand($commandName);
        $this->assertNotNull($cmd, 'Command not registered: ' . $commandName);
        $this->assertSame($commandName, $cmd->getName());

        // Assert options exist via Symfony definition (works for both signature and configure()).
        $optNames = [];
        foreach ($cmd->getDefinition()->getOptions() as $opt) {
            $optNames[$opt->getName()] = true;
        }

        foreach ($mustContain as $needle) {
            if (strpos($needle, '--') === 0) {
                $opt = ltrim($needle, '-');
                $this->assertArrayHasKey(
                    $opt,
                    $optNames,
                    sprintf('Expected %s to have option "%s". Available: %s', $commandName, $needle, implode(', ', array_keys($optNames)))
                );
            }
        }
    }

    private function findCommand(string $commandName): ?Command
    {
        // Ensure kernel is booted.
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class);

        foreach (Artisan::all() as $name => $cmd) {
            if ($name === $commandName) {
                return $cmd;
            }
        }

        return null;
    }

    /** @return array<string, array{0:string,1:array<int,string>}> */
    public function signatureProvider(): array
    {
        return [
            'market-data:import-eod' => [
                'market-data:import-eod',
                ['market-data:import-eod', '--date'],
            ],
            'market-data:publish-eod' => [
                'market-data:publish-eod',
                ['market-data:publish-eod', '--run'],
            ],
            'market-data:rebuild-canonical' => [
                'market-data:rebuild-canonical',
                ['market-data:rebuild-canonical', '--date'],
            ],
            'trade:compute-eod' => [
                'trade:compute-eod',
                ['trade:compute-eod', '--date'],
            ],
            'market-data:validate-eod' => [
                'market-data:validate-eod',
                ['market-data:validate-eod', '--date', '--tickers'],
            ],
            'watchlist:scorecard:check-live' => [
                'watchlist:scorecard:check-live',
                ['watchlist:scorecard:check-live', '--trade-date', '--exec-date'],
            ],
            'watchlist:scorecard:compute' => [
                'watchlist:scorecard:compute',
                ['watchlist:scorecard:compute', '--trade-date', '--exec-date', '--policy'],
            ],
            'portfolio:expire-plans' => [
                'portfolio:expire-plans',
                ['portfolio:expire-plans', '--date'],
            ],
            'portfolio:ingest-trade' => [
                'portfolio:ingest-trade',
                ['portfolio:ingest-trade', '--account', '--ticker', '--date', '--side', '--qty', '--price', '--external_ref'],
            ],
            'portfolio:value-eod' => [
                'portfolio:value-eod',
                ['portfolio:value-eod', '--date', '--account'],
            ],
            'portfolio:cancel-plan' => [
                'portfolio:cancel-plan',
                ['portfolio:cancel-plan', '--reason'],
            ],
        ];
    }
}
