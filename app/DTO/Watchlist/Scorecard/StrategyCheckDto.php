<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * DB row wrapper for a stored check.
 * PHP 7.3 compatible.
 */
class StrategyCheckDto
{
    /** @var int */
    public $checkId;
    /** @var string */
    public $checkedAt;
    /** @var array<string,mixed> */
    public $snapshot;
    /** @var array<string,mixed> */
    public $result;

    /**
     * @param int $checkId
     * @param string $checkedAt
     * @param array<string,mixed> $snapshot
     * @param array<string,mixed> $result
     */
    public function __construct($checkId, $checkedAt, array $snapshot, array $result)
    {
        $this->checkId = (int)$checkId;
        $this->checkedAt = (string)$checkedAt;
        $this->snapshot = $snapshot;
        $this->result = $result;
    }
}
