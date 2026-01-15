<?php

namespace App\Trade\Compute\Config;

final class PatternThresholds
{
    public float $volStrong;
    public float $volBurst;

    public function __construct(float $volStrong = 2.0, float $volBurst = 1.5)
    {
        $this->volStrong = $volStrong;
        $this->volBurst  = $volBurst;
    }

    public static function fromArray(array $cfg): self
    {
        return new self(
            (float)($cfg['vol_strong'] ?? 2.0),
            (float)($cfg['vol_burst'] ?? 1.5),
        );
    }
}
