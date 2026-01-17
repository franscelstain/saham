<?php

namespace App\Trade\MarketData\Validate;

use App\Trade\MarketData\Config\QualityRules;
use App\Trade\MarketData\DTO\EodBar;
use App\Trade\MarketData\DTO\Validation;

final class EodQualityGate
{
    /** @var QualityRules */
    private $rules;

    public function __construct(QualityRules $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Hard rules reject: invalid OHLC bounds, non-positive prices, negative volume, missing fields.
     * Soft flags: can be extended later (gap/outlier/disagreement).
     */
    public function validate(EodBar $bar): Validation
    {
        $flags = [];

        // Required fields
        if ($bar->open === null || $bar->high === null || $bar->low === null || $bar->close === null || $bar->volume === null) {
            return new Validation(false, $flags, 'MISSING_FIELDS', 'OHLCV incomplete');
        }

        $o = (float) $bar->open;
        $h = (float) $bar->high;
        $l = (float) $bar->low;
        $c = (float) $bar->close;
        $v = (int) $bar->volume;

        if ($o <= 0 || $h <= 0 || $l <= 0 || $c <= 0) {
            return new Validation(false, $flags, 'NON_POSITIVE_PRICE', 'Price must be > 0');
        }

        if ($v < 0) {
            return new Validation(false, $flags, 'NEGATIVE_VOLUME', 'Volume must be >= 0');
        }

        if ($h < $l) {
            return new Validation(false, $flags, 'HIGH_LT_LOW', 'High must be >= Low');
        }

        $tol = $this->rules->tol();

        // Open/Close must be in [low..high] with tolerance
        if ($o < ($l - $tol) || $o > ($h + $tol)) {
            return new Validation(false, $flags, 'OPEN_OUT_OF_RANGE', 'Open out of [low..high]');
        }
        if ($c < ($l - $tol) || $c > ($h + $tol)) {
            return new Validation(false, $flags, 'CLOSE_OUT_OF_RANGE', 'Close out of [low..high]');
        }

        // Soft flags placeholders (extend later)
        if ($v == 0) $flags[] = 'VOL_ZERO';

        return new Validation(true, $flags, null, null);
    }
}
