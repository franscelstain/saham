<?php

namespace App\Trade\Watchlist\Scorecard;

use App\Repositories\TickerOhlcDailyRepository;

class ScorecardCalculator
{
    public function __construct(private TickerOhlcDailyRepository $ohlcRepo)
    {
    }

    /**
     * Compute scorecard metrics.
     *
     * @param array<string,mixed> $runPayload
     * @param array<string,mixed>|null $latestCheck
     * @return array{feasible_rate:?float,fill_rate:?float,outcome_rate:?float,payload:array<string,mixed>}
     */
    public function compute(array $runPayload, ?array $latestCheck): array
    {
        $feasible = null;
        $fill = null;
        $outcome = null;

        $policy = (string) (($runPayload['policy']['selected'] ?? '') ?: ($runPayload['policy'] ?? ''));
        $execDate = (string) ($runPayload['exec_trade_date'] ?? '');

        $candidates = $this->collectCandidates($runPayload, false);

        // Feasible rate from latest check (eligible_now true / evaluated)
        if ($latestCheck && isset($latestCheck['result']) && is_array($latestCheck['result'])) {
            $res = $latestCheck['result'];
            $rows = $res['results'] ?? [];
            if (is_array($rows) && count($rows) > 0) {
                $eligible = 0;
                $eval = 0;
                foreach ($rows as $r) {
                    if (!is_array($r)) continue;
                    $eval++;
                    if (!empty($r['eligible_now'])) $eligible++;
                }
                if ($eval > 0) $feasible = $eligible / $eval;
            }
        }

        // Fill rate: use EOD high/low on exec_date and compare against entry levels.
        if ($execDate !== '' && !empty($candidates)) {
            $tickers = array_values(array_unique(array_map(function ($c) {
                if (!is_array($c)) return '';
                return strtoupper((string)($c['ticker'] ?? ($c['ticker_code'] ?? '')));
            }, $candidates)));
            $tickers = array_values(array_filter($tickers, fn($t) => $t !== ''));
            $ohlc = $this->ohlcRepo->mapOhlcByTickerCodesForDate($execDate, $tickers);

            $filled = 0;
            $totalSlices = 0;
            $sliceDetails = [];

            foreach ($candidates as $cand) {
                if (!is_array($cand)) continue;
                $t = strtoupper((string)($cand['ticker'] ?? ($cand['ticker_code'] ?? '')));
                if ($t === '' || empty($ohlc[$t])) continue;

                $day = $ohlc[$t];
                $lo = $day['low'];
                $hi = $day['high'];
                if ($lo === null || $hi === null) continue;

                $levels = is_array($cand['levels'] ?? null) ? $cand['levels'] : [];
                $sizing = is_array($cand['sizing'] ?? null) ? $cand['sizing'] : [];

                $slices = (int) ($cand['slices'] ?? ($sizing['slices'] ?? 1));
                if ($slices < 1) $slices = 1;

                $prices = $this->deriveSlicePrices($cand, $levels, $slices);
                if (!$prices) continue;

                $hit = 0;
                foreach ($prices as $px) {
                    $totalSlices++;
                    if ($px >= $lo && $px <= $hi) {
                        $filled++;
                        $hit++;
                    }
                }

                $sliceDetails[] = [
                    'ticker' => $t,
                    'slices' => $slices,
                    'slice_prices' => $prices,
                    'day_low' => $lo,
                    'day_high' => $hi,
                    'filled_slices' => $hit,
                ];
            }

            if ($totalSlices > 0) $fill = $filled / $totalSlices;

            $payload = [
                'policy' => $policy,
                'exec_trade_date' => $execDate,
                'slice_details' => $sliceDetails,
            ];
        } else {
            $payload = [
                'policy' => $policy,
                'exec_trade_date' => $execDate,
                'slice_details' => [],
            ];
        }

        return [
            'feasible_rate' => $feasible,
            'fill_rate' => $fill,
            'outcome_rate' => $outcome,
            'payload' => $payload,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function collectCandidates(array $runPayload, bool $includeWatchOnly): array
    {
        $groups = (array) ($runPayload['groups'] ?? []);
        $out = [];
        foreach (['top_picks', 'secondary'] as $g) {
            if (!empty($groups[$g]) && is_array($groups[$g])) {
                foreach ($groups[$g] as $cand) {
                    if (is_array($cand)) $out[] = $cand;
                }
            }
        }
        if ($includeWatchOnly && !empty($groups['watch_only']) && is_array($groups['watch_only'])) {
            foreach ($groups['watch_only'] as $cand) {
                if (is_array($cand)) $out[] = $cand;
            }
        }
        return $out;
    }

    /**
     * Derive slice prices.
     * - Prefer entry_limit_low/high when both exist.
     * - Otherwise fall back to entry_trigger_price.
     *
     * @return float[]
     */
    private function deriveSlicePrices(array $cand, array $levels, int $slices): array
    {
        // Scorecard schema prefers entry_trigger + entry_band.low/high.
        $entry = $this->toFloatOrNull($cand['entry_trigger'] ?? ($cand['entry_trigger_price'] ?? ($levels['entry_trigger_price'] ?? null)));

        $band = is_array($cand['entry_band'] ?? null) ? $cand['entry_band'] : [];
        $low = $this->toFloatOrNull($band['low'] ?? ($cand['entry_limit_low'] ?? ($levels['entry_limit_low'] ?? null)));
        $high = $this->toFloatOrNull($band['high'] ?? ($cand['entry_limit_high'] ?? ($levels['entry_limit_high'] ?? null)));

        if ($slices <= 1) {
            return $entry !== null ? [$entry] : [];
        }

        if ($low !== null && $high !== null && $high >= $low) {
            if ($slices === 2) return [$low, $high];
            $step = ($high - $low) / (float)($slices - 1);
            $out = [];
            for ($i = 0; $i < $slices; $i++) {
                $out[] = $low + ($i * $step);
            }
            return $out;
        }

        return $entry !== null ? [$entry] : [];
    }

    private function toFloatOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_int($v) || is_float($v)) return (float) $v;
        if (is_string($v) && is_numeric(trim($v))) return (float) trim($v);
        return null;
    }
}
