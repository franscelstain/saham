<?php

namespace App\Http\Controllers;

use App\Services\YahooFinanceService;
use App\Services\YahooOhlcImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class YahooFinanceController extends Controller
{
    private $tickers;
    
    public function __construct()
    {
        $this->tickers = [
            "ADMR.JK","ADRO.JK","AISA.JK","ANTM.JK","APEX.JK","ARCI.JK","ASII.JK","BBCA.JK","BBNI.JK","BBRI.JK","BBTN.JK","BBYB.JK","BKSL.JK","BMRI.JK","BREN.JK","BRIS.JK","BRMS.JK","BRPT.JK","BUMI.JK","BWPT.JK","CARE.JK","CDIA.JK","CENT.JK","COCO.JK","COIN.JK","CUAN.JK","DEWA.JK","DKFT.JK","DSNG.JK","ELIT.JK","ELSA.JK","EMTK.JK","ENRG.JK","ERAA.JK","FORE.JK","GIAA.JK","GOTO.JK","GPRA.JK","GZCO.JK","HEAL.JK","HRTA.JK","INDX.JK","IMPC.JK","INET.JK","ISAT.JK","JPFA.JK","JATI.JK","KKGI.JK","KLAS.JK","KRAS.JK","LPPF.JK","MBMA.JK","MEDC.JK","MNCN.JK","NCKL.JK","OMED.JK","PGAS.JK","PNLF.JK","PPRE.JK","PTRO.JK","RAJA.JK","RATU.JK","REAL.JK","SCMA.JK","SMBR.JK","TINS.JK","TLKM.JK","TOWR.JK","TRIM.JK","UNVR.JK","WIFI.JK","WINS.JK"
        ];
    }

     /**
     * Return historical OHLC data from Yahoo Finance
     */
    
    public function history(Request $request, YahooOhlcImportService $svc)
    {
        $start = Carbon::createFromFormat('Y-m-d', $request->query('start'));
        $end   = Carbon::createFromFormat('Y-m-d', $request->query('end'));

        $interval = $request->query('interval', '1d');
        $ticker   = $request->query('ticker'); // optional

        if ($end->lt($start)) {
            return response()->json(['message' => 'end harus >= start'], 422);
        }

        $stats = $svc->import($start, $end, $interval, $ticker);

        return response()->json($stats);
    }

    public function names(Request $request, YahooFinanceService $yf)
    {

        $data = $yf->companyNames((array) $this->tickers);

        $now = Carbon::now();

        $rows = array_map(function ($r) use ($now) {
            $code = $this->normalizeTickerCode($r['symbol']);
            $name = $r['longName'] ?? ($r['shortName'] ?? $code);
            return [
                'ticker_code'  => $code,
                'company_name' => $name,
                'is_deleted'   => 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }, $data);

        DB::table('tickers')->upsert(
            $rows,
            ['ticker_code'],
            ['company_name', 'is_deleted', 'updated_at']
        );

        return response()->json($rows);
    }

    public function normalizeTickerCode(string $code): string
    {
        $code = trim($code);

        // buang prefix yang kadang muncul (opsional)
        $code = str_replace(['IDX:', 'idx:'], '', $code);

        // buang .JK (case-insensitive) kalau ada di akhir
        $code = preg_replace('/\.jk$/i', '', $code);

        // rapihin: uppercase
        return strtoupper($code);
    }
}
