<?php

namespace App\Trade\Compute\Classifiers;

class VolumeLabelClassifier
{
    /**
     * Volume label berbasis vol_ratio:
     * vol_ratio = volume_today / vol_sma20_prev
     *
     * Output code (8 level):
     * 1 Dormant
     * 2 Ultra Dry
     * 3 Quiet
     * 4 Normal
     * 5 Early Interest
     * 6 Volume Burst
     * 7 Strong Burst
     * 8 Climax / Euphoria
     */
    public function classify($volRatio): ?int
    {
        if ($volRatio === null) return null;
        $r = (float)$volRatio;

        // threshold bisa diubah dari config
        $t = config('trade.compute.volume_ratio_thresholds', [
            0.4, 0.7, 1.0, 1.5, 2.0, 3.0, 4.0
        ]);

        if ($r < (float)$t[0]) return 1;
        if ($r < (float)$t[1]) return 2;
        if ($r < (float)$t[2]) return 3;
        if ($r < (float)$t[3]) return 4;
        if ($r < (float)$t[4]) return 5;
        if ($r < (float)$t[5]) return 6;
        if ($r < (float)$t[6]) return 7;
        return 8;
    }
}
