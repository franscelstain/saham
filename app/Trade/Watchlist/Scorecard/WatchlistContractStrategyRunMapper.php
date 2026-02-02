<?php

namespace App\Trade\Watchlist\Scorecard;

use App\DTO\Watchlist\Scorecard\CandidateDto;
use App\DTO\Watchlist\Scorecard\CandidateGuardsDto;
use App\DTO\Watchlist\Scorecard\StrategyRunDto;
use App\Support\Clock;
use App\Trade\Watchlist\Config\ScorecardConfig;

/**
 * Adapter: map Watchlist contract payload (array) -> StrategyRunDto.
 *
 * Arrays are allowed here because this is the boundary adapter.
 * Scorecard domain/services remain DTO-only.
 */
class WatchlistContractStrategyRunMapper
{
    /** @var ScorecardConfig */
    private $cfg;
    /** @var Clock */
    private $clock;

    public function __construct(ScorecardConfig $cfg, Clock $clock)
    {
        $this->cfg = $cfg;
        $this->clock = $clock;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function fromPayload(array $payload): StrategyRunDto
    {
        $tradeDate = (string)($payload['trade_date'] ?? '');
        $execDate = (string)($payload['exec_trade_date'] ?? ($payload['exec_date'] ?? ''));
        $policy = (string)(($payload['policy']['selected'] ?? '') ?: ($payload['policy'] ?? ''));

        $generatedAt = null;
        if (isset($payload['meta']['generated_at']) && is_string($payload['meta']['generated_at']) && $payload['meta']['generated_at'] !== '') {
            $generatedAt = $payload['meta']['generated_at'];
        } elseif (isset($payload['generated_at']) && is_string($payload['generated_at']) && $payload['generated_at'] !== '') {
            $generatedAt = $payload['generated_at'];
        } else {
            $generatedAt = $this->clock->nowRfc3339();
        }

        $mode = strtoupper(trim((string)($payload['recommendation']['mode'] ?? ($payload['mode'] ?? ''))));

        $groups = (array)($payload['groups'] ?? []);

        $guardsFallback = new CandidateGuardsDto(
            $this->cfg->maxChasePctDefault,
            $this->cfg->gapUpBlockPctDefault,
            $this->cfg->spreadMaxPctDefault
        );

        $top = $this->normalizeCandidateList($groups['top_picks'] ?? [], $guardsFallback);
        $sec = $this->normalizeCandidateList($groups['secondary'] ?? [], $guardsFallback);
        $wo = $this->normalizeCandidateList($groups['watch_only'] ?? [], $guardsFallback);

        return StrategyRunDto::fromNormalized($tradeDate, $execDate, $policy, $mode, $generatedAt, $top, $sec, $wo);
    }

    /**
     * @param mixed $rows
     * @return CandidateDto[]
     */
    private function normalizeCandidateList($rows, CandidateGuardsDto $guardsFallback): array
    {
        if (!is_array($rows)) return [];

        $out = [];
        $rank = 1;
        foreach ($rows as $cand) {
            if (!is_array($cand)) continue;
            $dto = CandidateDto::fromArray($cand, $guardsFallback, $rank);
            if ($dto->ticker !== '') {
                $out[] = $dto;
                $rank++;
            }
        }
        return $out;
    }
}
