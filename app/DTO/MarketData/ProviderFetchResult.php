<?php

namespace App\DTO\MarketData;

final class ProviderFetchResult
{
    /** @var RawBar[] */
    public $bars;

    /** @var string|null */
    public $errorCode;

    /** @var string|null */
    public $errorMsg;

    /**
     * @param RawBar[] $bars
     */
    public function __construct(array $bars, ?string $errorCode = null, ?string $errorMsg = null)
    {
        $this->bars = $bars;
        $this->errorCode = $errorCode;
        $this->errorMsg = $errorMsg;
    }
}
