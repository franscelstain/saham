<?php

namespace App\Trade\Portfolio\Policies;

use App\DTO\Portfolio\TradeInput;
use App\Repositories\MarketCalendarRepository;

/**
 * Minimal policy for INTRADAY_LIGHT.
 *
 * Goals (per docs/PORTFOLIO.md strict):
 * - Provide explicit policy handler for strategy code.
 * - Allow optional enforcement via config (disabled-by-config).
 * - Keep behavior deterministic and low-risk (no hard-block; emits POLICY_BREACH only).
 */
class IntradayLightPolicy implements PortfolioPolicy
{
    private MarketCalendarRepository $cal;
    /** @var array<string,mixed> */
    private array $cfg;

    /**
     * @param array<string,mixed> $cfg
     */
    public function __construct(MarketCalendarRepository $cal, array $cfg)
    {
        $this->cal = $cal;
        $this->cfg = $cfg;
    }

    /**
     * @param array<string,mixed> $ctx
     * @return string[]
     */
    public function validateBuy(object $plan, TradeInput $trade, array $ctx): array
    {
        if (!(bool)($this->cfg['enabled'] ?? true)) {
            return [];
        }

        $breaches = [];

        $intent = strtoupper((string)($plan->intent ?? ''));
        $isBuyIntent = ($intent === 'ENTRY' || $intent === 'BUY' || $intent === 'ADD' || str_starts_with($intent, 'BUY_'));
        if (!$isBuyIntent) {
            $breaches[] = 'INTENT_NOT_BUY';
        }

        // Optional: cooldown after SL exit
        $cooldown = (int)($this->cfg['cooldown_days_after_sl'] ?? 0);
        $lastSl = isset($ctx['last_exit_sl_trade_date']) ? (string)$ctx['last_exit_sl_trade_date'] : null;
        if ($cooldown > 0 && $lastSl) {
            $allowed = $this->cal->addTradingDays($lastSl, $cooldown);
            if ($allowed && $trade->tradeDate < $allowed) {
                $breaches[] = 'COOLDOWN_AFTER_SL';
            }
        }

        // Optional: no averaging down
        $preQty = (int)($ctx['pre_qty'] ?? 0);
        $preAvg = $ctx['pre_avg_cost'] !== null ? (float)$ctx['pre_avg_cost'] : null;
        $noAvgDown = (bool)($this->cfg['no_averaging_down'] ?? false);
        if ($noAvgDown && $preQty > 0 && $preAvg !== null && $trade->price < $preAvg) {
            $breaches[] = 'NO_AVERAGING_DOWN';
        }

        return $breaches;
    }

    /**
     * Minimal/no-op risk events (disabled by default).
     *
     * @param array<string,mixed> $pos
     * @param array<string,mixed> $ctx
     * @return array<int,array<string,mixed>>
     */
    public function eodRiskEvents(object $plan, array $pos, float $close, string $tradeDate, array $ctx): array
    {
        if (!(bool)($this->cfg['enabled'] ?? true)) {
            return [];
        }
        if (!(bool)($this->cfg['enable_risk_events'] ?? false)) {
            return [];
        }

        // Keep minimal behavior: no EOD risk automation for intraday-light by default.
        return [];
    }
}
