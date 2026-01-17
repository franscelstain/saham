<?php

namespace App\Trade\MarketData\Select;

use App\Trade\MarketData\Config\ProviderPriority;
use App\Trade\MarketData\DTO\CanonicalPick;
use App\Trade\MarketData\DTO\EodBar;
use App\Trade\MarketData\DTO\Validation;

final class CanonicalSelector
{
    /** @var ProviderPriority */
    private $priority;

    public function __construct(ProviderPriority $priority)
    {
        $this->priority = $priority;
    }

    /**
     * @param array<string,array{bar:EodBar, val:Validation}> $candidatesBySource
     */
    public function select(string $tradeDate, array $candidatesBySource): ?CanonicalPick
    {
        $prio = $this->priority->names();
        $first = $this->priority->first();

        foreach ($prio as $src) {
            if (!isset($candidatesBySource[$src])) continue;

            $pair = $candidatesBySource[$src];
            /** @var Validation $val */
            $val = $pair['val'];

            if ($val->hardValid) {
                $reason = ($src === $first) ? 'PRIORITY_WIN' : 'FALLBACK_USED';
                $flags = $val->flags ?: [];
                /** @var EodBar $bar */
                $bar = $pair['bar'];
                return new CanonicalPick($tradeDate, $src, $reason, $flags, $bar);
            }
        }

        return null;
    }
}
