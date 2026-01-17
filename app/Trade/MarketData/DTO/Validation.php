<?php

namespace App\Trade\MarketData\DTO;

final class Validation
{
    /** @var bool */
    public $hardValid;

    /** @var string[] */
    public $flags;

    /** @var string|null */
    public $errorCode;

    /** @var string|null */
    public $errorMsg;

    /**
     * @param string[] $flags
     */
    public function __construct(bool $hardValid, array $flags = [], ?string $errorCode = null, ?string $errorMsg = null)
    {
        $this->hardValid = $hardValid;
        $this->flags = $flags;
        $this->errorCode = $errorCode;
        $this->errorMsg = $errorMsg;
    }
}
