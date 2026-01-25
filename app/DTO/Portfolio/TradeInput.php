<?php

namespace App\DTO\Portfolio;

/**
 * DTO input trade/fill.
 * No business logic beyond normalization.
 */
final class TradeInput
{
    public int $accountId;
    public int $tickerId;
    public string $symbol;
    public string $tradeDate; // YYYY-MM-DD
    public string $side; // BUY|SELL
    public int $qty; // shares
    public float $price;
    public ?string $externalRef;
    public ?string $brokerRef;
    public ?string $source;
    public ?string $currency;
    /** @var array<string,mixed>|null */
    public ?array $meta;

    private function __construct() {}

    /**
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $x = new self();
        $x->accountId = (int)($a['account_id'] ?? 1);
        $x->tickerId = (int)($a['ticker_id'] ?? 0);
        $x->symbol = (string)($a['symbol'] ?? '');
        $x->tradeDate = (string)($a['trade_date'] ?? '');
        $x->side = strtoupper((string)($a['side'] ?? ''));
        $x->qty = (int)($a['qty'] ?? 0);
        $x->price = (float)($a['price'] ?? 0);
        $x->externalRef = isset($a['external_ref']) ? (string)($a['external_ref'] ?? '') : null;
        $x->externalRef = ($x->externalRef !== null && trim($x->externalRef) === '') ? null : $x->externalRef;
        $x->brokerRef = isset($a['broker_ref']) ? (string)($a['broker_ref'] ?? '') : null;
        $x->brokerRef = ($x->brokerRef !== null && trim($x->brokerRef) === '') ? null : $x->brokerRef;
        $x->source = isset($a['source']) ? (string)($a['source'] ?? '') : null;
        $x->source = ($x->source !== null && trim($x->source) === '') ? null : $x->source;
        $x->currency = isset($a['currency']) ? (string)($a['currency'] ?? '') : null;
        $x->currency = ($x->currency !== null && trim($x->currency) === '') ? null : $x->currency;

        $meta = $a['meta'] ?? $a['meta_json'] ?? null;
        $x->meta = is_array($meta) ? $meta : null;
        return $x;
    }
}
