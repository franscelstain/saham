<?php

namespace App\DTO;

/**
 * BaseDto
 *
 * Minimal DTO base used across TradeAxis.
 *
 * Goals (docs/DTO.md):
 * - DTOs are plain data carriers.
 * - No framework helpers inside DTO.
 * - Prefer immutability: DTOs should not be mutated after construction.
 *
 * PHP 7.4 compatible.
 */
abstract class BaseDto
{
    /**
     * Read-only property access bridge (Phase 2A).
     *
     * Many call sites still use "$dto->field" access. We keep that working
     * while moving DTO fields to private typed properties.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->shouldStrictBanLegacyAccess()) {
            throw new \LogicException(static::class . " blocks legacy property access ($name). Use getters.");
        }

        // guardrail: kalau masih pakai legacy access, log biar kamu bisa bersihin callsite bertahap
        if ($this->shouldLogLegacyAccess()) {
            // jangan spam log production kalau nggak mau; bisa gate by env
            \Log::warning(static::class . " legacy property access: $" . "dto->$name", [
                'class' => static::class,
                'field' => $name,
            ]);
        }

        $method = $this->getterMethodName($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new \InvalidArgumentException(sprintf(
            'Property "%s" does not exist on %s',
            $name,
            static::class
        ));
    }

    /**
     * Disallow mutation (Phase 2A).
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value): void
    {
        $cls = get_class($this);
        throw new \LogicException("DTO is immutable: cannot set {$cls}::\${$name}");
    }

    /**
     * @param string $name
     */
    public function __isset($name): bool
    {
        $rc = new \ReflectionClass($this);
        if (!$rc->hasProperty((string)$name)) return false;
        $rp = $rc->getProperty((string)$name);
        $rp->setAccessible(true);
        return $rp->getValue($this) !== null;
    }

    /**
     * @param string $name
     */
    public function __unset($name): void
    {
        $cls = get_class($this);
        throw new \LogicException("DTO is immutable: cannot unset {$cls}::\${$name}");
    }

    /**
     * Convert DTO into array for contract mapping.
     *
     * DTO children should override.
     */
    public function toArray(): array
    {
        return [];
    }

    protected function shouldStrictBanLegacyAccess(): bool
    {
        // hanya scope ke Scorecard DTO
        if (strpos(static::class, 'App\\DTO\\Watchlist\\Scorecard\\') !== 0) {
            return false;
        }

        return (bool) config('trade.watchlist.scorecard.dto_strict_ban', false);
    }

    protected function shouldLogLegacyAccess(): bool
    {
        if (strpos(static::class, 'App\\DTO\\Watchlist\\Scorecard\\') !== 0) {
            return false;
        }
        // log cuma saat strict ban OFF (biar jadi “early warning” sebelum strict ON)
        if ((bool) config('trade.watchlist.scorecard.dto_strict_ban', false)) {
            return false;
        }
        return (bool) config('trade.watchlist.scorecard.dto_legacy_log', true);
    }
}
