<?php

namespace App\Trade\Compute\Classifiers;

final class VolumeLabelClassifier
{
    /** @var float[] */
    private array $t;

    /**
     * thresholds harus 7 angka (ascending), contoh default:
     * [0.4, 0.7, 1.0, 1.5, 2.0, 3.0, 4.0]
     */
    public function __construct(array $thresholds)
    {
        $this->t = array_values(array_map('floatval', $thresholds));
        if (count($this->t) < 7) {
            $this->t = [0.4, 0.7, 1.0, 1.5, 2.0, 3.0, 4.0];
        }
    }

    /**
     * 1 Dormant
     * 2 Ultra Dry
     * 3 Quiet
     * 4 Normal
     * 5 Early Interest
     * 6 Volume Burst / Accumulation
     * 7 Strong Burst / Breakout
     * 8 Climax / Euphoria
     */
    public function classify(?float $volRatio): int
    {
        if ($volRatio === null) return 1;
        $r = (float) $volRatio;
        if ($r <= 0) return 1;

        $t = $this->t;

        if ($r < $t[0]) return 2;
        if ($r < $t[1]) return 3;
        if ($r < $t[2]) return 4;
        if ($r < $t[3]) return 5;
        if ($r < $t[4]) return 6;
        if ($r < $t[6]) return 7; // < 4.0 masih Strong Burst
        return 8;   // >= t[5] itu sudah strong → t[6] tetap 8 juga
    }
}
