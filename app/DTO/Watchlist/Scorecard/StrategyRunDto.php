<?php

namespace App\DTO\Watchlist\Scorecard;

use App\Trade\Watchlist\Config\ScorecardConfig;

class StrategyRunDto
{
    /** @param CandidateDto[] $topPicks @param CandidateDto[] $secondary @param CandidateDto[] $watchOnly */
    public function __construct(
        public int $runId,
        public string $tradeDate,
        public string $execDate,
        public string $policy,
        public string $recommendationMode,
        public string $generatedAt,
        public array $topPicks,
        public array $secondary,
        public array $watchOnly,
    ) {
    }

    /**
     * Build from stored payload array.
     *
     * @param array<string,mixed> $payload
     */
    public static function fromPayloadArray(array $payload, int $runId, ScorecardConfig $cfg): self
    {
        $tradeDate = (string)($payload['trade_date'] ?? '');
        $execDate = (string)($payload['exec_trade_date'] ?? ($payload['exec_date'] ?? ''));
        $policy = (string)(($payload['policy']['selected'] ?? '') ?: ($payload['policy'] ?? ''));
        $mode = strtoupper(trim((string)($payload['recommendation']['mode'] ?? ($payload['mode'] ?? ''))));

        $gen = null;
        if (isset($payload['meta']['generated_at']) && is_string($payload['meta']['generated_at']) && $payload['meta']['generated_at'] !== '') {
            $gen = $payload['meta']['generated_at'];
        } elseif (isset($payload['generated_at']) && is_string($payload['generated_at']) && $payload['generated_at'] !== '') {
            $gen = $payload['generated_at'];
        }
        $generatedAt = $gen ?? '';

        $guardsFallback = new CandidateGuardsDto($cfg->maxChasePctDefault, $cfg->gapUpBlockPctDefault, $cfg->spreadMaxPctDefault);

        $groups = is_array($payload['groups'] ?? null) ? $payload['groups'] : [];
        $tp = self::buildCandidateList($groups['top_picks'] ?? [], $guardsFallback);
        $sec = self::buildCandidateList($groups['secondary'] ?? [], $guardsFallback);
        $wo = self::buildCandidateList($groups['watch_only'] ?? [], $guardsFallback);

        return new self($runId, $tradeDate, $execDate, $policy, $mode, $generatedAt, $tp, $sec, $wo);
    }

    /**
     * Build a new run DTO from a normalized plan.
     *
     * @param CandidateDto[] $topPicks
     * @param CandidateDto[] $secondary
     * @param CandidateDto[] $watchOnly
     */
    public static function fromNormalized(
        string $tradeDate,
        string $execDate,
        string $policy,
        string $recommendationMode,
        string $generatedAt,
        array $topPicks,
        array $secondary,
        array $watchOnly,
    ): self {
        return new self(0, $tradeDate, $execDate, $policy, $recommendationMode, $generatedAt, $topPicks, $secondary, $watchOnly);
    }

    /**
     * @return array<string,mixed>
     */
    public function toPayloadArray(): array
    {
        return [
            'trade_date' => $this->tradeDate,
            'exec_trade_date' => $this->execDate,
            'exec_date' => $this->execDate,
            'policy' => $this->policy,
            'recommendation' => ['mode' => $this->recommendationMode],
            'meta' => ['generated_at' => $this->generatedAt],
            'generated_at' => $this->generatedAt,
            'groups' => [
                'top_picks' => array_map(fn(CandidateDto $c) => $c->toArray(), $this->topPicks),
                'secondary' => array_map(fn(CandidateDto $c) => $c->toArray(), $this->secondary),
                'watch_only' => array_map(fn(CandidateDto $c) => $c->toArray(), $this->watchOnly),
            ],
        ];
    }

    /**
     * @param mixed $rows
     * @param CandidateGuardsDto $guardsFallback
     * @return CandidateDto[]
     */
    private static function buildCandidateList($rows, CandidateGuardsDto $guardsFallback): array
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
