<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Timing rules for a candidate.
 * PHP 7.3 compatible.
 */
class CandidateTimingDto
{
    /** @var bool */
    public $tradeDisabled;
    /** @var string[] */
    public $entryWindows;
    /** @var string[] */
    public $avoidWindows;
    /** @var string|null */
    public $tradeDisabledReason;
    /** @var string[] */
    public $tradeDisabledReasonCodes;

    /**
     * @param bool $tradeDisabled
     * @param string[] $entryWindows
     * @param string[] $avoidWindows
     * @param string|null $tradeDisabledReason
     * @param string[] $tradeDisabledReasonCodes
     */
    public function __construct($tradeDisabled, array $entryWindows, array $avoidWindows, $tradeDisabledReason, array $tradeDisabledReasonCodes)
    {
        $this->tradeDisabled = (bool)$tradeDisabled;
        $this->entryWindows = array_values($entryWindows);
        $this->avoidWindows = array_values($avoidWindows);
        $this->tradeDisabledReason = ($tradeDisabledReason === null) ? null : (string)$tradeDisabledReason;
        $this->tradeDisabledReasonCodes = array_values($tradeDisabledReasonCodes);
    }

    /**
     * @param array<string,mixed> $a
     * @return self
     */
    public static function fromArray(array $a)
    {
        $entry = (isset($a['entry_windows']) && is_array($a['entry_windows'])) ? array_values($a['entry_windows']) : [];
        $avoid = (isset($a['avoid_windows']) && is_array($a['avoid_windows'])) ? array_values($a['avoid_windows']) : [];
        $codes = (isset($a['trade_disabled_reason_codes']) && is_array($a['trade_disabled_reason_codes'])) ? array_values($a['trade_disabled_reason_codes']) : [];

        return new self(
            (bool)($a['trade_disabled'] ?? false),
            array_map('strval', $entry),
            array_map('strval', $avoid),
            isset($a['trade_disabled_reason']) ? (string)$a['trade_disabled_reason'] : null,
            array_map('strval', $codes)
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        return [
            'trade_disabled' => (bool)$this->tradeDisabled,
            'entry_windows' => array_values($this->entryWindows),
            'avoid_windows' => array_values($this->avoidWindows),
            'trade_disabled_reason' => $this->tradeDisabledReason,
            'trade_disabled_reason_codes' => array_values($this->tradeDisabledReasonCodes),
        ];
    }
}
