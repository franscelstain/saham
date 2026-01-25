<?php

namespace App\Exceptions;

/**
 * PortfolioInconsistentStateException
 *
 * Per docs/PORTFOLIO.md:
 * - positions.qty MUST equal SUM(lots.remaining_qty)
 * - on mismatch: emit INCONSISTENT_STATE audit event and stop (fail-fast)
 *
 * IMPORTANT: The audit event must be written OUTSIDE the failed DB transaction.
 */
class PortfolioInconsistentStateException extends \RuntimeException
{
    public int $accountId;
    public int $tickerId;
    public int $expectedQty;
    public int $positionQty;
    public ?string $strategyCode;
    public ?string $planVersion;
    public ?string $asOfTradeDate;

    public function __construct(
        int $accountId,
        int $tickerId,
        int $positionQty,
        int $expectedQty,
        ?string $strategyCode = null,
        ?string $planVersion = null,
        ?string $asOfTradeDate = null
    ) {
        parent::__construct('inconsistent_state_positions_qty');
        $this->accountId = $accountId;
        $this->tickerId = $tickerId;
        $this->positionQty = $positionQty;
        $this->expectedQty = $expectedQty;
        $this->strategyCode = $strategyCode;
        $this->planVersion = $planVersion;
        $this->asOfTradeDate = $asOfTradeDate;
    }
}
