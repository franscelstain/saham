<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TickerRepository
{
    /**
     * Return array of ['ticker_id'=>int,'ticker_code'=>string]
     */
    public function listActive(?string $tickerCode = null): array
    {
        $q = DB::table('tickers')
            ->where('is_deleted', 0)
            ->select(['ticker_id', 'ticker_code'])
            ->orderBy('ticker_id');

        if ($tickerCode) {
            $q->where('ticker_code', $tickerCode);
        }

        $rows = $q->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'ticker_id' => (int) $r->ticker_id,
                'ticker_code' => (string) $r->ticker_code,
            ];
        }
        return $out;
    }

    public function resolveIdByCode(string $tickerCode): ?int
    {
        $tickerCode = strtoupper(trim($tickerCode));
        if ($tickerCode === '') return null;

        $row = DB::table('tickers')
            ->select('ticker_id')
            ->where('is_deleted', 0)
            ->where('ticker_code', $tickerCode)
            ->first();

        return $row && isset($row->ticker_id) ? (int) $row->ticker_id : null;
    }

    /**
     * Resolve ticker_id by ticker_code for many tickers.
     *
     * @param array<int,string> $tickerCodes
     * @return array<string,int> map ticker_code => ticker_id
     */
    public function resolveIdsByCodes(array $tickerCodes): array
    {
        $codes = [];
        foreach ($tickerCodes as $c) {
            $c = strtoupper(trim((string)$c));
            if ($c !== '') $codes[$c] = true;
        }
        $codes = array_keys($codes);
        if (empty($codes)) return [];

        $rows = DB::table('tickers')
            ->select(['ticker_id','ticker_code'])
            ->where('is_deleted', 0)
            ->whereIn('ticker_code', $codes)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (!isset($r->ticker_code) || !isset($r->ticker_id)) continue;
            $out[(string)$r->ticker_code] = (int)$r->ticker_id;
        }
        return $out;
    }
}
