<?php

namespace App\Trade\Portfolio\Policies;

use App\DTO\Portfolio\TradeInput;
use App\Repositories\MarketCalendarRepository;

class WeeklySwingPolicy implements PortfolioPolicy
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
        $breaches = [];

        $intent = strtoupper((string)($plan->intent ?? ''));
        $isBuyIntent = ($intent === 'ENTRY' || $intent === 'BUY' || $intent === 'ADD' || str_starts_with($intent, 'BUY_'));
        if (!$isBuyIntent) {
            $breaches[] = 'INTENT_NOT_BUY';
        }

        // cooldown after SL exit
        $cooldown = (int)($this->cfg['cooldown_days_after_sl'] ?? 0);
        $lastSl = isset($ctx['last_exit_sl_trade_date']) ? (string)$ctx['last_exit_sl_trade_date'] : null;
        if ($cooldown > 0 && $lastSl) {
            $allowed = $this->cal->addTradingDays($lastSl, $cooldown);
            if ($allowed && $trade->tradeDate < $allowed) {
                $breaches[] = 'COOLDOWN_AFTER_SL';
            }
        }

        // no averaging down by default
        $preQty = (int)($ctx['pre_qty'] ?? 0);
        $preAvg = $ctx['pre_avg_cost'] !== null ? (float)$ctx['pre_avg_cost'] : null;
        $noAvgDown = (bool)($this->cfg['no_averaging_down'] ?? true);
        if ($noAvgDown && $preQty > 0 && $preAvg !== null && $trade->price < $preAvg) {
            $breaches[] = 'NO_AVERAGING_DOWN';
        }

        return $breaches;
    }

    /**
     * @param array<string,mixed> $pos
     * @param array<string,mixed> $ctx
     * @return array<int,array<string,mixed>>
     */
    public function eodRiskEvents(object $plan, array $pos, float $close, string $tradeDate, array $ctx): array
    {
        $events = [];

        $qty = (int)($pos['qty'] ?? 0);
        $avg = (float)($pos['avg_price'] ?? 0);
        if ($qty <= 0 || $avg <= 0 || $close <= 0) return $events;

        $risk = $ctx['risk'] ?? null;
        $sl = null;
        if (is_array($risk)) {
            if (isset($risk['sl_price'])) $sl = (float)$risk['sl_price'];
            elseif (isset($risk['stop_loss_price'])) $sl = (float)$risk['stop_loss_price'];
        }
        if ($sl === null || $sl <= 0 || $sl >= $avg) return $events;

        $r = $avg - $sl;
        $beAtR = (float)($ctx['be_at_r'] ?? 1.0);
        $beTrigger = $avg + ($r * $beAtR);

        $beArmed = (bool)($ctx['be_armed'] ?? false);
        $slMoved = (bool)($ctx['sl_moved'] ?? false);

        if (!$beArmed && $close >= $beTrigger) {
            $events[] = [
                'event_type' => 'BE_ARMED',
                'qty_before' => $qty,
                'qty_after' => $qty,
                'price' => $close,
                'reason_code' => 'RISK',
                'notes' => 'break_even_trigger_reached',
                'payload_json' => json_encode([
                    'close' => $close,
                    'avg_price' => $avg,
                    'sl_price' => $sl,
                    'r' => $r,
                    'be_at_r' => $beAtR,
                    'be_trigger' => $beTrigger,
                    'trade_date' => $tradeDate,
                ], JSON_UNESCAPED_SLASHES),
            ];
        }

        // Minimal deterministic move: once BE_ARMED, move SL to entry (avg_price)
        if (($beArmed || ($close >= $beTrigger)) && !$slMoved) {
            $events[] = [
                'event_type' => 'SL_MOVED',
                'qty_before' => $qty,
                'qty_after' => $qty,
                'price' => $close,
                'reason_code' => 'RISK',
                'notes' => 'move_sl_to_break_even',
                'payload_json' => json_encode([
                    'from_sl' => $sl,
                    'to_sl' => $avg,
                    'trade_date' => $tradeDate,
                ], JSON_UNESCAPED_SLASHES),
            ];
        }

        return $events;
    }
}
