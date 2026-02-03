<?php

namespace App\DTO\Watchlist\Scorecard;

use App\Trade\Explain\ReasonCatalog;

/**
 * Per-ticker eligibility result.
 * Output schema is LOCKED by docs/watchlist/scorecard.md.
 * PHP 7.3 compatible.
 */
class EligibilityResultDto
{
    /** @var string */
    public $tickerCode;
    /** @var bool */
    public $eligibleNow;
    /** @var string */
    public $decision; // APPROVE|REJECT|DELAY
    /** @var string|null */
    public $nextCheckAt;

    /** @var ReasonDto[] */
    public $reasons;

    /** @var array<string,mixed> */
    public $plan;
    /** @var array<string,mixed> */
    public $computed;
    /** @var array<string,mixed> */
    public $live;
    /** @var array<int,array<string,mixed>> */
    public $recommendedOrders;

    // Backward-compat fields (used by older calculators/tests)
    /** @var array<int,string> */
    public $flags;
    /** @var float|null */
    public $gapPct;
    /** @var float|null */
    public $spreadPct;
    /** @var float|null */
    public $chasePct;
    /** @var string */
    public $notes;

    /**
     * @param string $tickerCode
     * @param bool $eligibleNow
     * @param array<int,string> $flags
     * @param float|null $gapPct
     * @param float|null $spreadPct
     * @param float|null $chasePct
     * @param array<int,string|array<string,mixed>|ReasonDto> $reasons
     * @param string $notes
     * @param string $decision
     * @param string|null $nextCheckAt
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $computed
     * @param array<string,mixed> $live
     * @param array<int,array<string,mixed>> $recommendedOrders
     */
    public function __construct(
        $tickerCode,
        $eligibleNow,
        array $flags = [],
        $gapPct = null,
        $spreadPct = null,
        $chasePct = null,
        array $reasons = [],
        $notes = '',
        $decision = 'REJECT',
        $nextCheckAt = null,
        array $plan = [],
        array $computed = [],
        array $live = [],
        array $recommendedOrders = []
    ) {
        $this->tickerCode = strtoupper(trim((string)$tickerCode));
        $this->eligibleNow = (bool)$eligibleNow;
        $this->decision = strtoupper(trim((string)$decision));
        if (!in_array($this->decision, ['APPROVE','REJECT','DELAY'], true)) $this->decision = 'REJECT';
        $this->nextCheckAt = ($nextCheckAt === null) ? null : (string)$nextCheckAt;

        $this->flags = array_values(array_map('strval', $flags));
        $this->gapPct = ($gapPct === null) ? null : (float)$gapPct;
        $this->spreadPct = ($spreadPct === null) ? null : (float)$spreadPct;
        $this->chasePct = ($chasePct === null) ? null : (float)$chasePct;
        $this->notes = (string)$notes;

        $this->reasons = [];
        foreach ($reasons as $r) {
            if ($r instanceof ReasonDto) {
                $this->reasons[] = $r;
                continue;
            }
            if (is_array($r)) {
                $this->reasons[] = ReasonDto::fromArray($r);
                continue;
            }
            $code = (string)$r;
            if ($code !== '') {
                $this->reasons[] = new ReasonDto($code, ReasonCatalog::getMessage($code));
            }
        }

        $this->plan = $plan;
        $this->computed = $computed;
        $this->live = $live;
        $this->recommendedOrders = array_values($recommendedOrders);
    }

    /**
     * Strict output.
     *
     * @return array<string,mixed>
     */
    public function toArray()
    {
        $reasons = [];
        foreach ($this->reasons as $r) {
            if ($r instanceof ReasonDto) $reasons[] = $r->toArray();
        }

        // Ensure computed contains the common pct fields (for UI stability)
        $computed = $this->computed;
        if ($this->gapPct !== null && !array_key_exists('gap_pct', $computed)) $computed['gap_pct'] = $this->gapPct;
        if ($this->spreadPct !== null && !array_key_exists('spread_pct', $computed)) $computed['spread_pct'] = $this->spreadPct;
        if ($this->chasePct !== null && !array_key_exists('chase_pct', $computed)) $computed['chase_pct'] = $this->chasePct;

        $a = [
            'ticker_code' => $this->tickerCode,
            'decision' => $this->decision,
            'eligible_now' => (bool)$this->eligibleNow,
            'reasons' => $reasons,
            'plan' => $this->plan,
            'computed' => $computed,
            'live' => $this->live,
            'recommended_orders' => array_values($this->recommendedOrders),
        ];
        if ($this->nextCheckAt !== null && $this->nextCheckAt !== '') {
            $a['next_check_at'] = $this->nextCheckAt;
        }
        return $a;
    }
}
