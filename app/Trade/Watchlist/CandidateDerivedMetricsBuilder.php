<?php

namespace App\Trade\Watchlist;

use App\DTO\Watchlist\CandidateInput;
use App\Trade\Watchlist\Config\WatchlistPolicyConfig;

/**
 * CandidateDerivedMetricsBuilder
 *
 * Mengisi field derivatif pada CandidateInput (liq bucket, corporate action gate, candle flags).
 * Tidak baca config() langsung; semua threshold berasal dari WatchlistPolicyConfig.
 */
final class CandidateDerivedMetricsBuilder
{
    private WatchlistPolicyConfig $cfg;

    public function __construct(WatchlistPolicyConfig $cfg)
    {
        $this->cfg = $cfg;
    }

    public function enrich(CandidateInput $c): CandidateInput
    {
        $this->computeLiquidityBucket($c);
        $this->computeCorporateActionGate($c);
        $this->computeCandleFlags($c);

        return $c;
    }

    private function computeLiquidityBucket(CandidateInput $c): void
    {
        if ($c->liqBucket !== null && $c->liqBucket !== '') return;

        $dv20 = $c->dv20;
        if ($dv20 === null || $dv20 <= 0) {
            $c->liqBucket = 'U';
            return;
        }

        $a = $this->cfg->dv20AMin();
        $b = $this->cfg->dv20BMin();

        if ($dv20 >= $a) $c->liqBucket = 'A';
        elseif ($dv20 >= $b) $c->liqBucket = 'B';
        else $c->liqBucket = 'C';
    }

    private function computeCorporateActionGate(CandidateInput $c): void
    {
        if ($c->prevClose === null || $c->prevClose <= 0 || $c->close <= 0) return;

        $ratio = $c->close / $c->prevClose;
        $c->corpActionRatio = round($ratio, 6);

        $min = $this->cfg->caSuspectMin();
        $max = $this->cfg->caSuspectMax();

        $suspect = ($ratio <= $min) || ($ratio >= $max);

        if (!$suspect && $c->open !== null && $c->open > 0) {
            $openRatio = $c->open / $c->prevClose;
            if ($openRatio <= $min || $openRatio >= $max) $suspect = true;
        }

        $c->corpActionSuspected = (bool) $suspect;
    }

    private function computeCandleFlags(CandidateInput $c): void
    {
        if ($c->open === null || $c->high === null || $c->low === null) return;

        $range = $c->high - $c->low;
        if ($range <= 0) return;

        $body = abs($c->close - $c->open);
        $upper = $c->high - max($c->open, $c->close);
        $lower = min($c->open, $c->close) - $c->low;

        $c->candleBodyPct = $this->clamp01($body / $range);
        $c->candleUpperWickPct = $this->clamp01($upper / $range);
        $c->candleLowerWickPct = $this->clamp01($lower / $range);

        // inside day
        if ($c->prevHigh !== null && $c->prevLow !== null) {
            $c->isInsideDay = ($c->high <= $c->prevHigh) && ($c->low >= $c->prevLow);
        }

        // engulfing type
        if ($c->prevOpen !== null && $c->prevClose !== null) {
            $bullPrev = $c->prevClose > $c->prevOpen;
            $bullNow  = $c->close > $c->open;
            $bearPrev = $c->prevClose < $c->prevOpen;
            $bearNow  = $c->close < $c->open;

            if ($bullNow && $bearPrev && $c->close >= $c->prevOpen && $c->open <= $c->prevClose) {
                $c->engulfingType = 'bull';
            } elseif ($bearNow && $bullPrev && $c->open >= $c->prevClose && $c->close <= $c->prevOpen) {
                $c->engulfingType = 'bear';
            }
        }

        $wickThr = $this->cfg->candleLongWickPct();
        if ($c->candleUpperWickPct !== null) $c->isLongUpperWick = ($c->candleUpperWickPct >= $wickThr);
        if ($c->candleLowerWickPct !== null) $c->isLongLowerWick = ($c->candleLowerWickPct >= $wickThr);

        // close near high (<=25% dari range)
        $c->closeNearHigh = (($c->high - $c->close) / $range) <= 0.25;
    }

    private function clamp01(float $v): float
    {
        if ($v < 0) return 0.0;
        if ($v > 1) return 1.0;
        return $v;
    }
}
