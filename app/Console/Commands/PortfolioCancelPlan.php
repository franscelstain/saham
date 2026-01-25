<?php

namespace App\Console\Commands;

use App\Services\Portfolio\PortfolioService;
use Illuminate\Console\Command;

class PortfolioCancelPlan extends Command
{
    protected $signature = 'portfolio:cancel-plan {plan_id : Plan ID} {--reason=manual_cancel : Reason string}';
    protected $description = 'Cancel a portfolio plan (status=CANCELLED) and emit PLAN_CANCELLED event.';

    public function handle(PortfolioService $svc): int
    {
        $planId = (int) $this->argument('plan_id');
        $reason = (string) $this->option('reason');

        $res = $svc->cancelPlan($planId, $reason);
        if (($res['ok'] ?? false) !== true) {
            $this->error('Cancel failed: ' . ($res['error'] ?? 'unknown'));
            return 1;
        }

        if (!empty($res['already_cancelled'])) {
            $this->info('Already cancelled: plan_id=' . $planId);
            return 0;
        }

        $this->info('Cancelled: plan_id=' . $planId);
        return 0;
    }
}
