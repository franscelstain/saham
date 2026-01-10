<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Compute\ComputeEodService;

class ComputeEod extends Command
{
    protected $signature = 'trade:compute-eod {tradeDate? : YYYY-MM-DD (optional)}';
    protected $description = 'Compute indikator EOD (holiday-aware) + decision/volume + signal age';

    protected $service;

    public function __construct(ComputeEodService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $tradeDate = $this->argument('tradeDate');
        $result = $this->service->run($tradeDate, null);

        $this->line(json_encode($result));
        return 0;
    }
}
