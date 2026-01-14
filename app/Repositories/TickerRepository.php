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
}
