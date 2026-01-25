<?php

namespace App\Console\Commands;

use App\Services\Portfolio\PortfolioService;
use Illuminate\Console\Command;

class PortfolioIngestTrade extends Command
{
    protected $signature = 'portfolio:ingest-trade
        {--account=1 : Account ID}
        {--ticker= : Ticker code (e.g. BBCA)}
        {--ticker_id= : Ticker ID}
        {--date= : Trade date (YYYY-MM-DD)}
        {--side= : BUY|SELL}
        {--qty= : Qty (shares)}
        {--price= : Price}
        {--external_ref= : External ref for idempotency}
        {--broker_ref= : Broker ref}
        {--source=manual : Source}
        {--currency=IDR : Currency}
        {--meta= : JSON meta (optional)}';

    protected $description = 'Ingest single trade/fill into portfolio (FIFO lots + derived positions)';

    private PortfolioService $svc;

    public function __construct(PortfolioService $svc)
    {
        parent::__construct();
        $this->svc = $svc;
    }

    public function handle()
    {
        $meta = $this->option('meta');
        $metaArr = null;
        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) $metaArr = $decoded;
        }

        $payload = [
            'account_id' => (int)$this->option('account'),
            'ticker_id' => $this->option('ticker_id') !== null ? (int)$this->option('ticker_id') : null,
            'symbol' => $this->option('ticker') !== null ? (string)$this->option('ticker') : null,
            'trade_date' => (string)$this->option('date'),
            'side' => (string)$this->option('side'),
            'qty' => (int)$this->option('qty'),
            'price' => (float)$this->option('price'),
            'external_ref' => $this->option('external_ref') !== null ? (string)$this->option('external_ref') : null,
            'broker_ref' => $this->option('broker_ref') !== null ? (string)$this->option('broker_ref') : null,
            'source' => (string)$this->option('source'),
            'currency' => (string)$this->option('currency'),
            'meta' => $metaArr,
        ];

        // remove nulls to keep TradeInput normalization clean
        foreach ($payload as $k => $v) {
            if ($v === null) unset($payload[$k]);
        }

        $res = $this->svc->ingestTrade($payload);
        if (!$res['ok']) {
            $this->error('ERROR: ' . ($res['error'] ?? 'unknown'));
            return 1;
        }

        $this->info('OK trade_id=' . (string)($res['trade_id'] ?? 0) . ' created=' . ((bool)($res['created'] ?? false) ? 'yes' : 'no'));
        return 0;
    }
}
