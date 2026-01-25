<?php

namespace App\DTO\Watchlist\Scorecard;

use App\Trade\Watchlist\Config\ScorecardConfig;

class LiveSnapshotDto
{
    /** @param array<string,LiveTickerDto> $tickers */
    public function __construct(
        public string $checkedAt,
        public string $checkpoint,
        public string $sessionOpenTime,
        public string $sessionCloseTime,
        public array $tickers,
    ) {
    }

    /**
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a, ScorecardConfig $cfg, string $checkedAtFallback): self
    {
        $checkedAt = (string)($a['checked_at'] ?? '');
        if ($checkedAt === '') $checkedAt = $checkedAtFallback;

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
            $tickers,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'checked_at' => $this->checkedAt,
            'checkpoint' => $this->checkpoint,
            'session_open_time' => $this->sessionOpenTime,
            'session_close_time' => $this->sessionCloseTime,
            'tickers' => array_values(array_map(fn(LiveTickerDto $t) => $t->toArray(), $this->tickers)),
        ];
    }
}
