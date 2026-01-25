<?php

namespace App\DTO\Watchlist\Scorecard;

use App\Trade\Watchlist\Config\ScorecardConfig;

/**
 * Strategy Run DTO (stored plan for scorecard).
 * PHP 7.3 compatible.
 */
class StrategyRunDto
{
    /** @var int */
    public $runId;
    /** @var string */
    public $tradeDate;
    /** @var string */
    public $execDate;
    /** @var string */
    public $policy;
    /** @var string */
    public $recommendationMode;
    /** @var string */
    public $generatedAt;
    /** @var CandidateDto[] */
    public $topPicks;
    /** @var CandidateDto[] */
    public $secondary;
    /** @var CandidateDto[] */
    public $watchOnly;

    /**
     * @param int $runId
     * @param string $tradeDate
     * @param string $execDate
     * @param string $policy
     * @param string $recommendationMode
     * @param string $generatedAt
     * @param CandidateDto[] $topPicks
     * @param CandidateDto[] $secondary
     * @param CandidateDto[] $watchOnly
     */
    public function __construct($runId, $tradeDate, $execDate, $policy, $recommendationMode, $generatedAt, array $topPicks, array $secondary, array $watchOnly)
    {
        $this->runId = (int)$runId;
        $this->tradeDate = (string)$tradeDate;
        $this->execDate = (string)$execDate;
        $this->policy = (string)$policy;
        $this->recommendationMode = (string)$recommendationMode;
        $this->generatedAt = (string)$generatedAt;
        $this->topPicks = array_values($topPicks);
        $this->secondary = array_values($secondary);
        $this->watchOnly = array_values($watchOnly);
    }

    /**
     * Build from stored payload array.
     *
     * @param array<string,mixed> $payload
     * @param int $runId
     * @param ScorecardConfig $cfg
     * @return self
     */
    public static function fromPayloadArray(array $payload, $runId, ScorecardConfig $cfg)
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
     * @param string $tradeDate
     * @param string $execDate
     * @param string $policy
     * @param string $recommendationMode
     * @param string $generatedAt
     * @param CandidateDto[] $topPicks
     * @param CandidateDto[] $secondary
     * @param CandidateDto[] $watchOnly
     * @return self
     */
    public static function fromNormalized($tradeDate, $execDate, $policy, $recommendationMode, $generatedAt, array $topPicks, array $secondary, array $watchOnly)
    {
        return new self(0, $tradeDate, $execDate, $policy, $recommendationMode, $generatedAt, $topPicks, $secondary, $watchOnly);
    }

    /**
     * @return array<string,mixed>
     */
    public function toPayloadArray()
    {
        $top = [];
        foreach ($this->topPicks as $c) {
            if ($c instanceof CandidateDto) $top[] = $c->toArray();
        }
        $sec = [];
        foreach ($this->secondary as $c) {
            if ($c instanceof CandidateDto) $sec[] = $c->toArray();
        }
        $wo = [];
        foreach ($this->watchOnly as $c) {
            if ($c instanceof CandidateDto) $wo[] = $c->toArray();
        }

        return [
            'trade_date' => $this->tradeDate,
            'exec_trade_date' => $this->execDate,
            'exec_date' => $this->execDate,
            'policy' => $this->policy,
            'recommendation' => ['mode' => $this->recommendationMode],
            'meta' => ['generated_at' => $this->generatedAt],
            'generated_at' => $this->generatedAt,
            'groups' => [
                'top_picks' => $top,
                'secondary' => $sec,
                'watch_only' => $wo,
            ],
        ];
    }

    /**
     * @param mixed $rows
     * @param CandidateGuardsDto $guardsFallback
     * @return CandidateDto[]
     */
    private static function buildCandidateList($rows, CandidateGuardsDto $guardsFallback)
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
