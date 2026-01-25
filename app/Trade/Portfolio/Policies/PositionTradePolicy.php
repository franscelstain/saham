<?php

namespace App\Trade\Portfolio\Policies;

use App\DTO\Portfolio\TradeInput;
use App\Repositories\MarketCalendarRepository;

/**
 * Minimal policy for POSITION_TRADE (carry-only / manual management).
 *
 * This policy exists to satisfy docs/PORTFOLIO.md strategy policy completeness.
 *
 * Default stance:
 * - Enabled by default, but enforcement is soft.
 * - Optionally emits POLICY_BREACH when a BUY attempts to open a new entry, if configured.
 */
class PositionTradePolicy implements PortfolioPolicy
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

        // Optional: disallow opening new entries under POSITION_TRADE (carry-only).
        $allowNew = (bool)($this->cfg['allow_new_entries'] ?? false);
        $preQty = (int)($ctx['pre_qty'] ?? 0);
        if (!$allowNew && $preQty <= 0) {
            $breaches[] = 'NO_NEW_ENTRY';
        }

        // Optional: no averaging down.
        $preAvg = $ctx['pre_avg_cost'] !== null ? (float)$ctx['pre_avg_cost'] : null;
        $noAvgDown = (bool)($this->cfg['no_averaging_down'] ?? false);
        if ($noAvgDown && $preQty > 0 && $preAvg !== null && $trade->price < $preAvg) {
            $breaches[] = 'NO_AVERAGING_DOWN';
        }

        // Optional: cooldown after SL exit (rare for carry-only, but supported).
        $cooldown = (int)($this->cfg['cooldown_days_after_sl'] ?? 0);
        $lastSl = isset($ctx['last_exit_sl_trade_date']) ? (string)$ctx['last_exit_sl_trade_date'] : null;
        if ($cooldown > 0 && $lastSl) {
            $allowed = $this->cal->addTradingDays($lastSl, $cooldown);
            if ($allowed && $trade->tradeDate < $allowed) {
                $breaches[] = 'COOLDOWN_AFTER_SL';
            }
        }

        return $breaches;
    }

    /**
     * No-op risk events by default.
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
        return [];
    }
}
