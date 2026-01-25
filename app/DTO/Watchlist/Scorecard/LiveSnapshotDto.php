<?php

namespace App\DTO\Watchlist\Scorecard;

use App\Trade\Watchlist\Config\ScorecardConfig;

/**
 * Live snapshot wrapper.
 * PHP 7.3 compatible.
 */
class LiveSnapshotDto
{
    /** @var string */
    public $checkedAt;
    /** @var string */
    public $checkpoint;
    /** @var string */
    public $sessionOpenTime;
    /** @var string */
    public $sessionCloseTime;
    /** @var array<string,LiveTickerDto> */
    public $tickers;

    /**
     * @param string $checkedAt
     * @param string $checkpoint
     * @param string $sessionOpenTime
     * @param string $sessionCloseTime
     * @param array<string,LiveTickerDto> $tickers
     */
    public function __construct($checkedAt, $checkpoint, $sessionOpenTime, $sessionCloseTime, array $tickers)
    {
        $this->checkedAt = (string)$checkedAt;
        $this->checkpoint = (string)$checkpoint;
        $this->sessionOpenTime = (string)$sessionOpenTime;
        $this->sessionCloseTime = (string)$sessionCloseTime;
        $this->tickers = $tickers;
    }

    /**
     * @param array<string,mixed> $a
     * @param ScorecardConfig $cfg
     * @param string $checkedAtFallback
     * @return self
     */
    public static function fromArray(array $a, ScorecardConfig $cfg, $checkedAtFallback)
    {
        $checkedAt = (string)($a['checked_at'] ?? '');
        if ($checkedAt === '') $checkedAt = (string)$checkedAtFallback;

        $sessionOpen = (string)($a['session_open_time'] ?? $cfg->sessionOpenTimeDefault);
        $sessionClose = (string)($a['session_close_time'] ?? $cfg->sessionCloseTimeDefault);

        $tickers = [];
        $rows = $a['tickers'] ?? [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $dto = LiveTickerDto::fromArray($row);
                if ($dto->ticker === '') continue;
                $tickers[$dto->ticker] = $dto;
            }
        }

        return new self(
            $checkedAt,
            (string)($a['checkpoint'] ?? ''),
            $sessionOpen,
            $sessionClose,
            $tickers
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        $tickers = [];
        foreach ($this->tickers as $t) {
            if ($t instanceof LiveTickerDto) $tickers[] = $t->toArray();
        }

        return [
            'checked_at' => $this->checkedAt,
            'checkpoint' => $this->checkpoint,
            'session_open_time' => $this->sessionOpenTime,
            'session_close_time' => $this->sessionCloseTime,
            'tickers' => array_values($tickers),
        ];
    }
}
