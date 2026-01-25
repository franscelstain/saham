<?php

namespace App\DTO\Portfolio;

/**
 * DTO input plan snapshot dari watchlist execution intent.
 */
final class PlanInput
{
    public int $accountId;
    public int $tickerId;
    public string $strategyCode;
    public string $asOfTradeDate;
    public string $intent;
    public float $allocPct;
    public string $planVersion;
    /** @var array<string,mixed> */
    public array $planSnapshot;

    private function __construct() {}

    /**
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $x = new self();
        $x->accountId = (int)($a['account_id'] ?? 1);
        $x->tickerId = (int)($a['ticker_id'] ?? 0);
        $x->strategyCode = (string)($a['strategy_code'] ?? ($a['policy_code'] ?? ''));
        $x->asOfTradeDate = (string)($a['as_of_trade_date'] ?? ($a['trade_date'] ?? ''));
        $x->intent = (string)($a['intent'] ?? 'ENTRY');
        $x->allocPct = (float)($a['alloc_pct'] ?? 0);
        $x->planVersion = (string)($a['plan_version'] ?? 'v1');
        $snap = $a['plan_snapshot'] ?? $a['plan_snapshot_json'] ?? $a;
        $x->planSnapshot = is_array($snap) ? $snap : $a;
        return $x;
    }
}
