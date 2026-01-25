<?php

namespace App\Http\Controllers;

use App\Services\Portfolio\PortfolioService;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    private PortfolioService $svc;

    public function __construct(PortfolioService $svc)
    {
        $this->svc = $svc;
    }

    public function positions(Request $request)
    {
        $accountId = (int) $request->query('account_id', 1);
        $data = $this->svc->listPositions($accountId);
        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function ingestTrade(Request $request)
    {
        $payload = $request->all();
        $res = $this->svc->ingestTrade($payload);
        $status = $res['ok'] ? 200 : 422;
        return response()->json($res, $status);
    }

    public function upsertPlan(Request $request)
    {
        $payload = $request->all();
        $res = $this->svc->upsertPlan($payload);
        $status = $res['ok'] ? 200 : 422;
        return response()->json($res, $status);
    }

    public function valueEod(Request $request)
    {
        $tradeDate = (string) $request->query('trade_date', '');
        $accountId = (int) $request->query('account_id', 1);
        $res = $this->svc->valueEod($tradeDate, $accountId);
        $status = $res['ok'] ? 200 : 422;
        return response()->json($res, $status);
    }
}
