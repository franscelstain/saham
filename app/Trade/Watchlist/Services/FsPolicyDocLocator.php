<?php

namespace App\Trade\Watchlist\Services;

use App\DTO\Watchlist\PolicyDocCheckResult;
use App\Trade\Watchlist\Contracts\PolicyDocLocator;

final class FsPolicyDocLocator implements PolicyDocLocator
{
    /** @var string|null */
    private $rootFromEnv;

    /** @var bool */
    private $strict;

    /** @var array<string,string> */
    private $policyMap;

    /**
     * @param array<string,string> $policyMap policy_code => filename
     */
    public function __construct(?string $rootFromEnv, bool $strict, array $policyMap)
    {
        $rootFromEnv = is_string($rootFromEnv) ? trim($rootFromEnv) : '';
        $this->rootFromEnv = ($rootFromEnv !== '') ? $rootFromEnv : null;
        $this->strict = $strict;
        $this->policyMap = $policyMap;
    }

    public function check(string $policyCode): PolicyDocCheckResult
    {
        $policyCode = strtoupper(trim($policyCode));

        $root = $this->resolveRoot();

        // Docs optional (prod friendly) unless strict enabled.
        if ($root === null) {
            if ($this->strict) {
                return PolicyDocCheckResult::fail($policyCode, null, null, 'POLICY_DOCS_ROOT_NOT_FOUND');
            }
            return PolicyDocCheckResult::ok($policyCode, null, null);
        }

        $file = $this->policyMap[$policyCode] ?? null;
        if ($file === null) {
            return PolicyDocCheckResult::fail($policyCode, $root, null, 'UNKNOWN_POLICY_CODE');
        }

        // Support both:
        // - root points to docs/watchlist/policy
        // - root points to docs/watchlist (then subfolder policy is appended)
        $p1 = rtrim($root, "/\\") . DIRECTORY_SEPARATOR . $file;
        $p2 = rtrim($root, "/\\") . DIRECTORY_SEPARATOR . 'policy' . DIRECTORY_SEPARATOR . $file;

        if (is_file($p1)) return PolicyDocCheckResult::ok($policyCode, $root, $p1);
        if (is_file($p2)) return PolicyDocCheckResult::ok($policyCode, $root, $p2);

        return PolicyDocCheckResult::fail($policyCode, $root, $p2, 'POLICY_DOC_MISSING');
    }

    private function resolveRoot(): ?string
    {
        $candidates = [];

        if ($this->rootFromEnv) {
            $candidates[] = $this->rootFromEnv;
        }

        if (function_exists('base_path')) {
            $candidates[] = base_path('docs/watchlist/policy');
            $candidates[] = base_path('docs/watchlist/policy');
        }

        // Fallback non-Laravel: this file is in app/Trade/Watchlist/Services
        // so project root is 4 levels up.
        $projectRoot = dirname(__DIR__, 4);
        $candidates[] = $projectRoot . '/docs/watchlist/policy';
        $candidates[] = $projectRoot . '/docs/watchlist/policy';

        foreach ($candidates as $r) {
            if (is_string($r)) {
                $r = trim($r);
                if ($r !== '' && is_dir($r)) return $r;
            }
        }

        return null;
    }
}
