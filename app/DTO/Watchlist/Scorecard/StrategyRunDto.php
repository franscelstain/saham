<?php

namespace App\DTO\Watchlist\Scorecard;

use App\DTO\BaseDto;
use App\Trade\Watchlist\Config\ScorecardConfig;

/**
 * Strategy Run DTO (stored plan for scorecard).
 *
 * Phase 2A:
 * - Make fields private (immutable).
 * - Keep read-only "$dto->field" access via BaseDto::__get.
 *
 * PHP 7.4 compatible.
 */
class StrategyRunDto extends BaseDto
{
    private int $runId;
    private string $tradeDate;
    private string $execDate;
    private string $policy;
    private string $recommendationMode;
    private string $generatedAt;
    /** @var CandidateDto[] */
    private array $topPicks;
    /** @var CandidateDto[] */
    private array $secondary;
    /** @var CandidateDto[] */
    private array $watchOnly;

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
        $mode = strtoupper(trim((string)(($payload['recommendations']['mode'] ?? null) ?? ($payload['mode'] ?? ''))));

        $gen = null;
        if (isset($payload['meta']['generated_at']) && is_string($payload['meta']['generated_at']) && $payload['meta']['generated_at'] !== '') {
            $gen = $payload['meta']['generated_at'];
        } elseif (isset($payload['generated_at']) && is_string($payload['generated_at']) && $payload['generated_at'] !== '') {
            $gen = $payload['generated_at'];
        }
        $generatedAt = $gen ?? '';

        $groups = is_array($payload['groups'] ?? null) ? $payload['groups'] : [];
        $tp = self::buildCandidateList($groups['top_picks'] ?? []);
        $sec = self::buildCandidateList($groups['secondary'] ?? []);
        $wo = self::buildCandidateList($groups['watch_only'] ?? []);

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
    /**
     * Phase 2B.2: forbid legacy property access (use getters).
     */
    public function __get($name)
    {
        // Guarded strict-ban (scoped in BaseDto to App\\DTO\\Watchlist\\Scorecard\\*).
        // When strict-ban is OFF, allow legacy access through BaseDto (ReflectionProperty) to avoid breaking callsites.
        if ($this->shouldStrictBanLegacyAccess()) {
            throw new \LogicException('StrategyRunDto is immutable and does not expose public properties. Use getters like runId(), tradeDate(), policy(), topPicks(), etc.');
        }

        return parent::__get($name);
    }

    public function __isset($name): bool
    {
        if ($this->shouldStrictBanLegacyAccess()) return false;
        return parent::__isset($name);
    }

    public function runId(): int { return (int)$this->runId; }
    public function tradeDate(): string { return $this->tradeDate; }
    public function execDate(): string { return $this->execDate; }
    public function policy(): string { return $this->policy; }
    public function recommendationMode(): string { return $this->recommendationMode; }
    public function generatedAt(): string { return $this->generatedAt; }
    public function topPicks(): array { return array_values($this->topPicks); }
    public function secondary(): array { return array_values($this->secondary); }
    public function watchOnly(): array { return array_values($this->watchOnly); }

    public function toPayloadArray(): array
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
            'recommendations' => ['mode' => $this->recommendationMode],
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
     * @return CandidateDto[]
     */
    private static function buildCandidateList($rows)
    {
        if (!is_array($rows)) return [];
        $out = [];
        $rank = 1;
        foreach ($rows as $cand) {
            if (!is_array($cand)) continue;
            $dto = CandidateDto::fromArray($cand, $rank);
            if ($dto->ticker() !== '') {
                $out[] = $dto;
                $rank++;
            }
        }
        return $out;
    }
}
