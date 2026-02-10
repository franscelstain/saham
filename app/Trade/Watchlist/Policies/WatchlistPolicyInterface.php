<?php

namespace App\Trade\Watchlist\Policies;

use App\Trade\Watchlist\WatchlistEngine;

interface WatchlistPolicyInterface
{
    public function code(): string;

    /**
     * @param array<string,mixed> $x
     * @param string[] $reasonCodes
     * @return array<string,mixed>
     */
    public function apply(array $x, array $reasonCodes, WatchlistEngine $engine): array;
}
