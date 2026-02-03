<?php

namespace App\DTO\Watchlist\Scorecard;

/**
 * Reason object (LOCKED by docs/watchlist/scorecard.md).
 * - code: stable machine code
 * - message: 1 kalimat user-facing
 * - severity: optional INFO|WARN|BLOCK
 *
 * PHP 7.3 compatible.
 */
class ReasonDto
{
    /** @var string */
    public $code;
    /** @var string */
    public $message;
    /** @var string|null */
    public $severity;

    public function __construct($code, $message, $severity = null)
    {
        $this->code = (string)$code;
        $this->message = (string)$message;
        $sev = $severity === null ? null : strtoupper(trim((string)$severity));
        if ($sev === '') $sev = null;
        if ($sev !== null && !in_array($sev, ['INFO','WARN','BLOCK'], true)) {
            $sev = null;
        }
        $this->severity = $sev;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        $a = [
            'code' => $this->code,
            'message' => $this->message,
        ];
        if ($this->severity !== null) {
            $a['severity'] = $this->severity;
        }
        return $a;
    }

    /**
     * @param array<string,mixed> $a
     */
    public static function fromArray(array $a)
    {
        return new self(
            (string)($a['code'] ?? ''),
            (string)($a['message'] ?? ''),
            isset($a['severity']) ? (string)$a['severity'] : null
        );
    }
}
