<?php

namespace App\Trade\Pricing;

/**
 * TickLadderConfig
 *
 * Holder tick ladder IDX agar TickRule tidak baca config() langsung.
 */
final class TickLadderConfig
{
    /** @var array<int,array{lt?:int|float|null,tick?:int|null}> */
    private array $ladder;

    /**
     * @param array<int,array{lt?:int|float|null,tick?:int|null}> $ladder
     */
    public function __construct(array $ladder)
    {
        $this->ladder = $ladder;
    }

    /**
     * @return array<int,array{lt?:int|float|null,tick?:int|null}>
     */
    public function ladder(): array
    {
        return $this->ladder;
    }
}
