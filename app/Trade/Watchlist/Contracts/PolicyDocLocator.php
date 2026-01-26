<?php

namespace App\Trade\Watchlist\Contracts;

use App\DTO\Watchlist\PolicyDocCheckResult;

interface PolicyDocLocator
{
    public function check(string $policyCode): PolicyDocCheckResult;
}
