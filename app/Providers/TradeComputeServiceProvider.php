<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Trade\Compute\Config\DecisionGuardrails;
use App\Trade\Compute\Config\PatternThresholds;
use App\Trade\Compute\Classifiers\VolumeLabelClassifier;

class TradeComputeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(DecisionGuardrails::class, function () {
            return DecisionGuardrails::fromArray((array) config('trade.indicators.decision_guardrails', []));
        });

        $this->app->singleton(PatternThresholds::class, function () {
            return PatternThresholds::fromArray((array) config('trade.indicators.pattern_thresholds', []));
        });

        // Kalau nanti VolumeLabelClassifier kamu refactor jadi injected thresholds:
        $this->app->singleton(VolumeLabelClassifier::class, function () {
            return new VolumeLabelClassifier(
                (array) config('trade.indicators.volume_ratio_thresholds', [0.4,0.7,1.0,1.5,2.0,3.0,4.0])
            );
        });
    }
}
