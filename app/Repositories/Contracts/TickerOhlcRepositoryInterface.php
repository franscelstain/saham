<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface TickerOhlcRepositoryInterface
{
    public function getActiveTickers(?string $tickerCode = null): Collection;

    public function getLatestOhlcDate(): ?string;

    public function upsertOhlcDaily(array $rows): void;
}
