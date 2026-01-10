<?php

namespace App\DTO\Trade;

class RuleResult
{
    public bool $pass;
    public string $code;     // contoh: TREND_OK, RSI_TOO_HIGH
    public string $message;  // buat UI/debug (opsional)

    public function __construct(bool $pass, string $code, string $message = '')
    {
        $this->pass = $pass;
        $this->code = $code;
        $this->message = $message;
    }
}
