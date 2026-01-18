<?php

namespace App\Services\MarketData;

use App\Repositories\MarketCalendarRepository;
use App\Repositories\TickerOhlcDailyRepository;

final class CorporateActionHintService
{
    private MarketCalendarRepository $cal;
    private TickerOhlcDailyRepository $ohlc;

    /** tolerance relatif, mis 0.03 = 3% */
    private float $tol;

    public function __construct(MarketCalendarRepository $cal, TickerOhlcDailyRepository $ohlc, float $tol = 0.03)
    {
        $this->cal = $cal;
        $this->ohlc = $ohlc;
        $this->tol = $tol;
    }

    /**
     * Apply CA hints for all tickers on a trade date.
     * If $runId provided, only rows with that run_id will be considered (lebih aman untuk publish-run).
     *
     * @return array{updated:int, split:int, reverse_split:int, adj_diff:int}
     */
    public function applyForDate(string $tradeDate, ?int $runId = null): array
    {
        $prevDate = $this->cal->previousTradingDate($tradeDate);
        if (!$prevDate) {
            return ['updated' => 0, 'split' => 0, 'reverse_split' => 0, 'adj_diff' => 0];
        }

        $rows = $this->ohlc->listForDate($tradeDate, $runId);
        if (!$rows) {
            return ['updated' => 0, 'split' => 0, 'reverse_split' => 0, 'adj_diff' => 0];
        }

        $tickerIds = [];
        foreach ($rows as $r) $tickerIds[] = (int) $r['ticker_id'];

        $prevMap = $this->ohlc->mapPrevCloseVolume($prevDate, $tickerIds);

        $updates = [];
        $split = 0;
        $rsplit = 0;
        $adjDiff = 0;

        foreach ($rows as $r) {
            $tid = (int) $r['ticker_id'];

            $close = $r['close'] !== null ? (float) $r['close'] : null;
            if ($close === null || $close <= 0) continue;

            $prevClose = $prevMap[$tid]['close'] ?? null;
            if ($prevClose === null || $prevClose <= 0) continue;

            $adj = $r['adj_close'] !== null ? (float) $r['adj_close'] : null;

            $event = null;
            $hint  = null;

            // 1) Heuristic split / reverse split dari ratio close_today / prev_close
            $ratio = $close / $prevClose;

            // common split ratios
            // split: 2:1 => 0.5 ; 3:1 => 0.3333 ; 4:1 => 0.25 ; 5:1 => 0.2 ; 10:1 => 0.1
            // reverse split: 1:2 => 2 ; 1:3 => 3 ; 1:4 => 4 ; 1:5 => 5 ; 1:10 => 10
            $splitHint = $this->matchRatioHint($ratio);
            if ($splitHint !== null) {
                $hint = $splitHint;

                // tentukan event type
                if (strpos($hint, 'SPLIT_') === 0) {
                    $event = 'SPLIT';
                    $split++;
                } elseif (strpos($hint, 'RSPLIT_') === 0) {
                    $event = 'REVERSE_SPLIT';
                    $rsplit++;
                }
            }

            // 2) Adj-close difference hint (audit)
            // kalau provider kasih adj_close dan beda jauh dari close → tandai
            if ($adj !== null && $adj > 0) {
                $diff = abs($adj - $close) / $close;
                if ($diff >= 0.15) { // 15% threshold (cukup agresif tapi aman untuk “hint”)
                    if ($event === null) $event = 'UNKNOWN';
                    $hint = $hint ? ($hint . '|CA_ADJ_DIFF') : 'CA_ADJ_DIFF';
                    $adjDiff++;
                }
            }

            // Kalau tidak ada indikasi apapun, skip update (biar tidak overwrite data manual)
            if ($event === null && $hint === null) continue;

            $updates[] = [
                'ticker_id' => $tid,
                'trade_date' => $tradeDate,
                'ca_event' => $event,
                'ca_hint' => $hint,
                'updated_at' => now(),
            ];
        }

        if ($updates) {
            $this->ohlc->upsertCaHints($updates);
        }

        return [
            'updated' => count($updates),
            'split' => $split,
            'reverse_split' => $rsplit,
            'adj_diff' => $adjDiff,
        ];
    }

    private function matchRatioHint(float $ratio): ?string
    {
        // target ratios
        $targets = [
            // SPLIT N_FOR_1 => close_today ≈ prev_close / N => ratio ≈ 1/N
            'SPLIT_2_FOR_1'  => 1 / 2,
            'SPLIT_3_FOR_1'  => 1 / 3,
            'SPLIT_4_FOR_1'  => 1 / 4,
            'SPLIT_5_FOR_1'  => 1 / 5,
            'SPLIT_10_FOR_1' => 1 / 10,

            // Reverse split 1_FOR_N => close_today ≈ prev_close * N => ratio ≈ N
            'RSPLIT_1_FOR_2'  => 2,
            'RSPLIT_1_FOR_3'  => 3,
            'RSPLIT_1_FOR_4'  => 4,
            'RSPLIT_1_FOR_5'  => 5,
            'RSPLIT_1_FOR_10' => 10,
        ];

        foreach ($targets as $label => $t) {
            $band = $t * $this->tol; // relative tolerance band
            if (abs($ratio - $t) <= $band) {
                return $label;
            }
        }

        return null;
    }
}
