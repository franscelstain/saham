<?php

namespace App\DTO\MarketData;

/**
 * CandidateValidation
 *
 * DTO untuk row md_candidate_validation.
 */
final class CandidateValidation
{
    /** @var int */
    public $runId;
    /** @var string */
    public $tradeDate;
    /** @var int */
    public $tickerId;
    /** @var string */
    public $tickerCode;
    /** @var string */
    public $status;
    /** @var ?float */
    public $primaryClose;
    /** @var ?float */
    public $validatorClose;
    /** @var ?float */
    public $diffPct;
    /** @var ?string */
    public $note;

    public static function fromObject(object $r): self
    {
        $o = new self();
        $o->runId = (int) ($r->run_id ?? 0);
        $o->tradeDate = (string) ($r->trade_date ?? '');
        $o->tickerId = (int) ($r->ticker_id ?? 0);
        $o->tickerCode = (string) ($r->ticker_code ?? '');
        $o->status = (string) ($r->status ?? '');
        $o->primaryClose = $r->primary_close !== null ? (float) $r->primary_close : null;
        $o->validatorClose = $r->validator_close !== null ? (float) $r->validator_close : null;
        $o->diffPct = $r->diff_pct !== null ? (float) $r->diff_pct : null;
        $o->note = $r->note !== null ? (string) $r->note : null;
        return $o;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'trade_date' => $this->tradeDate,
            'ticker_id' => $this->tickerId,
            'ticker_code' => $this->tickerCode,
            'status' => $this->status,
            'primary_close' => $this->primaryClose,
            'validator_close' => $this->validatorClose,
            'diff_pct' => $this->diffPct,
            'note' => $this->note,
        ];
    }
}
