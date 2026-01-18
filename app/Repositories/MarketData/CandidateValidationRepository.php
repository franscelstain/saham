<?php

namespace App\Repositories\MarketData;

use Illuminate\Support\Facades\DB;
use App\DTO\MarketData\CandidateValidation;

/**
 * CandidateValidationRepository
 *
 * SRP: persistence & read model untuk hasil validasi subset (EODHD).
 */
final class CandidateValidationRepository
{
    /**
     * Upsert validation rows produced by ValidateEodService.
     *
     * @param string $tradeDate
     * @param array<int,array<string,mixed>> $rows
     */
    public function upsertFromValidateRows(string $tradeDate, array $rows): int
    {
        if (!$rows) return 0;

        $now = now();
        $payload = [];

        foreach ($rows as $r) {
            $tickerId = (int) ($r['ticker_id'] ?? 0);
            if ($tickerId <= 0) continue;

            $provider = (string) ($r['validator_source'] ?? 'EODHD');
            if ($provider === '') $provider = 'EODHD';

            $payload[] = [
                'trade_date' => $tradeDate,
                'ticker_id' => $tickerId,
                'provider' => strtoupper($provider),
                'status' => (string) ($r['status'] ?? 'UNKNOWN'),
                'canonical_run_id' => $r['canonical_run_id'] ?? null,
                'primary_close' => $r['primary_close'] ?? null,
                'validator_close' => $r['validator_close'] ?? null,
                'diff_pct' => $r['diff_pct'] ?? null,
                'error_code' => $r['error_code'] ?? null,
                'error_msg' => $r['error_msg'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!$payload) return 0;

        DB::table('md_candidate_validations')->upsert(
            $payload,
            ['trade_date', 'ticker_id', 'provider'],
            ['status', 'canonical_run_id', 'primary_close', 'validator_close', 'diff_pct', 'error_code', 'error_msg', 'updated_at']
        );

        return count($payload);
    }

    /**
     * Load validation results map by ticker code for a specific date.
     *
     * @param string $tradeDate
     * @param string[] $tickerCodes
     * @param string $provider
     * @return array<string,array{status:string,diff_pct:?float,primary_close:?float,validator_close:?float,provider:string,updated_at:?string,error_code:?string}>
     */
    public function mapByDateAndCodes(string $tradeDate, array $tickerCodes, string $provider = 'EODHD'): array
    {
        $codes = [];
        foreach ($tickerCodes as $c) {
            $c = strtoupper(trim((string) $c));
            if ($c !== '') $codes[$c] = true;
        }
        $codes = array_keys($codes);
        if (!$codes) return [];

        $provider = strtoupper(trim($provider));
        if ($provider === '') $provider = 'EODHD';

        $rows = DB::table('md_candidate_validations as v')
            ->join('tickers as t', function ($join) {
                $join->on('v.ticker_id', '=', 't.ticker_id')
                    ->where('t.is_deleted', 0);
            })
            ->where('v.trade_date', $tradeDate)
            ->where('v.provider', $provider)
            ->whereIn('t.ticker_code', $codes)
            ->select([
                't.ticker_code',
                'v.status',
                'v.diff_pct',
                'v.primary_close',
                'v.validator_close',
                'v.provider',
                'v.error_code',
                'v.updated_at',
            ])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $code = strtoupper((string) $r->ticker_code);
            $out[$code] = [
                'status' => (string) $r->status,
                'diff_pct' => $r->diff_pct !== null ? (float) $r->diff_pct : null,
                'primary_close' => $r->primary_close !== null ? (float) $r->primary_close : null,
                'validator_close' => $r->validator_close !== null ? (float) $r->validator_close : null,
                'provider' => (string) $r->provider,
                'error_code' => $r->error_code !== null ? (string) $r->error_code : null,
                'updated_at' => $r->updated_at !== null ? (string) $r->updated_at : null,
            ];
        }

        return $out;
    }

    /**
     * DTO variant of mapByDateAndCodes.
     *
     * @param string $tradeDate
     * @param string[] $tickerCodes
     * @param string $provider
     * @return array<string,CandidateValidation>
     */
    public function mapDtoByDateAndCodes(string $tradeDate, array $tickerCodes, string $provider = 'EODHD'): array
    {
        $rows = $this->mapByDateAndCodes($tradeDate, $tickerCodes, $provider);
        $out = [];
        foreach ($rows as $code => $r) {
            $dto = new CandidateValidation();
            $dto->runId = 0;
            $dto->tradeDate = $tradeDate;
            $dto->tickerId = 0;
            $dto->tickerCode = (string) $code;
            $dto->status = (string) ($r['status'] ?? '');
            $dto->primaryClose = $r['primary_close'] ?? null;
            $dto->validatorClose = $r['validator_close'] ?? null;
            $dto->diffPct = $r['diff_pct'] ?? null;
            $dto->note = null;
            $out[(string) $code] = $dto;
        }
        return $out;
    }
}
