<?php

namespace App\DTO\Watchlist\Scorecard;

class CandidateTimingDto
{
    /** @param string[] $entryWindows @param string[] $avoidWindows @param string[] $tradeDisabledReasonCodes */
    public function __construct(
        public bool $tradeDisabled,
        public array $entryWindows,
        public array $avoidWindows,
        public ?string $tradeDisabledReason,
        public array $tradeDisabledReasonCodes,
    ) {
    }

    /**
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a): self
    {
        $entry = isset($a['entry_windows']) && is_array($a['entry_windows']) ? array_values($a['entry_windows']) : [];
        $avoid = isset($a['avoid_windows']) && is_array($a['avoid_windows']) ? array_values($a['avoid_windows']) : [];
        $codes = isset($a['trade_disabled_reason_codes']) && is_array($a['trade_disabled_reason_codes']) ? array_values($a['trade_disabled_reason_codes']) : [];
        return new self(
            (bool)($a['trade_disabled'] ?? false),
            array_map('strval', $entry),
            array_map('strval', $avoid),
            isset($a['trade_disabled_reason']) ? (string)$a['trade_disabled_reason'] : null,
            array_map('strval', $codes),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'trade_disabled' => $this->tradeDisabled,
            'entry_windows' => array_values($this->entryWindows),
            'avoid_windows' => array_values($this->avoidWindows),
            'trade_disabled_reason' => $this->tradeDisabledReason,
            'trade_disabled_reason_codes' => array_values($this->tradeDisabledReasonCodes),
        ];
    }
}
