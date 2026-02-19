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
        if (!is_string($name) || $name === '') {
            trigger_error('Invalid DTO property access', E_USER_NOTICE);
            return null;
        }

        $rc = new \ReflectionClass($this);
        if (!$rc->hasProperty($name)) {
            // mimic native behaviour
            $cls = $rc->getName();
            trigger_error("Undefined property: {$cls}::\${$name}", E_USER_NOTICE);
            return null;
        }

        $rp = $rc->getProperty($name);
        $rp->setAccessible(true);
        return $rp->getValue($this);
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
}
