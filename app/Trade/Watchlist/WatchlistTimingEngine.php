<?php

namespace App\Trade\Watchlist;

/**
 * SRP: engine jam beli berbasis EOD -> entry_windows + avoid_windows + sizing hints.
 * Tidak melakukan scoring/ranking.
 */
class WatchlistTimingEngine
{
    /**
     * @param string $dow Mon/Tue/Wed/Thu/Fri
     * @param string $setupType Breakout/Pullback/Continuation/Reversal/Base
     * @param array{gap_risk_high:bool,volatility_high:bool,liq_low:bool,market_risk_off:bool} $risk
     * @return array{entry_windows:string[],avoid_windows:string[],entry_style:string,size_multiplier:float,max_positions_today:int}
     */
    public function advise(string $dow, string $setupType, array $risk): array
    {
        // Baseline (WATCHLIST.md 8.1)
        $entry = ['09:20-10:30', '13:35-14:30'];
        $avoid = ['09:00-09:15', '11:45-12:00', '15:50-close'];

        // Day-of-week adjustment (WATCHLIST.md 8.2)
        $size = 1.0;
        $maxPos = 2;
        switch ($dow) {
            case 'Mon':
                $entry = ['09:35-11:00', '13:35-14:30'];
                $size = 0.7;
                $maxPos = 1;
                break;
            case 'Tue':
                $entry = ['09:20-10:30', '13:35-14:30'];
                $size = 1.0;
                $maxPos = 2;
                break;
            case 'Wed':
                $entry = ['09:30-10:45', '13:35-14:15'];
                $size = 0.8;
                $maxPos = 1;
                break;
            case 'Thu':
                $entry = ['09:45-10:45', '13:45-14:15'];
                $size = 0.5;
                $maxPos = 1;
                break;
            case 'Fri':
                // default NO TRADE: maxPos=0, size kecil
                $entry = [];
                $size = 0.3;
                $maxPos = 0;
                break;
        }

        // Setup-driven tweak (WATCHLIST.md 8.3)
        if ($setupType === 'Breakout') {
            $entry = array_values(array_unique(array_merge($entry, ['09:20-10:15'])));
        } elseif ($setupType === 'Pullback') {
            $entry = array_values(array_unique(array_merge($entry, ['09:35-11:00', '13:35-14:45'])));
        } elseif ($setupType === 'Reversal') {
            $entry = ['10:00-11:30', '13:45-14:30'];
            $avoid = array_values(array_unique(array_merge($avoid, ['09:00-09:30'])));
        }

        // Risk-driven shifting (WATCHLIST.md 8.3)
        if (!empty($risk['market_risk_off'])) {
            $size = min($size, 0.6);
            $maxPos = min($maxPos, 1);
        }

        if (!empty($risk['gap_risk_high']) || !empty($risk['volatility_high'])) {
            $avoid = array_values(array_unique(array_merge($avoid, ['pre-open', '09:00-09:30'])));
            if ($dow !== 'Fri') {
                $entry = ['09:45-11:15', '13:45-14:30'];
            }
            $size = min($size, 0.8);
            $maxPos = min($maxPos, 1);
        }

        if (!empty($risk['liq_low'])) {
            $avoid = array_values(array_unique(array_merge($avoid, ['09:00-09:30', '15:30-close'])));
            if ($dow !== 'Fri') {
                $entry = ['10:00-11:30', '13:45-14:30'];
            }
            $size = min($size, 0.8);
            $maxPos = min($maxPos, 1);
        }

        $entry = array_values(array_unique($entry));
        $avoid = array_values(array_unique($avoid));

        $style = 'Breakout-confirm';
        if ($setupType === 'Pullback') $style = 'Pullback-wait';
        elseif ($setupType === 'Reversal') $style = 'Reversal-confirm';
        elseif ($setupType === 'Base') $style = 'No-trade';
        if ($dow === 'Fri') $style = 'No-trade';

        return [
            'entry_windows' => $entry,
            'avoid_windows' => $avoid,
            'entry_style' => $style,
            'size_multiplier' => (float) $size,
            'max_positions_today' => (int) $maxPos,
        ];
    }
}
