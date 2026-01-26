<?php

namespace App\DTO\Watchlist;

final class PolicyDocCheckResult
{
    /** @var bool */
    public $ok;

    /** @var string */
    public $policy;

    /** @var string|null */
    public $resolvedRoot;

    /** @var string|null */
    public $resolvedPath;

    /** @var string|null */
    public $reason;

    private function __construct(bool $ok, string $policy, ?string $resolvedRoot, ?string $resolvedPath, ?string $reason)
    {
        $this->ok = $ok;
        $this->policy = $policy;
        $this->resolvedRoot = $resolvedRoot;
        $this->resolvedPath = $resolvedPath;
        $this->reason = $reason;
    }

    public static function ok(string $policy, ?string $resolvedRoot, ?string $resolvedPath): self
    {
        return new self(true, $policy, $resolvedRoot, $resolvedPath, null);
    }

    public static function fail(string $policy, ?string $resolvedRoot, ?string $resolvedPath, string $reason): self
    {
        return new self(false, $policy, $resolvedRoot, $resolvedPath, $reason);
    }
}
