<?php

namespace App\Trade\Compute\Indicators;

class IndicatorCalculator
{
    public function sma(array $values, int $period)
    {
        $n = count($values);
        if ($n < $period) return null;

        $slice = array_slice($values, $n - $period, $period);
        $sum = 0.0;
        foreach ($slice as $v) $sum += (float)$v;

        return $sum / $period;
    }

    public function smaExcludeToday(array $values, int $period)
    {
        $n = count($values);
        if ($n < ($period + 1)) return null;

        $slice = array_slice($values, $n - 1 - $period, $period);
        $sum = 0.0;
        foreach ($slice as $v) $sum += (float)$v;

        return $sum / $period;
    }

    public function rollingMinExcludeToday(array $values, int $period)
    {
        $n = count($values);
        if ($n < ($period + 1)) return null;

        $slice = array_slice($values, $n - 1 - $period, $period);
        $min = null;
        foreach ($slice as $v) {
            $v = (float)$v;
            if ($min === null || $v < $min) $min = $v;
        }
        return $min;
    }

    public function rollingMaxExcludeToday(array $values, int $period)
    {
        $n = count($values);
        if ($n < ($period + 1)) return null;

        $slice = array_slice($values, $n - 1 - $period, $period);
        $max = null;
        foreach ($slice as $v) {
            $v = (float)$v;
            if ($max === null || $v > $max) $max = $v;
        }
        return $max;
    }

    public function rsiWilder(array $closes, int $period = 14)
    {
        $n = count($closes);
        if ($n < ($period + 1)) return null;

        $gains = 0.0;
        $losses = 0.0;

        // seed avg gain/loss
        for ($i = $n - $period; $i < $n; $i++) {
            $chg = (float)$closes[$i] - (float)$closes[$i - 1];
            if ($chg >= 0) $gains += $chg;
            else $losses += abs($chg);
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        if ($avgLoss == 0.0) return 100.0;

        $rs = $avgGain / $avgLoss;
        return 100.0 - (100.0 / (1.0 + $rs));
    }

    public function atrWilder(array $highs, array $lows, array $closes, int $period = 14)
    {
        $n = count($closes);
        if ($n < ($period + 1)) return null;

        $trs = [];
        for ($i = 1; $i < $n; $i++) {
            $h = (float)$highs[$i];
            $l = (float)$lows[$i];
            $pc = (float)$closes[$i - 1];
            $trs[] = max($h - $l, abs($h - $pc), abs($l - $pc));
        }

        if (count($trs) < $period) return null;

        $seed = array_slice($trs, 0, $period);
        $sum = 0.0;
        foreach ($seed as $v) $sum += (float)$v;
        $atr = $sum / $period;

        for ($i = $period; $i < count($trs); $i++) {
            $atr = (($atr * ($period - 1)) + (float)$trs[$i]) / $period;
        }

        return $atr;
    }
}
