<?php

namespace App\DTO;

/**
 * BaseDto
 *
 * Minimal DTO base used across TradeAxis.
 *
 * - Keeps DTOs as plain data carriers.
 * - Avoids framework helpers inside DTO.
 * - Safe for PHP 7.3/7.4.
 */
abstract class BaseDto
{
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
