<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TickerController extends Controller
{
    public function historicalOhlc(Request $request)
    {
        $symbol   = 'BBCA.JK';
        $period1  = strtotime('2024-01-01');
        $period2  = time();
        
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}"
            . "?period1={$period1}"
            . "&period2={$period2}"
            . "&interval=1d&events=history&includeAdjustedClose=true";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0', // penting untuk beberapa endpoint
        ]);
        $csv = curl_exec($ch);
        curl_close($ch);

        $rows   = array_map('str_getcsv', explode("\n", trim($csv)));
        $header = array_shift($csv);

        return response()->json($header);

        $data = [];
        foreach ($rows as $row) {
            if (count($row) !== count($header)) continue;
            $item = array_combine($header, $row);
            // misal: pakai Close, abaikan Adj Close kalau mau mirip Ajaib
            $data[] = [
                'date'   => $item['Date'],
                'open'   => (float)$item['Open'],
                'high'   => (float)$item['High'],
                'low'    => (float)$item['Low'],
                'close'  => (float)$item['Close'],
                'volume' => (int)$item['Volume'],
            ];
        }

        return response()->json($data);
    }
}