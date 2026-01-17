<?php

namespace App\Services\MarketData;

use App\Repositories\TickerRepository;
use App\Trade\MarketData\Normalize\EodBarNormalizer;
use App\Trade\MarketData\Providers\EodHd\EodhdEodProvider;
use App\Trade\Support\TradeClock;
use Illuminate\Support\Facades\DB;

/**
 * ValidateEodService
 *
 * Fungsi:
 * - Memvalidasi EOD canonical (hasil import Yahoo) dengan provider validator (EODHD) untuk subset ticker.
 * - Bukan untuk 900 ticker. Dipakai setelah kandidat/recommended selesai dari Yahoo.
 *
 * Output:
 * - Summary counts + per-ticker results (OK/WARN/DISAGREE_MAJOR/PRIMARY_NO_DATA/VALIDATOR_*).
 */
final class ValidateEodService
{
    /** @var TickerRepository */
    private $tickers;

    /** @var EodhdEodProvider */
    private $validator;

    /** @var EodBarNormalizer */
    private $normalizer;

    public function __construct(TickerRepository $tickers, EodhdEodProvider $validator)
    {
        $this->tickers = $tickers;
        $this->validator = $validator;
        $this->normalizer = new EodBarNormalizer(TradeClock::tz());
    }

    /**
     * @param string $tradeDate YYYY-MM-DD
     * @param string[] $tickerCodes ex: ['BBCA','BBRI']
     * @return array{summary:array<string,mixed>, rows:array<int,array<string,mixed>>, notes:string[]}
     */
    public function run(string $tradeDate, array $tickerCodes, ?int $maxTickers = null, ?int $canonicalRunId = null): array
    {
        $notes = [];

        $tradeDate = trim($tradeDate);
        if ($tradeDate === '') {
            return [
                'summary' => ['status' => 'FAILED', 'reason' => 'EMPTY_DATE'],
                'rows' => [],
                'notes' => ['empty_date'],
            ];
        }

        // Enforce caps (validator config + provider config + option)
        $cfgMax = (int) config('trade.market_data.validator.max_tickers', 20);
        $callLimit = (int) config('trade.market_data.providers.eodhd.daily_call_limit', 20);
        $optMax = $maxTickers !== null ? (int) $maxTickers : $cfgMax;
        $cap = max(1, min($cfgMax, $callLimit, $optMax));

        // Normalize ticker codes, unique, upper
        $codes = [];
        foreach ($tickerCodes as $c) {
            $c = strtoupper(trim((string) $c));
            if ($c !== '') $codes[$c] = true;
        }
        $codes = array_keys($codes);

        if (!$codes) {
            return [
                'summary' => ['status' => 'FAILED', 'reason' => 'EMPTY_TICKERS'],
                'rows' => [],
                'notes' => ['empty_tickers'],
            ];
        }

        if (count($codes) > $cap) {
            $notes[] = 'trimmed_tickers: requested=' . count($codes) . ', cap=' . $cap;
            $codes = array_slice($codes, 0, $cap);
        }

        // Resolve ticker_id by calling existing repository (safe even if it returns 900 rows).
        $active = $this->tickers->listActive(null);
        $codeToId = [];
        foreach ($active as $t) {
            $code = strtoupper((string) ($t['ticker_code'] ?? ''));
            $id = (int) ($t['ticker_id'] ?? 0);
            if ($code !== '' && $id > 0) $codeToId[$code] = $id;
        }

        // Determine which canonical run to validate against.
        $runId = $canonicalRunId ?: $this->findLatestSuccessfulImportRunIdForDate($tradeDate);
        if (!$runId) {
            return [
                'summary' => ['status' => 'FAILED', 'reason' => 'NO_CANONICAL_RUN', 'trade_date' => $tradeDate],
                'rows' => [],
                'notes' => ['no_success_import_run_for_date'],
            ];
        }

        // Load primary (canonical) closes for tickers on trade date.
        $tickerIds = [];
        foreach ($codes as $c) {
            $id = (int) ($codeToId[$c] ?? 0);
            if ($id > 0) $tickerIds[] = $id;
        }
        $tickerIds = array_values(array_unique($tickerIds));

        $primaryByTickerId = $this->loadCanonicalByRunAndDate($runId, $tradeDate, $tickerIds);

        $disagreeMajorPct = (float) config('trade.market_data.validator.disagree_major_pct', 1.5);
        // Simple warn threshold: half of disagree threshold (but at least 0.2%)
        $warnPct = max(0.2, $disagreeMajorPct / 2.0);

        $rows = [];
        $counts = [
            'OK' => 0,
            'WARN' => 0,
            'DISAGREE_MAJOR' => 0,
            'PRIMARY_NO_DATA' => 0,
            'VALIDATOR_NO_DATA' => 0,
            'VALIDATOR_ERROR' => 0,
            'INVALID_TICKER' => 0,
        ];

        foreach ($codes as $code) {
            $tickerId = (int) ($codeToId[$code] ?? 0);
            if ($tickerId <= 0) {
                $counts['INVALID_TICKER']++;
                $rows[] = [
                    'ticker_code' => $code,
                    'ticker_id' => null,
                    'trade_date' => $tradeDate,
                    'primary_close' => null,
                    'validator_close' => null,
                    'diff_pct' => null,
                    'status' => 'INVALID_TICKER',
                    'primary_source' => 'canonical',
                    'validator_source' => $this->validator->name(),
                    'error_code' => 'INVALID_TICKER',
                    'error_msg' => 'ticker_code not found in active list',
                ];
                continue;
            }

            $primary = $primaryByTickerId[$tickerId] ?? null;
            $primaryClose = $primary ? (isset($primary['close']) ? (float) $primary['close'] : null) : null;

            // Fetch validator bar (single-day range)
            $symbol = $this->validator->mapTickerCodeToSymbol($code);
            $vres = $this->validator->fetch($symbol, $tradeDate, $tradeDate);

            $validatorClose = null;
            $vErrCode = null;
            $vErrMsg = null;

            if ($vres->errorCode) {
                $vErrCode = (string) $vres->errorCode;
                $vErrMsg = (string) ($vres->errorMsg ?? '');
            } else {
                // Normalize and pick matching date
                foreach ($vres->bars as $raw) {
                    $norm = $this->normalizer->normalize($raw);
                    if (!$norm) continue;
                    if ($norm->tradeDate !== $tradeDate) continue;
                    $validatorClose = $norm->close !== null ? (float) $norm->close : null;
                    break;
                }
                if ($validatorClose === null) {
                    $vErrCode = 'VALIDATOR_NO_DATA';
                    $vErrMsg = 'no bar for trade_date';
                }
            }

            $status = 'OK';
            $diffPct = null;

            if ($primaryClose === null) {
                $status = 'PRIMARY_NO_DATA';
                $counts['PRIMARY_NO_DATA']++;
            }

            if ($vErrCode !== null) {
                if ($vErrCode === 'VALIDATOR_NO_DATA') {
                    $status = $status === 'PRIMARY_NO_DATA' ? 'PRIMARY_NO_DATA' : 'VALIDATOR_NO_DATA';
                    $counts['VALIDATOR_NO_DATA']++;
                } else {
                    $status = $status === 'PRIMARY_NO_DATA' ? 'PRIMARY_NO_DATA' : 'VALIDATOR_ERROR';
                    $counts['VALIDATOR_ERROR']++;
                }
            }

            if ($primaryClose !== null && $validatorClose !== null && $primaryClose > 0) {
                $diffPct = abs($primaryClose - $validatorClose) / $primaryClose * 100.0;

                if ($diffPct >= $disagreeMajorPct) {
                    $status = 'DISAGREE_MAJOR';
                    $counts['DISAGREE_MAJOR']++;
                } elseif ($diffPct >= $warnPct) {
                    $status = 'WARN';
                    $counts['WARN']++;
                } else {
                    // Only count OK if no earlier error status
                    if ($status === 'OK') $counts['OK']++;
                }
            } else {
                if ($status === 'OK') $counts['OK']++;
            }

            $rows[] = [
                'ticker_code' => $code,
                'ticker_id' => $tickerId,
                'trade_date' => $tradeDate,
                'primary_close' => $primaryClose,
                'validator_close' => $validatorClose,
                'diff_pct' => $diffPct !== null ? round($diffPct, 4) : null,
                'status' => $status,
                'primary_source' => 'canonical',
                'validator_source' => $this->validator->name(),
                'canonical_run_id' => $runId,
                'error_code' => $vErrCode,
                'error_msg' => $vErrMsg,
            ];
        }

        // Summary
        $total = count($rows);
        $summary = [
            'status' => 'OK',
            'trade_date' => $tradeDate,
            'canonical_run_id' => $runId,
            'validated' => $total,
            'caps' => ['cap' => $cap, 'cfg_max' => $cfgMax, 'daily_call_limit' => $callLimit, 'opt_max' => $optMax],
            'thresholds' => ['warn_pct' => $warnPct, 'disagree_major_pct' => $disagreeMajorPct],
            'counts' => $counts,
        ];

        if ($counts['DISAGREE_MAJOR'] > 0 || $counts['VALIDATOR_ERROR'] > 0) {
            $summary['status'] = 'WARN';
        }

        return [
            'summary' => $summary,
            'rows' => $rows,
            'notes' => $notes,
        ];
    }

    private function findLatestSuccessfulImportRunIdForDate(string $tradeDate): ?int
    {
        $row = DB::table('md_runs')
            ->select('run_id')
            ->where('job', 'import_eod')
            ->where('status', 'SUCCESS')
            ->where('effective_start_date', '<=', $tradeDate)
            ->where('effective_end_date', '>=', $tradeDate)
            ->orderByDesc('run_id')
            ->first();

        if (!$row || !isset($row->run_id)) return null;
        return (int) $row->run_id;
    }

    /**
     * @param int[] $tickerIds
     * @return array<int,array{close:float|null, chosen_source:string|null}>
     */
    private function loadCanonicalByRunAndDate(int $runId, string $tradeDate, array $tickerIds): array
    {
        if (!$tickerIds) return [];

        $rows = DB::table('md_canonical_eod')
            ->select('ticker_id', 'close', 'chosen_source')
            ->where('run_id', $runId)
            ->where('trade_date', $tradeDate)
            ->whereIn('ticker_id', $tickerIds)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $tid = (int) ($r->ticker_id ?? 0);
            if ($tid <= 0) continue;
            $out[$tid] = [
                'close' => $r->close !== null ? (float) $r->close : null,
                'chosen_source' => $r->chosen_source !== null ? (string) $r->chosen_source : null,
            ];
        }
        return $out;
    }
}
