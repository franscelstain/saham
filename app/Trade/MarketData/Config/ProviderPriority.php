<?php

namespace App\Trade\MarketData\Config;

final class ProviderPriority
{
    /** @var string[] */
    private $names;

    /**
     * @param string[] $names
     */
    public function __construct(array $names)
    {
        $out = [];
        foreach ($names as $n) {
            $n = strtolower(trim((string) $n));
            if ($n !== '' && !in_array($n, $out, true)) $out[] = $n;
        }
        $this->names = $out ?: ['yahoo'];
    }

    /** @return string[] */
    public function names(): array { return $this->names; }

    public function first(): string { return (string) ($this->names[0] ?? 'yahoo'); }
}
